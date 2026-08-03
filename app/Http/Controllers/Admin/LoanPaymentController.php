<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\Receipt;
use App\Services\ShareCashMovementService;
use App\Services\LoanSettlementService;
use App\Services\CreditHistoryService;
use App\Services\LateFeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LoanPaymentController extends Controller
{
    public function __construct(private readonly LoanSettlementService $settlement, private readonly CreditHistoryService $creditHistory, private readonly LateFeeService $lateFees)
    {
        $this->middleware('can:admin.cobros.index')->only(['index', 'list', 'summary', 'nextCode', 'loansByMember', 'installmentsByLoan']);
        $this->middleware('can:admin.cobros.create')->only(['store']);
        $this->middleware('can:admin.cobros.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.cobros.show')->only(['show']);
        $this->middleware('can:admin.cobros.anular')->only(['annul']);
        $this->middleware('can:admin.cobros.delete')->only(['destroy']);
        $this->middleware('can:admin.cobros.receipt')->only(['receipt']);
        $this->middleware('can:admin.cobros.receipt_pdf')->only(['receiptPdf']);
        $this->middleware('can:admin.cobros.voucher')->only(['voucher']);
    }

    public function index()
    {
        return view('admin.loan-payments.index', [
            'members' => Member::where('status', 'vigente')->orderBy('full_name')->get(['id', 'code', 'dni', 'full_name']),
            'loans' => Loan::with('member')->whereIn('status', ['desembolsado', 'pagado'])->orderByDesc('id')->get(),
            'nextCode' => $this->generateNextCode(),
            'systemCutoffDate' => config('utility.system_cutoff_date'),
        ]);
    }

    public function list(Request $request)
    {
        $payments = LoanPayment::with(['loan', 'member', 'receipt'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('loan_id'), fn ($query) => $query->where('loan_id', $request->integer('loan_id')))
            ->when($request->filled('payment_type'), fn ($query) => $query->where('payment_type', $request->input('payment_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('payment_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('payment_date', '<=', $request->input('date_to')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        return DataTables::of($payments)
            ->addIndexColumn()
            ->addColumn('member_name', fn (LoanPayment $payment) => $payment->member?->full_name ?? '-')
            ->addColumn('member_dni', fn (LoanPayment $payment) => $payment->member?->dni ?? '-')
            ->addColumn('loan_number', fn (LoanPayment $payment) => $payment->loan?->loan_number ?? '-')
            ->editColumn('payment_date', fn (LoanPayment $payment) => optional($payment->payment_date)->format('d/m/Y') ?? '-')
            ->editColumn('payment_type', fn (LoanPayment $payment) => $this->paymentTypeLabel($payment->payment_type))
            ->editColumn('payment_method', fn (LoanPayment $payment) => $this->paymentMethodLabel($payment->payment_method))
            ->editColumn('amount', fn (LoanPayment $payment) => 'S/ ' . number_format((float) $payment->amount, 2))
            ->addColumn('historical', function (LoanPayment $payment) {
                if (! $payment->is_historical) {
                    return '<span class="badge badge-light">Normal</span>';
                }
                $badges = ['<span class="badge badge-info">Histórico</span>'];
                if (! $payment->affects_cash) $badges[] = '<span class="badge badge-secondary">No afecta caja</span>';
                if ($payment->affects_profit) $badges[] = '<span class="badge badge-success">Afecta utilidades</span>';
                if ($payment->affects_credit_history) $badges[] = '<span class="badge badge-primary">Afecta historial</span>';
                return implode(' ', $badges);
            })
            ->editColumn('status', fn (LoanPayment $payment) => $this->statusBadge($payment->status))
            ->addColumn('acciones', fn (LoanPayment $payment) => view('admin.loan-payments.partials.acciones', compact('payment'))->render())
            ->rawColumns(['historical', 'status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'total' => number_format((float) LoanPayment::where('status', 'registrado')->sum('amount'), 2),
            'month' => number_format((float) LoanPayment::where('status', 'registrado')->whereYear('payment_date', now()->year)->whereMonth('payment_date', now()->month)->sum('amount'), 2),
            'today' => number_format((float) LoanPayment::where('status', 'registrado')->whereDate('payment_date', now()->toDateString())->sum('amount'), 2),
            'loans_with_balance' => Loan::where('status', 'desembolsado')->where('current_balance', '>', 0)->count(),
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => $this->generateNextCode()]);
    }

    public function loansByMember(Member $member)
    {
        $loans = Loan::where('member_id', $member->id)
            ->where('status', 'desembolsado')
            ->where('current_balance', '>', 0)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $loan) => $this->loanOptionPayload($loan));

        return response()->json($loans);
    }

    public function installmentsByLoan(Request $request, Loan $loan)
    {
        return response()->json([
            'loan' => $this->loanOptionPayload($loan->load('member')),
            'installments' => $loan->installments()
                ->whereIn('status', ['pendiente', 'parcial', 'vencido'])
                ->where('remaining_amount', '>', 0)
                ->orderBy('installment_number')
                ->get()
                ->map(fn (LoanInstallment $installment) => $this->installmentPayload($installment, $request->input('payment_date', today())))
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeNullableRequestFields($request);
        $data = $this->validatedData($request);
        $voucherPath = null;

        try {
            $payment = DB::transaction(function () use ($request, $data, &$voucherPath) {
                $loan = Loan::with(['member', 'installments'])->lockForUpdate()->findOrFail($data['loan_id']);
                $this->settlement->syncLoanBalance($loan);
                $loan->refresh()->load(['member', 'installments']);
                $this->ensureLoanCanBePaid($loan);
                $amount = round((float) $data['amount'], 2);

                $selectedLateFees = $data['payment_type'] === 'liquidacion'
                    ? $this->settlement->debt($loan, \Illuminate\Support\Carbon::parse($data['payment_date']))['late_fee']
                    : $loan->installments->whereIn('id', $data['installment_ids'] ?? [])->sum(fn ($row) => $this->lateFees->quote($row, $data['payment_date'])['pending']);
                if (in_array($data['payment_type'], ['adelanto_cuotas', 'abono_capital'], true) && $amount - ((float) $loan->current_balance + $selectedLateFees) > 0.01) {
                    throw ValidationException::withMessages(['amount' => ['El monto pagado no puede ser mayor al saldo pendiente del prestamo.']]);
                }

                $voucherPath = $request->hasFile('voucher_path')
                    ? $request->file('voucher_path')->store('loan-payments', 'public')
                    : null;

                $payment = LoanPayment::create([
                    'loan_id' => $loan->id,
                    'member_id' => $loan->member_id,
                    'payment_number' => $this->generateNextCode(),
                    'payment_date' => $data['payment_date'],
                    'is_historical' => $data['is_historical'],
                    'affects_cash' => $data['affects_cash'],
                    'affects_profit' => $data['affects_profit'],
                    'profit_treatment' => $data['profit_treatment'],
                    'affects_credit_history' => $data['affects_credit_history'],
                    'amount' => $amount,
                    'previous_loan_balance' => $loan->current_balance,
                    'schedule_before' => $this->scheduleSnapshot($loan),
                    'payment_type' => $data['payment_type'],
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'voucher_path' => $voucherPath,
                    'observation' => $data['observation'] ?? null,
                    'status' => 'registrado',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $this->applyPaymentToLoan($loan, $payment, $data);
                $payment->update(['schedule_after' => $this->scheduleSnapshot($loan->fresh()), 'new_loan_balance' => $loan->fresh()->current_balance]);
                $receipt = $this->createReceipt($payment, $loan);
                $payment->update(['receipt_id' => $receipt->id, 'receipt_number' => $receipt->receipt_number]);
                if ($payment->affects_cash) {
                    $this->createCashMovement($payment, $loan);
                }
                app(ShareCashMovementService::class)->recalculateBalances();

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($voucherPath) {
                Storage::disk('public')->delete($voucherPath);
            }

            throw $exception;
        }

        $this->creditHistory->recalculate($payment->member_id);

        return response()->json(['message' => 'Cobro registrado correctamente.', 'id' => $payment->id]);
    }

    public function show(LoanPayment $cobro)
    {
        return response()->json($this->paymentPayload($cobro));
    }

    public function edit(LoanPayment $cobro)
    {
        return response()->json($this->paymentPayload($cobro));
    }

    public function update(Request $request, LoanPayment $cobro)
    {
        if ($cobro->status === 'anulado') {
            return response()->json(['message' => 'No se puede editar un cobro anulado.'], 422);
        }

        $this->normalizeNullableRequestFields($request);
        $data = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string'],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'profit_treatment' => [
                Rule::requiredIf(fn () => $cobro->is_historical && $cobro->affects_profit),
                'nullable',
                Rule::in(['eligible', 'historical_closed', 'externally_distributed']),
            ],
        ], $this->messages());

        DB::transaction(function () use ($request, $cobro, $data) {
            if ($request->hasFile('voucher_path')) {
                if ($cobro->voucher_path) {
                    Storage::disk('public')->delete($cobro->voucher_path);
                }

                $data['voucher_path'] = $request->file('voucher_path')->store('loan-payments', 'public');
            }

            $data['updated_by'] = auth()->id();
            $cobro->update($data);
            $cobro->receipt?->update([
                'payment_reference' => $data['payment_reference'] ?? null,
                'voucher_path' => $data['voucher_path'] ?? $cobro->voucher_path,
                'observation' => $data['observation'] ?? null,
                'updated_by' => auth()->id(),
            ]);
            CashMovement::where('related_type', LoanPayment::class)->where('related_id', $cobro->id)->update([
                'reference' => $data['payment_reference'] ?? null,
                'voucher_path' => $data['voucher_path'] ?? $cobro->voucher_path,
                'updated_by' => auth()->id(),
            ]);
        });

        $this->creditHistory->recalculate($cobro->member_id);

        return response()->json(['message' => 'Cobro actualizado correctamente.']);
    }

    public function destroy(LoanPayment $cobro)
    {
        return $this->annul($cobro);
    }

    public function annul(LoanPayment $cobro)
    {
        if ($cobro->status === 'anulado') {
            return response()->json(['message' => 'El cobro ya se encuentra anulado.'], 422);
        }

        DB::transaction(function () use ($cobro) {
            $cobro->load(['loan', 'details.installment', 'receipt']);
            $loan = Loan::lockForUpdate()->findOrFail($cobro->loan_id);

            if (in_array($cobro->payment_type, ['adelanto_cuotas', 'abono_capital', 'liquidacion'], true) && $cobro->schedule_before) {
                $this->restoreSchedule($loan, $cobro->schedule_before);
            } else {

            foreach ($cobro->details as $detail) {
                if ($detail->installment) {
                    $installment = LoanInstallment::lockForUpdate()->find($detail->loan_installment_id);
                    $paid = max(0, (float) $installment->paid_amount - (float) $detail->amount_paid);
                    $capitalPaid = max(0, (float) $installment->capital_paid - (float) $detail->principal_paid);
                    $interestPaid = max(0, (float) $installment->interest_paid - (float) $detail->interest_paid);
                    $remaining = (float) $installment->installment_amount - $paid;
                    $latePaid = max(0, (float) $installment->late_fee_paid - (float) $detail->late_fee_paid);
                    $lateWaived = max(0, (float) $installment->late_fee_waived - (float) $detail->late_fee_waived);
                    $latePending = max(0, (float) $installment->late_fee_amount - $latePaid - $lateWaived);
                    $installment->update([
                        'paid_amount' => $paid,
                        'capital_paid' => $capitalPaid,
                        'interest_paid' => $interestPaid,
                        'remaining_amount' => $remaining,
                        'status' => $paid <= 0 ? 'pendiente' : ($remaining <= 0.01 ? 'pagado' : 'parcial'),
                        'paid_at' => $paid <= 0 ? null : $installment->paid_at,
                        'late_fee_paid' => $latePaid,
                        'late_fee_waived' => $lateWaived,
                        'late_fee_pending' => $latePending,
                        'late_fee_status' => $latePending > 0 ? 'pendiente' : ((float) $installment->late_fee_amount > 0 ? ($lateWaived > 0 ? 'exonerada' : 'pagada') : 'no_mora'),
                    ]);
                }
            }

            }

            $this->settlement->syncLoanBalance($loan);

            $cobro->update([
                'status' => 'anulado',
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $cobro->receipt?->update(['status' => 'anulado', 'updated_by' => auth()->id()]);

            CashMovement::where('related_type', LoanPayment::class)
                ->where('related_id', $cobro->id)
                ->update([
                    'status' => 'anulado',
                    'balance_before' => null,
                    'balance_after' => null,
                    'annulled_by' => auth()->id(),
                    'annulled_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

            app(ShareCashMovementService::class)->recalculateBalances();
        });

        $this->creditHistory->recalculate($cobro->member_id);

        return response()->json(['message' => 'Cobro anulado correctamente.']);
    }

    public function receipt(LoanPayment $cobro)
    {
        $cobro->load(['loan', 'member', 'details.installment', 'receipt', 'creator']);
        abort_unless($cobro->receipt, 404);

        return view('admin.loan-payments.receipt', ['payment' => $cobro, 'receipt' => $cobro->receipt]);
    }

    public function receiptPdf(LoanPayment $cobro)
    {
        $cobro->load('receipt');
        abort_unless($cobro->receipt, 404);

        return redirect()->route('admin.recibos.pdf', $cobro->receipt);
    }

    public function voucher(LoanPayment $cobro)
    {
        abort_unless($cobro->voucher_path && Storage::disk('public')->exists($cobro->voucher_path), 404);

        return Storage::disk('public')->download($cobro->voucher_path);
    }

    private function validatedData(Request $request): array
    {
        $historical = $request->boolean('is_historical');
        $data = $request->validate([
            'loan_id' => ['required', 'integer', Rule::exists('loans', 'id')],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_type' => ['required', Rule::in(['cuota', 'parcial', 'adelanto_cuotas', 'abono_capital', 'liquidacion'])],
            'payment_method' => ['required', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'cheque', 'otro'])],
            'payment_reference' => [
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['yape', 'plin', 'transferencia', 'cheque'], true)),
                'nullable',
                'string',
                'max:100',
            ],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'installment_ids' => ['nullable', 'array'],
            'installment_ids.*' => ['integer', Rule::exists('loan_installments', 'id')],
            'observation' => ['nullable', 'string'],
            'waive_late_fee' => ['nullable', 'boolean'],
            'late_fee_reason' => [Rule::requiredIf(fn () => $request->boolean('waive_late_fee')), 'nullable', 'string', 'max:500'],
            'is_historical' => ['nullable', 'boolean'],
            'affects_cash' => ['nullable', 'boolean'],
            'affects_profit' => ['nullable', 'boolean'],
            'profit_treatment' => ['nullable', Rule::in(['eligible', 'historical_closed', 'externally_distributed'])],
            'affects_credit_history' => ['nullable', 'boolean'],
            'late_fee_charged' => ['nullable', 'numeric', 'min:0'],
            'late_fee_exonerated' => ['nullable', 'numeric', 'min:0'],
            'late_fee_override_reason' => ['nullable', 'string', 'max:1000'],
        ], $this->messages());

        $data['is_historical'] = $historical;
        $data['affects_cash'] = $historical ? $request->boolean('affects_cash') : true;
        $data['affects_profit'] = $historical ? $request->boolean('affects_profit') : true;
        $data['profit_treatment'] = $historical && $data['affects_profit']
            ? ($data['profit_treatment'] ?? 'eligible')
            : 'eligible';
        $data['affects_credit_history'] = $historical ? $request->boolean('affects_credit_history') : true;

        if ($historical && empty($data['payment_date'])) {
            throw ValidationException::withMessages(['payment_date' => ['La fecha real de pago es obligatoria en modo histórico.']]);
        }

        if (in_array($data['payment_type'], ['cuota', 'parcial', 'adelanto_cuotas'], true) && empty($data['installment_ids'])) {
            throw ValidationException::withMessages(['installment_ids' => ['Seleccione al menos una cuota para registrar el cobro.']]);
        }

        return $data;
    }

    private function applyPaymentToLoan(Loan $loan, LoanPayment $payment, array $data): void
    {
        match ($payment->payment_type) {
            'cuota', 'parcial' => $this->applyInstallmentPayment($loan, $payment, $data),
            'adelanto_cuotas' => $this->applyAdvanceInstallments($loan, $payment, $data),
            'liquidacion' => $this->applyLiquidation($loan, $payment),
            'abono_capital' => $this->applyCapitalAmortization($loan, $payment),
        };
        $this->settlement->syncLoanBalance($loan);
    }

    private function applyInstallmentPayment(Loan $loan, LoanPayment $payment, array $data): void
    {
        $remainingPayment = (float) $payment->amount;
        $installments = LoanInstallment::where('loan_id', $loan->id)
            ->whereIn('id', $data['installment_ids'])
            ->whereIn('status', ['pendiente', 'parcial', 'vencido'])
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        if ($installments->isEmpty()) {
            throw ValidationException::withMessages(['installment_ids' => ['Seleccione al menos una cuota para registrar el cobro.']]);
        }

        $quotes = $installments->mapWithKeys(fn ($row) => [$row->id => $this->lateFees->persistQuote($row, $payment->payment_date)]);
        $feeDue = (float) $quotes->sum('pending');
        $historical = (bool) $payment->is_historical;
        $historicalCharged = round((float) ($data['late_fee_charged'] ?? $feeDue), 2);
        $historicalExonerated = round((float) ($data['late_fee_exonerated'] ?? 0), 2);
        $waive = ! empty($data['waive_late_fee']) || ($historical && $historicalExonerated > 0);
        if ($historical && abs(($historicalCharged + $historicalExonerated) - $feeDue) > 0.01 && empty($data['late_fee_override_reason'])) {
            throw ValidationException::withMessages(['late_fee_override_reason' => ['Indique el motivo cuando la mora cobrada más la exonerada difiere de la mora calculada.']]);
        }
        if ($historical && $historicalExonerated > 0 && empty($data['late_fee_override_reason'])) {
            throw ValidationException::withMessages(['late_fee_override_reason' => ['El motivo de exoneración histórica es obligatorio.']]);
        }
        if (! $historical && $waive && $quotes->contains(fn ($quote) => $quote['pending'] > 0 && ! $quote['setting']?->allow_waiver)) {
            throw ValidationException::withMessages(['waive_late_fee' => ['La configuración activa no permite exonerar mora.']]);
        }
        $feeToCollect = $historical ? $historicalCharged : ($waive ? 0 : $feeDue);
        $pending = (float) $installments->sum('remaining_amount') + $feeToCollect;

        if ($payment->payment_type === 'cuota' && $installments->contains(function ($row) {
            $capital = max(0, (float) $row->principal_amount - (float) $row->capital_paid);
            $interest = max(0, (float) $row->interest_amount - (float) $row->interest_paid - (float) $row->interest_exonerated);
            return (float) $row->remaining_amount > 0.009 && $capital + $interest <= 0.009;
        })) {
            throw ValidationException::withMessages(['installment_ids' => ['No se pudo calcular el detalle del cobro. Revise capital, interés y mora antes de guardar.']]);
        }

        if (! $historical && $waive && $feeDue > 0) {
            Gate::authorize('mora.exonerate');
        }

        if ($payment->payment_type === 'cuota' && abs($remainingPayment - $pending) > 0.01) {
            throw ValidationException::withMessages(['amount' => ['El pago normal debe coincidir con capital, interés y mora de las cuotas seleccionadas: S/ ' . number_format($pending, 2) . '.']]);
        }

        if ($remainingPayment - $pending > 0.01) {
            throw ValidationException::withMessages(['amount' => ['El monto no puede superar el saldo pendiente de la cuota.']]);
        }

        if ($payment->payment_type === 'parcial' && $installments->count() > 1) {
            throw ValidationException::withMessages(['installment_ids' => ['Para pago parcial seleccione una sola cuota.']]);
        }

        foreach ($installments as $installment) {
            if ($remainingPayment <= 0) {
                break;
            }

            $quote = $quotes[$installment->id];
            if ($historical) {
                $feePaid = min($remainingPayment, $historicalCharged, (float) $quote['pending']);
                $feeWaived = min(max(0, (float) $quote['pending'] - $feePaid), $historicalExonerated);
                $historicalCharged -= $feePaid;
                $historicalExonerated -= $feeWaived;
            } else {
                $feePaid = $waive ? 0 : min($remainingPayment, (float) $quote['pending']);
                $feeWaived = $waive ? (float) $quote['pending'] : 0;
            }
            $remainingPayment -= $feePaid;
            if ($feePaid > 0 || $feeWaived > 0) {
                $installment->update([
                    'late_fee_paid' => (float) $installment->late_fee_paid + $feePaid,
                    'late_fee_waived' => (float) $installment->late_fee_waived + $feeWaived,
                    'late_fee_pending' => max(0, (float) $quote['pending'] - $feePaid - $feeWaived),
                    'late_fee_status' => $feeWaived > 0 ? 'exonerada' : (((float) $quote['pending'] - $feePaid <= 0.009) ? 'pagada' : 'parcial'),
                ]);
            }
            $previous = (float) $installment->remaining_amount;
            $paid = min($previous, $remainingPayment);
            $newRemaining = max(0, $previous - $paid);
            $interestPending = max(0, (float) $installment->interest_amount - (float) $installment->interest_paid - (float) $installment->interest_exonerated);
            $principalPending = max(0, (float) $installment->principal_amount - (float) $installment->capital_paid);
            $interestPaid = min($paid, $interestPending);
            $principalPaid = min(max(0, $paid - $interestPaid), $principalPending);

            $installment->update([
                'paid_amount' => (float) $installment->paid_amount + $paid,
                'capital_paid' => (float) $installment->capital_paid + $principalPaid,
                'interest_paid' => (float) $installment->interest_paid + $interestPaid,
                'remaining_amount' => $newRemaining,
                'status' => $newRemaining <= 0.01 ? 'pagado' : 'parcial',
                'payment_type' => $payment->payment_type,
                'paid_at' => $newRemaining <= 0.01 ? $payment->payment_date : $installment->paid_at,
            ]);

            $detail = $this->createPaymentDetail($payment, $installment, $principalPaid, $interestPaid, $previous, $newRemaining);
            $detail->update(['late_fee_paid' => $feePaid, 'late_fee_waived' => $feeWaived, 'late_fee_days' => $quote['days']]);
            $remainingPayment -= $paid;
        }

        $payment->update([
            'capital_amount' => $payment->details()->sum('principal_paid'), 'interest_amount' => $payment->details()->sum('interest_paid'),
            'late_fee_amount' => $feeDue, 'late_fee_paid' => $payment->details()->sum('late_fee_paid'), 'late_fee_waived' => $payment->details()->sum('late_fee_waived'),
            'late_fee_reason' => $waive ? ($historical ? ($data['late_fee_override_reason'] ?? null) : ($data['late_fee_reason'] ?? null)) : null, 'late_fee_days' => $quotes->max('days') ?? 0,
            'late_fee_setting_id' => $quotes->first()['setting']?->id, 'late_fee_waived_by' => $waive ? auth()->id() : null, 'late_fee_waived_at' => $waive ? now() : null,
            'late_fee_calculated' => $feeDue,
            'late_fee_charged' => $payment->details()->sum('late_fee_paid'),
            'late_fee_exonerated' => $payment->details()->sum('late_fee_waived'),
            'late_fee_override_reason' => $historical ? ($data['late_fee_override_reason'] ?? null) : null,
        ]);

        $registeredTotal = round((float) $payment->details()->sum('principal_paid') + (float) $payment->details()->sum('interest_paid') + (float) $payment->details()->sum('late_fee_paid'), 2);
        if (abs((float) $payment->amount - $registeredTotal) > 0.01) {
            throw ValidationException::withMessages(['amount' => ['El total pagado no coincide con la suma de capital, interés y mora.']]);
        }
    }

    private function applyAdvanceInstallments(Loan $loan, LoanPayment $payment, array $data): void
    {
        $paymentDate = $payment->payment_date;
        if ($this->settlement->hasOverdueDebt($loan, $paymentDate)) {
            throw ValidationException::withMessages(['installment_ids' => ['Primero debe regularizar las cuotas vencidas antes de adelantar cuotas futuras.']]);
        }

        $installments = LoanInstallment::where('loan_id', $loan->id)->whereIn('id', $data['installment_ids'])->whereDate('due_date', '>', $paymentDate)->whereIn('status', ['pendiente', 'parcial'])->orderBy('installment_number')->lockForUpdate()->get();
        if ($installments->count() !== count(array_unique($data['installment_ids']))) {
            throw ValidationException::withMessages(['installment_ids' => ['Seleccione solamente cuotas futuras pendientes.']]);
        }

        $capital = round((float) $installments->sum(fn ($row) => max(0, (float) $row->principal_amount - (float) $row->capital_paid)), 2);
        $exonerated = round((float) $installments->sum(fn ($row) => max(0, (float) $row->interest_amount - (float) $row->interest_paid)), 2);
        if (abs((float) $payment->amount - $capital) > 0.01) {
            throw ValidationException::withMessages(['amount' => ['El adelanto debe ser exactamente el capital de las cuotas seleccionadas: S/ ' . number_format($capital, 2) . '.']]);
        }

        foreach ($installments as $row) {
            $principal = max(0, (float) $row->principal_amount - (float) $row->capital_paid);
            $interest = max(0, (float) $row->interest_amount - (float) $row->interest_paid);
            $row->update(['capital_paid' => (float) $row->capital_paid + $principal, 'interest_exonerated' => (float) $row->interest_exonerated + $interest, 'paid_amount' => (float) $row->paid_amount + $principal, 'remaining_amount' => 0, 'status' => 'adelantado', 'payment_type' => 'adelanto_cuotas', 'paid_at' => $paymentDate]);
            $this->createPaymentDetail($payment, $row, $principal, 0, (float) $row->getOriginal('remaining_amount'), 0, 'Cuota adelantada; interes futuro exonerado S/ ' . number_format($interest, 2));
        }
        $payment->update(['capital_amount' => $capital, 'interest_amount' => 0, 'interest_exonerated_amount' => $exonerated, 'installments_advanced_count' => $installments->count()]);
        $this->settlement->recalculateFutureSchedule($loan, $paymentDate);
    }

    private function applyCapitalAmortization(Loan $loan, LoanPayment $payment): void
    {
        $debt = $this->settlement->debt($loan, $payment->payment_date);
        if ((float) $payment->amount - $debt['capital'] > 0.01) {
            throw ValidationException::withMessages(['amount' => ['La amortizacion no puede superar el capital pendiente de S/ ' . number_format($debt['capital'], 2) . '.']]);
        }

        $remaining = (float) $payment->amount;
        $future = $loan->installments()->whereDate('due_date', '>', $payment->payment_date)->whereIn('status', ['pendiente', 'parcial'])->orderByDesc('installment_number')->lockForUpdate()->get();
        foreach ($future as $row) {
            if ($remaining <= 0.009) break;
            $available = max(0, (float) $row->principal_amount - (float) $row->capital_paid);
            $applied = min($remaining, $available);
            $row->update(['principal_amount' => (float) $row->principal_amount - $applied, 'remaining_amount' => max(0, (float) $row->remaining_amount - $applied), 'closing_balance' => max(0, (float) $row->closing_balance - $applied), 'payment_type' => 'abono_capital']);
            $remaining -= $applied;
        }
        if ($remaining > 0.01) throw ValidationException::withMessages(['amount' => ['Primero debe regularizar capital vencido antes de amortizar cuotas futuras.']]);
        $this->createPaymentDetail($payment, null, (float) $payment->amount, 0, $debt['capital'], max(0, $debt['capital'] - (float) $payment->amount), 'Amortizacion directa a capital');
        $payment->update(['capital_amount' => $payment->amount, 'interest_amount' => 0]);
        $this->settlement->recalculateFutureSchedule($loan, $payment->payment_date);
    }

    private function applyLiquidation(Loan $loan, LoanPayment $payment): void
    {
        $debt = $this->settlement->debt($loan, $payment->payment_date);
        if (abs((float) $payment->amount - $debt['total']) > 0.01) {
            throw ValidationException::withMessages(['amount' => ['La liquidacion debe ser por S/ ' . number_format($debt['total'], 2) . ': capital pendiente mas intereses vencidos.']]);
        }
        $pendingInstallments = LoanInstallment::where('loan_id', $loan->id)
            ->whereIn('status', ['pendiente', 'parcial', 'vencido'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($pendingInstallments as $installment) {
            $previous = (float) $installment->remaining_amount;
            $principalPaid = max(0, (float) $installment->principal_amount - (float) $installment->capital_paid);
            $quote = $this->lateFees->persistQuote($installment, $payment->payment_date);
            $lateFeePaid = (float) $quote['pending'];
            $interestPaid = $installment->due_date && $installment->due_date->lte($payment->payment_date) ? max(0, (float) $installment->interest_amount - (float) $installment->interest_paid) : 0;
            $interestExonerated = max(0, (float) $installment->interest_amount - (float) $installment->interest_paid - $interestPaid);
            $installment->update([
                'paid_amount' => (float) $installment->paid_amount + $principalPaid + $interestPaid,
                'capital_paid' => (float) $installment->capital_paid + $principalPaid,
                'interest_paid' => (float) $installment->interest_paid + $interestPaid,
                'interest_exonerated' => (float) $installment->interest_exonerated + $interestExonerated,
                'remaining_amount' => 0,
                'status' => 'liquidado',
                'payment_type' => 'liquidacion',
                'paid_at' => $payment->payment_date,
                'late_fee_paid' => (float) $installment->late_fee_paid + $lateFeePaid,
                'late_fee_pending' => 0,
                'late_fee_status' => $lateFeePaid > 0 ? 'pagada' : $installment->late_fee_status,
            ]);
            $detail = $this->createPaymentDetail($payment, $installment, $principalPaid, $interestPaid, $previous, 0, 'Liquidacion de prestamo');
            $detail->update(['late_fee_paid' => $lateFeePaid, 'late_fee_days' => $quote['days']]);
        }
        $payment->update(['capital_amount' => $debt['capital'], 'interest_amount' => $debt['overdue_interest'], 'late_fee_amount' => $debt['late_fee'], 'late_fee_paid' => $debt['late_fee'], 'late_fee_days' => $payment->details()->max('late_fee_days') ?? 0, 'interest_exonerated_amount' => $debt['future_interest_exonerated']]);
    }

    private function createPaymentDetail(LoanPayment $payment, ?LoanInstallment $installment, float $principal, float $interest, float $previous, float $new, ?string $observation = null): \App\Models\LoanPaymentDetail
    {
        return $payment->details()->create([
            'loan_installment_id' => $installment?->id,
            'principal_paid' => $principal,
            'interest_paid' => $interest,
            'amount_paid' => $principal + $interest > 0 ? $principal + $interest : (float) $payment->amount,
            'previous_balance' => $previous,
            'new_balance' => $new,
            'observation' => $observation,
        ]);
    }

    private function createReceipt(LoanPayment $payment, Loan $loan): Receipt
    {
        return Receipt::create([
            'receipt_number' => $this->generateNextReceiptNumber(),
            'receipt_date' => $payment->payment_date,
            'member_id' => $payment->member_id,
            'type' => $this->receiptType($payment->payment_type),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'payment_reference' => $payment->payment_reference,
            'voucher_path' => $payment->voucher_path,
            'related_type' => LoanPayment::class,
            'related_id' => $payment->id,
            'observation' => $payment->observation,
            'status' => 'registrado',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function createCashMovement(LoanPayment $payment, Loan $loan): void
    {
        $exists = CashMovement::where('related_type', LoanPayment::class)->where('related_id', $payment->id)->exists();

        if ($exists) {
            return;
        }

        CashMovement::create([
            'movement_number' => CashMovement::nextCode(),
            'movement_date' => $payment->payment_date,
            'type' => 'ingreso',
            'category' => $this->cashCategory($payment->payment_type),
            'concept' => $this->cashConcept($payment, $loan),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference' => $payment->payment_reference,
            'voucher_path' => $payment->voucher_path,
            'related_type' => LoanPayment::class,
            'related_id' => $payment->id,
            'status' => 'registrado',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function ensureLoanCanBePaid(Loan $loan): void
    {
        if ($loan->status === 'pagado') {
            throw ValidationException::withMessages(['loan_id' => ['El prestamo ya se encuentra pagado.']]);
        }

        if ($loan->status !== 'desembolsado') {
            throw ValidationException::withMessages(['loan_id' => ['Solo se pueden registrar cobros de prestamos desembolsados.']]);
        }

        if ((float) $loan->current_balance <= 0) {
            throw ValidationException::withMessages(['loan_id' => ['El prestamo seleccionado no tiene saldo pendiente.']]);
        }
    }

    private function paymentPayload(LoanPayment $payment): array
    {
        $payment->load(['loan', 'member', 'details.installment', 'receipt', 'creator', 'annuller']);

        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'receipt_number' => $payment->receipt_number,
            'payment_date' => optional($payment->payment_date)->format('Y-m-d'),
            'payment_date_formatted' => optional($payment->payment_date)->format('d/m/Y'),
            'registered_at_formatted' => optional($payment->created_at)->format('d/m/Y H:i'),
            'is_historical' => (bool) $payment->is_historical,
            'historical_label' => $payment->is_historical ? 'Histórico' : 'Normal',
            'affects_cash' => (bool) $payment->affects_cash,
            'affects_profit' => (bool) $payment->affects_profit,
            'profit_treatment' => $payment->profit_treatment,
            'profit_treatment_label' => match ($payment->profit_treatment) {
                'historical_closed' => 'Período cerrado históricamente',
                'externally_distributed' => 'Ya distribuido fuera del sistema',
                default => 'Pendiente de cálculo',
            },
            'affects_credit_history' => (bool) $payment->affects_credit_history,
            'member_id' => $payment->member_id,
            'member_name' => $payment->member?->full_name,
            'member_dni' => $payment->member?->dni,
            'member_code' => $payment->member?->code,
            'loan_id' => $payment->loan_id,
            'loan_number' => $payment->loan?->loan_number,
            'payment_type' => $payment->payment_type,
            'payment_type_label' => $this->paymentTypeLabel($payment->payment_type),
            'payment_method' => $payment->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($payment->payment_method),
            'payment_reference' => $payment->payment_reference,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'amount_formatted' => 'S/ ' . number_format((float) $payment->amount, 2),
            'previous_loan_balance_formatted' => 'S/ ' . number_format((float) $payment->previous_loan_balance, 2),
            'new_loan_balance_formatted' => 'S/ ' . number_format((float) $payment->new_loan_balance, 2),
            'capital_amount_formatted' => 'S/ ' . number_format((float) $payment->capital_amount, 2),
            'interest_amount_formatted' => 'S/ ' . number_format((float) $payment->interest_amount, 2),
            'late_fee_paid_formatted' => 'S/ ' . number_format((float) $payment->late_fee_paid, 2),
            'late_fee_waived_formatted' => 'S/ ' . number_format((float) $payment->late_fee_waived, 2),
            'late_fee_calculated_formatted' => 'S/ ' . number_format((float) $payment->late_fee_calculated, 2),
            'late_fee_override_reason' => $payment->late_fee_override_reason,
            'interest_exonerated_amount_formatted' => 'S/ ' . number_format((float) $payment->interest_exonerated_amount, 2),
            'installments_advanced_count' => $payment->installments_advanced_count,
            'voucher_url' => $payment->voucher_path ? route('admin.cobros.voucher', $payment) : null,
            'receipt_url' => $payment->receipt ? route('admin.cobros.receipt', $payment) : null,
            'receipt_pdf_url' => $payment->receipt ? route('admin.cobros.receipt.pdf', $payment) : null,
            'status' => $payment->status,
            'status_label' => $payment->status === 'anulado' ? 'Anulado' : 'Registrado',
            'observation' => $payment->observation,
            'created_by_name' => $payment->creator?->name,
            'created_at' => optional($payment->created_at)->format('d/m/Y H:i'),
            'annulled_by_name' => $payment->annuller?->name,
            'annulled_at' => optional($payment->annulled_at)->format('d/m/Y H:i'),
            'details' => $payment->details->map(fn ($detail) => [
                'installment_number' => $detail->installment?->installment_number ?? '-',
                'due_date' => optional($detail->installment?->due_date)->format('d/m/Y') ?? '-',
                'principal_paid' => 'S/ ' . number_format((float) $detail->principal_paid, 2),
                'interest_paid' => 'S/ ' . number_format((float) $detail->interest_paid, 2),
                'late_fee_days' => (int) $detail->late_fee_days,
                'late_fee_paid' => 'S/ ' . number_format((float) $detail->late_fee_paid, 2),
                'late_fee_waived' => 'S/ ' . number_format((float) $detail->late_fee_waived, 2),
                'amount_paid' => 'S/ ' . number_format((float) $detail->amount_paid + (float) $detail->late_fee_paid, 2),
                'status' => ucfirst($detail->installment?->status ?? '-'),
                'previous_balance' => 'S/ ' . number_format((float) $detail->previous_balance, 2),
                'new_balance' => 'S/ ' . number_format((float) $detail->new_balance, 2),
                'observation' => $detail->observation,
            ])->values(),
        ];
    }

    private function loanOptionPayload(Loan $loan): array
    {
        $debt = $this->settlement->debt($loan, now());
        return [
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'member_id' => $loan->member_id,
            'member_name' => $loan->member?->full_name,
            'approved_amount' => 'S/ ' . number_format((float) $loan->approved_amount, 2),
            'current_balance' => number_format((float) $loan->current_balance, 2, '.', ''),
            'current_balance_formatted' => 'S/ ' . number_format((float) $loan->current_balance, 2),
            'status' => $loan->status,
            'status_label' => ucfirst($loan->status),
            'total_paid_formatted' => 'S/ ' . number_format((float) $loan->payments()->where('status', 'registrado')->sum('amount'), 2),
            'capital_pending' => number_format($debt['capital'], 2, '.', ''),
            'overdue_interest' => number_format($debt['overdue_interest'], 2, '.', ''),
            'liquidation_amount' => number_format($debt['total'], 2, '.', ''),
            'future_interest_exonerated' => number_format($debt['future_interest_exonerated'], 2, '.', ''),
            'has_overdue_debt' => $this->settlement->hasOverdueDebt($loan, now()),
        ];
    }

    private function installmentPayload(LoanInstallment $installment, $date = null): array
    {
        $quote = $this->lateFees->quote($installment, $date ?: today());
        return [
            'id' => $installment->id,
            'installment_number' => $installment->installment_number,
            'due_date' => optional($installment->due_date)->format('d/m/Y'),
            'due_date_iso' => optional($installment->due_date)->format('Y-m-d'),
            'principal_amount' => 'S/ ' . number_format((float) $installment->principal_amount, 2),
            'principal_pending' => number_format(max(0, (float) $installment->principal_amount - (float) $installment->capital_paid), 2, '.', ''),
            'interest_amount' => 'S/ ' . number_format((float) $installment->interest_amount, 2),
            'interest_pending' => number_format(max(0, (float) $installment->interest_amount - (float) $installment->interest_paid - (float) $installment->interest_exonerated), 2, '.', ''),
            'installment_amount' => 'S/ ' . number_format((float) $installment->installment_amount, 2),
            'paid_amount' => 'S/ ' . number_format((float) $installment->paid_amount, 2),
            'remaining_amount' => number_format((float) $installment->remaining_amount, 2, '.', ''),
            'remaining_amount_formatted' => 'S/ ' . number_format((float) $installment->remaining_amount, 2),
            'status' => $installment->status,
            'status_label' => ucfirst($installment->status),
            'is_future' => ! $installment->due_date || $installment->due_date->gt(today()),
            'late_days' => $quote['days'],
            'grace_days' => (int) ($quote['setting']?->grace_days ?? 0),
            'late_fee' => number_format($quote['pending'], 2, '.', ''),
            'late_fee_formatted' => 'S/ ' . number_format($quote['pending'], 2),
            'allow_waiver' => (bool) $quote['setting']?->allow_waiver,
            'total_due' => number_format((float) $installment->remaining_amount + $quote['pending'], 2, '.', ''),
        ];
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $normalized = [];

        foreach (['payment_reference', 'observation'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function generateNextCode(): string
    {
        return LoanPayment::nextCode();
    }

    private function scheduleSnapshot(Loan $loan): array
    {
        return $loan->installments()->orderBy('installment_number')->get()->map(fn ($row) => collect($row->only(['id', 'opening_balance', 'principal_amount', 'interest_amount', 'installment_amount', 'paid_amount', 'capital_paid', 'interest_paid', 'interest_exonerated', 'remaining_amount', 'closing_balance', 'status', 'payment_type', 'paid_at', 'schedule_version', 'recalculated_at', 'late_days', 'late_fee_amount', 'late_fee_paid', 'late_fee_waived', 'late_fee_pending', 'late_fee_calculated_at', 'late_fee_status', 'late_fee_setting_id']))->all())->all();
    }

    private function restoreSchedule(Loan $loan, array $snapshot): void
    {
        foreach ($snapshot as $values) {
            $id = $values['id'];
            unset($values['id']);
            LoanInstallment::where('loan_id', $loan->id)->whereKey($id)->update($values);
        }
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()->whereNotNull('receipt_number')->where('receipt_number', 'like', 'REC-%')->orderByDesc('id')->lockForUpdate()->value('receipt_number');
        $lastNumber = $lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches) ? (int) $matches[1] : 0;

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function portion(float $amount, float $part, float $total): float
    {
        return $total > 0 ? round($amount * ($part / $total), 2) : 0;
    }

    private function receiptType(string $type): string
    {
        return match ($type) {
            'parcial' => 'pago_parcial',
            'adelanto_cuotas' => 'adelanto_cuotas',
            'abono_capital' => 'abono_capital',
            'liquidacion' => 'liquidacion_prestamo',
            default => 'cobro_prestamo',
        };
    }

    private function cashCategory(string $type): string
    {
        return match ($type) {
            'adelanto_cuotas' => 'cobro_prestamo',
            'abono_capital' => 'abono_capital',
            'liquidacion' => 'liquidacion_prestamo',
            default => 'cobro_prestamo',
        };
    }

    private function cashConcept(LoanPayment $payment, Loan $loan): string
    {
        $member = $loan->member?->full_name ?? '';

        if ($payment->payment_type === 'cuota') {
            $installments = $payment->details()->with('installment')->get()->pluck('installment.installment_number')->filter()->implode(', ');
            return 'Cobro ' . $loan->loan_number . ' / Cuota ' . ($installments ?: '-')
                . ': Capital S/' . number_format((float) $payment->capital_amount, 2)
                . ', Interés S/' . number_format((float) $payment->interest_amount, 2)
                . ', Mora cobrada S/' . number_format((float) $payment->late_fee_paid, 2)
                . ', Mora exonerada S/' . number_format((float) $payment->late_fee_waived, 2);
        }

        return match ($payment->payment_type) {
            'adelanto_cuotas' => 'Adelanto de cuotas del prestamo ' . $loan->loan_number . ' del socio ' . $member,
            'abono_capital' => 'Abono a capital del prestamo ' . $loan->loan_number . ' del socio ' . $member,
            'liquidacion' => 'Liquidacion del prestamo ' . $loan->loan_number . ' del socio ' . $member,
            default => 'Cobro de prestamo ' . $loan->loan_number . ' del socio ' . $member,
        };
    }

    private function paymentTypeLabel(?string $type): string
    {
        return match ($type) {
            'cuota' => 'Cuota',
            'parcial' => 'Pago parcial',
            'adelanto_cuotas' => 'Adelanto de cuotas',
            'abono_capital' => 'Abono a capital',
            'liquidacion' => 'Liquidacion',
            default => '-',
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'efectivo' => 'Efectivo',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'transferencia' => 'Transferencia',
            'cheque' => 'Cheque',
            'otro' => 'Otro',
            default => '-',
        };
    }

    private function statusBadge(?string $status): string
    {
        $class = $status === 'anulado' ? 'danger' : 'success';

        return '<span class="badge badge-' . $class . '">' . e($status === 'anulado' ? 'Anulado' : 'Registrado') . '</span>';
    }

    private function messages(): array
    {
        return [
            'loan_id.required' => 'Seleccione un prestamo valido.',
            'loan_id.exists' => 'Seleccione un prestamo valido.',
            'payment_date.required' => 'La fecha de pago es obligatoria.',
            'payment_date.date' => 'La fecha de pago debe ser valida.',
            'payment_type.required' => 'Seleccione un tipo de pago valido.',
            'payment_type.in' => 'Seleccione un tipo de pago valido.',
            'amount.required' => 'El monto pagado es obligatorio.',
            'amount.numeric' => 'El monto pagado debe ser un numero valido.',
            'amount.min' => 'El monto pagado debe ser mayor a cero.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'payment_reference.required' => 'La referencia de pago es obligatoria para este metodo de pago.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
            'installment_ids.array' => 'Seleccione cuotas validas.',
            'installment_ids.*.exists' => 'Seleccione cuotas validas.',
        ];
    }
}
