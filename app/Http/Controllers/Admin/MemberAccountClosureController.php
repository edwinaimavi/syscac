<?php

namespace App\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanSimulation;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberAccountClosureDetail;
use App\Models\MemberShare;
use App\Models\ProfitDistributionDetail;
use App\Models\Receipt;
use App\Services\ShareCashMovementService;
use App\Services\LoanSettlementService;
use App\Services\CreditHistoryService;
use App\Services\RetirementUtilityService;
use App\Services\ProfitAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class MemberAccountClosureController extends Controller
{
    private const PAYMENT_METHODS = ['efectivo', 'yape', 'plin', 'transferencia', 'cheque', 'otro'];

    public function __construct(private readonly LoanSettlementService $settlement, private readonly CreditHistoryService $creditHistory, private readonly RetirementUtilityService $retirementUtility, private readonly ProfitAvailabilityService $profitAvailability)
    {
        $this->middleware('can:retiros.index')->only(['index', 'list', 'summary', 'nextCode', 'members']);
        $this->middleware('can:retiros.calculate')->only(['calculate']);
        $this->middleware('can:retiros.create')->only(['store']);
        $this->middleware('can:retiros.edit')->only(['edit', 'update']);
        $this->middleware('can:retiros.show')->only(['show']);
        $this->middleware('can:retiros.close')->only(['close']);
        $this->middleware('can:retiros.anular')->only(['annul', 'destroy']);
        $this->middleware('can:retiros.receipt')->only(['receipt']);
        $this->middleware('can:retiros.receipt_pdf')->only(['receiptPdf']);
        $this->middleware('can:retiros.voucher')->only(['voucher', 'voucherView']);
        $this->middleware('can:retiros.report')->only(['report']);
    }

    public function index()
    {
        return view('admin.member-account-closures.index', [
            'nextCode' => MemberAccountClosure::nextCode(),
            'members' => $this->membersForSelect(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function list(Request $request)
    {
        $closures = MemberAccountClosure::with('member')
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('settlement_type'), fn ($query) => $query->where('settlement_type', $request->input('settlement_type')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('closure_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('closure_date', '<=', $request->input('date_to')))
            ->orderByDesc('closure_date')
            ->orderByDesc('id');

        return DataTables::of($closures)
            ->addIndexColumn()
            ->editColumn('closure_date', fn (MemberAccountClosure $closure) => optional($closure->closure_date)->format('d/m/Y') ?? '-')
            ->addColumn('member_name', fn (MemberAccountClosure $closure) => $closure->member?->full_name ?? '-')
            ->addColumn('member_dni', fn (MemberAccountClosure $closure) => $closure->member?->dni ?? '-')
            ->editColumn('total_in_favor', fn (MemberAccountClosure $closure) => $this->money($closure->total_in_favor))
            ->editColumn('total_against', fn (MemberAccountClosure $closure) => $this->money($closure->total_against))
            ->editColumn('final_balance', fn (MemberAccountClosure $closure) => $this->signedMoney($closure->final_balance))
            ->editColumn('status', fn (MemberAccountClosure $closure) => $this->statusBadge($closure))
            ->addColumn('acciones', fn (MemberAccountClosure $closure) => view('admin.member-account-closures.partials.acciones', compact('closure'))->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'retired_members' => MemberAccountClosure::where('status', 'cerrado')->distinct('member_id')->count('member_id'),
            'closures' => MemberAccountClosure::where('status', '!=', 'anulado')->count(),
            'returned_balance' => number_format((float) MemberAccountClosure::where('status', 'cerrado')->where('final_balance', '>', 0)->sum('final_balance'), 2),
            'pending_collect' => number_format((float) abs(MemberAccountClosure::whereIn('status', ['calculado', 'pendiente_regularizacion'])->where('final_balance', '<', 0)->sum('final_balance')), 2),
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => MemberAccountClosure::nextCode()]);
    }

    public function members()
    {
        return response()->json(['members' => $this->membersForSelect()]);
    }

    public function calculate(Request $request)
    {
        $data = $this->validatedCalculationData($request);
        $member = $this->availableMemberOrFail((int) $data['member_id']);

        return response()->json($this->calculationPayload($member, $data['retirement_date'], $data['utility_mode']));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $member = $this->availableMemberOrFail((int) $data['member_id']);
        $calculation = $this->calculationPayload($member, $data['retirement_date'], $data['utility_mode']);

        $closure = DB::transaction(function () use ($data, $member, $calculation) {
            $closure = MemberAccountClosure::create(array_merge(
                $this->closureAmounts($calculation),
                [
                    'code' => MemberAccountClosure::nextCode(),
                    'member_id' => $member->id,
                    'closure_date' => $data['closure_date'],
                    'retirement_date' => $data['retirement_date'],
                    'reason' => $data['reason'],
                    'observation' => $data['observation'] ?? null,
                    'status' => $this->calculationStatus($calculation),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            ));

            $this->syncDetails($closure, $calculation['details']);

            return $closure;
        });

        $this->creditHistory->recalculate($member);

        return response()->json(['message' => 'Calculo de cierre guardado correctamente.', 'id' => $closure->id]);
    }

    public function show(MemberAccountClosure $retiros_socio)
    {
        return response()->json($this->closurePayload($retiros_socio));
    }

    public function edit(MemberAccountClosure $retiros_socio)
    {
        if (! in_array($retiros_socio->status, ['calculado', 'pendiente_regularizacion'], true)) {
            return response()->json(['message' => $retiros_socio->status === 'cerrado' ? $this->confirmedClosureImmutableMessage() : 'Este cierre no puede modificarse.'], 422);
        }
        return response()->json($this->closurePayload($retiros_socio));
    }

    public function update(Request $request, MemberAccountClosure $retiros_socio)
    {
        if (! in_array($retiros_socio->status, ['calculado', 'pendiente_regularizacion'], true)) {
            return response()->json(['message' => $retiros_socio->status === 'cerrado' ? $this->confirmedClosureImmutableMessage() : 'Este cierre no puede modificarse.'], 422);
        }

        if ($request->filled('member_id') && $request->integer('member_id') !== $retiros_socio->member_id) {
            throw ValidationException::withMessages(['member_id' => ['El socio de un cierre existente no puede modificarse.']]);
        }

        $data = $this->validatedUpdateData($request);
        $member = $retiros_socio->member()->firstOrFail();
        $calculation = $this->calculationPayload($member, $data['retirement_date'], $data['utility_mode']);

        DB::transaction(function () use ($retiros_socio, $data, $calculation) {
            $retiros_socio->update(array_merge(
                $this->closureAmounts($calculation),
                [
                    'closure_date' => $data['closure_date'],
                    'retirement_date' => $data['retirement_date'],
                    'reason' => $data['reason'],
                    'observation' => $data['observation'] ?? null,
                    'status' => $this->calculationStatus($calculation),
                    'updated_by' => auth()->id(),
                ]
            ));

            $this->syncDetails($retiros_socio, $calculation['details']);
        });

        $this->creditHistory->recalculate($retiros_socio->member_id);

        return response()->json(['message' => 'Calculo de cierre actualizado correctamente.']);
    }

    public function close(Request $request, MemberAccountClosure $retiros_socio)
    {
        if (! in_array($retiros_socio->status, ['calculado', 'pendiente_regularizacion'], true)) {
            return response()->json(['message' => $retiros_socio->status === 'cerrado' ? $this->confirmedClosureImmutableMessage() : 'Este cierre no puede confirmarse.'], 422);
        }

        $retiros_socio->load('member');
        if (! $retiros_socio->member || $retiros_socio->member->status !== 'vigente') {
            return response()->json(['message' => 'El socio seleccionado ya se encuentra retirado.'], 422);
        }

        $calculation = $this->calculationPayload($retiros_socio->member, $retiros_socio->retirement_date->format('Y-m-d'), $retiros_socio->utility_mode);
        $this->ensureCalculationIsCurrent($retiros_socio, $calculation);

        if ($this->requiresRegularization(
            (float) $calculation['summary']['final_balance'],
            (float) $calculation['summary']['total_in_favor'],
            (float) $calculation['summary']['total_against']
        )) {
            throw ValidationException::withMessages(['member_id' => ['No se puede confirmar el retiro porque el socio mantiene saldo pendiente en contra.']]);
        }

        if ((float) $calculation['summary']['total_contributions'] + 0.009 < (float) $calculation['summary']['pending_loans_amount']) {
            throw ValidationException::withMessages(['member_id' => ['No se puede retirar al socio porque sus acciones no cubren la deuda pendiente. Debe regularizar antes de cerrar su cuenta.']]);
        }

        $data = $this->validatedCloseData($request, (float) $calculation['summary']['final_balance']);

        if ($retiros_socio->utility_mode === 'provisional' && (float) $calculation['summary']['utility_paid_now'] > 0) {
            $this->profitAvailability->validateAmount((float) $calculation['summary']['utility_paid_now']);
        }

        DB::transaction(function () use ($request, $retiros_socio, $data, $calculation) {
            $this->storeVoucher($request, $data, $retiros_socio);

            $retiros_socio->update(array_merge(
                $this->closureAmounts($calculation),
                [
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'voucher_path' => $data['voucher_path'] ?? $retiros_socio->voucher_path,
                    'status' => 'cerrado',
                    'utility_status' => (float) $calculation['summary']['utility_paid_now'] > 0 ? 'liquidada' : $calculation['summary']['utility_status'],
                    'closed_at' => now(),
                    'closed_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            ));

            $loans = Loan::where('member_id', $retiros_socio->member_id)->whereIn('status', ['desembolsado', 'refinanciado'])->lockForUpdate()->get();
            $retiros_socio->update(['loan_schedule_before' => $this->loanScheduleSnapshot($loans)]);
            foreach ($loans as $loan) {
                $this->settlement->liquidateByCompensation($loan, $retiros_socio->closure_date);
            }

            $retiros_socio->member->update([
                'status' => 'retirado',
                'retirement_date' => $retiros_socio->retirement_date,
                'updated_by' => auth()->id(),
            ]);

            LoanSimulation::where('member_id', $retiros_socio->member_id)
                ->where('status', 'simulada')
                ->update([
                    'status' => 'sin_efecto',
                    'effect_reason' => 'Socio retirado / cierre de cuenta confirmado.',
                    'effected_by' => auth()->id(),
                    'effected_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

            $movement = $this->syncCashMovement($retiros_socio->fresh(['member']));
            if ($movement && (float) $retiros_socio->final_balance > 0) {
                $receipt = $this->createOrUpdateReceipt($retiros_socio->fresh(['member']), $movement);
                $retiros_socio->update(['receipt_id' => $receipt->id]);
            }
        });

        $this->creditHistory->recalculate($retiros_socio->member_id);

        return response()->json(['message' => 'Cuenta del socio cerrada correctamente.']);
    }

    public function destroy(MemberAccountClosure $retiros_socio)
    {
        return $this->annul(request(), $retiros_socio);
    }

    public function annul(Request $request, MemberAccountClosure $retiros_socio)
    {
        if ($retiros_socio->status === 'cerrado') {
            return response()->json(['message' => $this->confirmedClosureImmutableMessage()], 422);
        }
        if ($retiros_socio->status === 'anulado') {
            return response()->json(['message' => 'El cierre ya se encuentra anulado.'], 422);
        }

        $data = $request->validate(['annulment_reason' => ['required', 'string', 'max:1000']], [
            'annulment_reason.required' => 'Indique el motivo de la anulacion.',
        ]);

        DB::transaction(function () use ($retiros_socio, $data) {
            $retiros_socio->load(['member', 'cashMovement', 'receipt']);

            if ($retiros_socio->loan_schedule_before) {
                $this->restoreLoanSchedules($retiros_socio->loan_schedule_before);
            }

            if ($retiros_socio->cashMovement && $retiros_socio->cashMovement->status === 'registrado') {
                $retiros_socio->cashMovement->update([
                    'status' => 'anulado',
                    'balance_before' => null,
                    'balance_after' => null,
                    'annulled_by' => auth()->id(),
                    'annulled_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            }

            if ($retiros_socio->receipt) {
                $retiros_socio->receipt->update(['status' => 'anulado', 'updated_by' => auth()->id()]);
            }

            $retiros_socio->update([
                'status' => 'anulado',
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
                'annulment_reason' => $data['annulment_reason'],
                'updated_by' => auth()->id(),
            ]);

            if ($retiros_socio->member && ! MemberAccountClosure::where('member_id', $retiros_socio->member_id)->where('id', '!=', $retiros_socio->id)->where('status', 'cerrado')->exists()) {
                $retiros_socio->member->update([
                    'status' => 'vigente',
                    'retirement_date' => null,
                    'updated_by' => auth()->id(),
                ]);
            }

            app(ShareCashMovementService::class)->recalculateBalances();
        });

        $this->creditHistory->recalculate($retiros_socio->member_id);

        return response()->json(['message' => 'Cierre de cuenta anulado correctamente.']);
    }

    private function confirmedClosureImmutableMessage(): string
    {
        return 'Este cierre ya fue confirmado y no puede modificarse.';
    }

    public function receipt(MemberAccountClosure $retiros_socio)
    {
        $receipt = $retiros_socio->receipt ?: Receipt::where('related_type', MemberAccountClosure::class)->where('related_id', $retiros_socio->id)->firstOrFail();

        return redirect()->route('admin.recibos.print', $receipt);
    }

    public function receiptPdf(MemberAccountClosure $retiros_socio)
    {
        $receipt = $retiros_socio->receipt ?: Receipt::where('related_type', MemberAccountClosure::class)->where('related_id', $retiros_socio->id)->firstOrFail();

        return redirect()->route('admin.recibos.pdf', $receipt);
    }

    public function voucher(MemberAccountClosure $retiros_socio)
    {
        if (! $retiros_socio->voucher_path || ! Storage::disk('public')->exists($retiros_socio->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('public')->download($retiros_socio->voucher_path);
    }

    public function voucherView(MemberAccountClosure $retiros_socio)
    {
        if (! $retiros_socio->voucher_path || ! Storage::disk('public')->exists($retiros_socio->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return response()->file(Storage::disk('public')->path($retiros_socio->voucher_path));
    }

    public function report(MemberAccountClosure $retiros_socio)
    {
        $retiros_socio->load(['member', 'details', 'creator', 'closer', 'annuller', 'receipt', 'cashMovement']);

        return view('admin.member-account-closures.report', [
            'closure' => $retiros_socio,
            'details' => $retiros_socio->details->map(fn (MemberAccountClosureDetail $detail) => $this->detailPayload($detail)),
        ]);
    }

    public function pdf(MemberAccountClosure $retiros_socio)
    {
        $retiros_socio->load(['member', 'details', 'creator', 'closer', 'annuller', 'receipt', 'cashMovement']);
        $details = $retiros_socio->details->map(fn (MemberAccountClosureDetail $detail) => $this->detailPayload($detail));

        $documentName = match ($retiros_socio->status) {
            'cerrado' => 'Constancia ',
            'anulado' => 'Cierre anulado ',
            default => 'Calculo preliminar ',
        };

        return Pdf::loadView('admin.member-account-closures.report', [
            'closure' => $retiros_socio,
            'details' => $details,
            'pdfMode' => true,
        ])->setPaper('a4', 'portrait')->stream($documentName . $retiros_socio->code . '.pdf');
    }

    private function validatedCalculationData(Request $request): array
    {
        $request->merge(['retirement_date' => $request->input('retirement_date', today()->toDateString()), 'utility_mode' => $request->input('utility_mode', 'pending')]);
        return $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'retirement_date' => ['required', 'date'],
            'utility_mode' => ['required', Rule::in(['provisional', 'pending'])],
        ], $this->messages());
    }

    private function validatedData(Request $request, ?MemberAccountClosure $closure = null): array
    {
        $this->normalizeNullableRequestFields($request);
        $request->merge(['utility_mode' => $request->input('utility_mode', 'pending')]);

        return $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'closure_date' => ['required', 'date'],
            'retirement_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['calculado', 'cerrado', 'anulado'])],
            'reason' => ['required', 'string'],
            'observation' => ['nullable', 'string'],
            'utility_mode' => ['required', Rule::in(['provisional', 'pending'])],
        ], $this->messages());
    }

    private function validatedUpdateData(Request $request): array
    {
        $this->normalizeNullableRequestFields($request);
        $request->merge(['utility_mode' => $request->input('utility_mode', 'pending')]);

        return $request->validate([
            'closure_date' => ['required', 'date'],
            'retirement_date' => ['required', 'date'],
            'reason' => ['required', 'string'],
            'observation' => ['nullable', 'string'],
            'utility_mode' => ['required', Rule::in(['provisional', 'pending'])],
        ], $this->messages());
    }

    private function validatedCloseData(Request $request, float $finalBalance): array
    {
        $this->normalizeNullableRequestFields($request);
        $requiresPayment = $finalBalance > 0;

        $data = $request->validate([
            'payment_method' => [$requiresPayment ? 'required' : 'nullable', Rule::in(self::PAYMENT_METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], $this->messages());

        if (($data['payment_method'] ?? null) && in_array($data['payment_method'], ['yape', 'plin', 'transferencia', 'cheque'], true) && blank($data['payment_reference'] ?? null)) {
            throw ValidationException::withMessages(['payment_reference' => ['La referencia de pago es obligatoria para este metodo de pago.']]);
        }

        return $data;
    }

    private function availableMemberOrFail(int $memberId, ?MemberAccountClosure $currentClosure = null): Member
    {
        $member = Member::findOrFail($memberId);

        if ($member->status !== 'vigente') {
            throw ValidationException::withMessages(['member_id' => ['El socio seleccionado ya se encuentra retirado.']]);
        }

        $alreadyClosed = MemberAccountClosure::where('member_id', $member->id)
            ->where('status', '!=', 'anulado')
            ->when($currentClosure, fn ($query) => $query->where('id', '!=', $currentClosure->id))
            ->exists();

        if ($alreadyClosed) {
            throw ValidationException::withMessages(['member_id' => ['El socio ya tiene un cierre de cuenta registrado.']]);
        }

        return $member;
    }

    private function calculationPayload(Member $member, ?string $retirementDate = null, string $utilityMode = 'pending'): array
    {
        $retirementDate ??= today()->toDateString();
        $proportionalUtility = $this->retirementUtility->calculate($member, $retirementDate, $utilityMode);
        $shares = MemberShare::where('member_id', $member->id)->where('status', 'registrado');
        $totalContributions = round((float) (clone $shares)->sum('share_capital_amount'), 2);
        $totalShares = round((float) (clone $shares)->sum('shares_quantity'), 4);
        $totalSolidarity = round((float) (clone $shares)->sum('solidarity_amount'), 2);
        $totalAdministrativeFees = round((float) (clone $shares)->sum('administrative_fee_amount'), 2);

        $details = collect();

        if ($totalContributions > 0) {
            $shareCodes = (clone $shares)->orderBy('date')->pluck('code')->filter()->implode(', ');
            $details->push([
                'item_type' => 'aporte_accion',
                'description' => 'Aportes de acciones registrados',
                'amount' => $totalContributions,
                'sign' => 'favor',
                'related_type' => MemberShare::class,
                'related_id' => null,
                'origin_code' => $shareCodes ?: null,
            ]);
        }

        $pendingUtilities = ProfitDistributionDetail::with('distribution')
            ->where('member_id', $member->id)
            ->where('status', 'pendiente')
            ->get()
            ->map(function (ProfitDistributionDetail $detail) use ($details) {
                $amount = round((float) $detail->profit_amount - (float) $detail->paid_amount, 2);
                if ($amount > 0) {
                    $metadata = $this->utilityDetailMetadata($detail);
                    $details->push([
                        'item_type' => 'utilidad_pendiente',
                        'description' => 'Utilidad generada en el periodo ' . $metadata['utility_period'],
                        'amount' => $amount,
                        'sign' => 'favor',
                        'related_type' => ProfitDistributionDetail::class,
                        'related_id' => $detail->id,
                        ...$metadata,
                        'origin_code' => $detail->distribution?->code,
                    ]);
                }

                return $amount;
            })
            ->sum();

        $activeLoans = Loan::with('installments')
            ->where('member_id', $member->id)
            ->whereIn('status', ['desembolsado', 'refinanciado'])
            ->get();

        $pendingLoans = 0.0;
        $activeLoansCount = 0;
        $loanCapital = 0.0;
        $overdueInterest = 0.0;
        $futureInterestExonerated = 0.0;
        foreach ($activeLoans as $loan) {
            $installments = $loan->installments->filter(fn ($installment) => ! in_array($installment->status, ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'], true) && (float) $installment->remaining_amount > 0);
            $debt = $this->settlement->debt($loan, today());
            $balance = $debt['total'];

            if ($balance <= 0) {
                continue;
            }

            $activeLoansCount++;
            $pendingLoans += $balance;
            $loanCapital += $debt['capital'];
            $overdueInterest += $debt['overdue_interest'];
            $futureInterestExonerated += $debt['future_interest_exonerated'];
            $overdueCount = $installments->filter(fn ($installment) => $installment->due_date && $installment->due_date->lt(today()))->count();

            $details->push([
                'item_type' => $overdueCount > 0 ? 'deuda_vencida' : 'prestamo_pendiente',
                'description' => 'Préstamo ' . ($loan->loan_number ?? 'sin código') . ($overdueCount > 0 ? ' - ' . $overdueCount . ' cuota(s) vencida(s)' : ''),
                'amount' => round($balance, 2),
                'sign' => 'contra',
                'related_type' => Loan::class,
                'related_id' => $loan->id,
                'origin_code' => $loan->loan_number,
            ]);
        }

        $pendingLoans = round($pendingLoans, 2);
        $pendingUtilities = round((float) $pendingUtilities, 2);
        if ($proportionalUtility['paid_now'] > 0) {
            $details->push(['item_type' => 'utilidad_provisional', 'description' => 'Utilidad proporcional provisional ' . $proportionalUtility['period_year'], 'amount' => $proportionalUtility['paid_now'], 'sign' => 'favor', 'related_type' => null, 'related_id' => null]);
        } elseif ($proportionalUtility['action_month'] > 0) {
            $details->push(['item_type' => 'utilidad_pendiente_cierre', 'description' => 'Derecho proporcional reservado para el cierre anual ' . $proportionalUtility['period_year'], 'amount' => 0, 'sign' => 'informativo', 'related_type' => null, 'related_id' => null]);
        }
        $totalInFavor = round($totalContributions + $pendingUtilities + $proportionalUtility['paid_now'], 2);
        $totalAgainst = $pendingLoans;
        $finalBalance = round($totalInFavor - $totalAgainst, 2);

        return [
            'member' => $this->memberSummary($member),
            'summary' => [
                'total_contributions' => $totalContributions,
                'total_contributions_formatted' => $this->money($totalContributions),
                'total_shares' => $totalShares,
                'total_solidarity_non_refundable' => $totalSolidarity,
                'total_solidarity_non_refundable_formatted' => $this->money($totalSolidarity),
                'total_administrative_fees_non_refundable' => $totalAdministrativeFees,
                'total_administrative_fees_non_refundable_formatted' => $this->money($totalAdministrativeFees),
                'active_loans_count' => $activeLoansCount,
                'pending_loans_amount' => $pendingLoans,
                'pending_loans_amount_formatted' => $this->money($pendingLoans),
                'pending_utilities_amount' => $pendingUtilities,
                'loan_capital_compensated' => round($loanCapital, 2),
                'overdue_interest_compensated' => round($overdueInterest, 2),
                'future_interest_exonerated' => round($futureInterestExonerated, 2),
                'pending_utilities_amount_formatted' => $this->money($pendingUtilities),
                'has_pending_utilities' => $pendingUtilities > 0,
                'utilities_note' => $pendingUtilities > 0
                    ? 'Incluye solo utilidades calculadas y pendientes registradas en el modulo de Utilidades.'
                    : 'Sin utilidades pendientes. Las utilidades se calculan desde el mes siguiente del aporte y segun los cierres de utilidad registrados.',
                'utility_mode' => $proportionalUtility['mode'],
                'utility_status' => $proportionalUtility['status'],
                'utility_status_label' => $this->utilityStatusLabel($proportionalUtility['status']),
                'utility_period_year' => $proportionalUtility['period_year'],
                'utility_actions_considered' => $proportionalUtility['actions_considered'],
                'utility_productive_months' => $proportionalUtility['productive_months'],
                'utility_action_month' => $proportionalUtility['action_month'],
                'utility_total_action_month' => $proportionalUtility['total_action_month'],
                'utility_available' => $proportionalUtility['available'],
                'utility_available_formatted' => $this->money($proportionalUtility['available']),
                'utility_estimated_amount' => $proportionalUtility['estimated'],
                'utility_estimated_formatted' => $this->money($proportionalUtility['estimated']),
                'utility_paid_now' => $proportionalUtility['paid_now'],
                'utility_paid_now_formatted' => $this->money($proportionalUtility['paid_now']),
                'utility_pending_annual' => $proportionalUtility['pending_amount'],
                'utility_pending_annual_formatted' => $this->money($proportionalUtility['pending_amount']),
                'utility_note' => $proportionalUtility['available'] <= 0 && $proportionalUtility['action_month'] > 0
                    ? 'Utilidad pendiente de cálculo. El socio tiene participación acumulada, pero aún no existe cierre de utilidad registrado.'
                    : ($proportionalUtility['mode'] === 'pending' ? 'El derecho queda pendiente para el cierre anual.' : 'Importe provisional calculado con la utilidad real disponible.'),
                'utility_calculation_breakdown' => $proportionalUtility['breakdown'],
                'total_in_favor' => $totalInFavor,
                'total_in_favor_formatted' => $this->money($totalInFavor),
                'total_against' => $totalAgainst,
                'total_against_formatted' => $this->money($totalAgainst),
                'final_balance' => $finalBalance,
                'final_balance_formatted' => $this->signedMoney($finalBalance),
                'settlement_type' => $this->settlementType($finalBalance),
                'settlement_label' => $this->settlementLabel($this->settlementType($finalBalance)),
            ],
            'details' => $details->map(fn ($detail) => $this->calculationDetailPayload($detail))->values(),
        ];
    }

    private function closureAmounts(array $calculation): array
    {
        $summary = $calculation['summary'];

        return [
            'total_contributions' => $summary['total_contributions'],
            'total_shares' => $summary['total_shares'],
            'pending_loans_amount' => $summary['pending_loans_amount'],
            'loan_capital_compensated' => $summary['loan_capital_compensated'],
            'overdue_interest_compensated' => $summary['overdue_interest_compensated'],
            'future_interest_exonerated' => $summary['future_interest_exonerated'],
            'pending_utilities_amount' => $summary['pending_utilities_amount'],
            'utility_mode' => $summary['utility_mode'], 'utility_status' => $summary['utility_status'], 'utility_period_year' => $summary['utility_period_year'],
            'utility_actions_considered' => $summary['utility_actions_considered'], 'utility_productive_months' => $summary['utility_productive_months'],
            'utility_action_month' => $summary['utility_action_month'], 'utility_total_action_month' => $summary['utility_total_action_month'],
            'utility_available_snapshot' => $summary['utility_available'], 'utility_estimated_amount' => $summary['utility_estimated_amount'],
            'utility_paid_now' => $summary['utility_paid_now'], 'utility_calculation_breakdown' => $summary['utility_calculation_breakdown'],
            'total_in_favor' => $summary['total_in_favor'],
            'total_against' => $summary['total_against'],
            'final_balance' => $summary['final_balance'],
            'settlement_type' => $summary['settlement_type'],
        ];
    }

    private function calculationStatus(array $calculation): string
    {
        $summary = $calculation['summary'];

        return $this->requiresRegularization(
            (float) $summary['final_balance'],
            (float) $summary['total_in_favor'],
            (float) $summary['total_against']
        ) ? 'pendiente_regularizacion' : 'calculado';
    }

    private function loanScheduleSnapshot($loans): array
    {
        return collect($loans)->mapWithKeys(fn ($loan) => [(string) $loan->id => [
            'loan_status' => $loan->status,
            'current_balance' => $loan->current_balance,
            'installments' => $loan->installments()->orderBy('installment_number')->get()->map(fn ($row) => $row->only(['id', 'paid_amount', 'capital_paid', 'interest_paid', 'interest_exonerated', 'remaining_amount', 'status', 'payment_type', 'paid_at']))->all(),
        ]])->all();
    }

    private function restoreLoanSchedules(array $snapshot): void
    {
        foreach ($snapshot as $loanId => $loanData) {
            Loan::whereKey($loanId)->update(['status' => $loanData['loan_status'], 'current_balance' => $loanData['current_balance'], 'updated_by' => auth()->id()]);
            foreach ($loanData['installments'] as $values) {
                $id = $values['id'];
                unset($values['id']);
                \App\Models\LoanInstallment::where('loan_id', $loanId)->whereKey($id)->update($values);
            }
        }
    }

    private function syncDetails(MemberAccountClosure $closure, mixed $details): void
    {
        $closure->details()->delete();
        $closure->details()->createMany(collect($details)->map(fn ($detail) => [
            'item_type' => $detail['item_type'],
            'description' => $detail['description'],
            'amount' => $detail['amount'],
            'sign' => $detail['sign'],
            'related_type' => $detail['related_type'] ?? null,
            'related_id' => $detail['related_id'] ?? null,
        ])->all());
    }

    private function ensureCalculationIsCurrent(MemberAccountClosure $closure, array $calculation): void
    {
        foreach ($this->closureAmounts($calculation) as $field => $value) {
            if (is_numeric($value) && abs((float) $closure->{$field} - (float) $value) > 0.02) {
                throw ValidationException::withMessages(['member_id' => ['El calculo del cierre no esta actualizado.']]);
            }
        }
    }

    private function syncCashMovement(MemberAccountClosure $closure): ?CashMovement
    {
        $amount = abs(round((float) $closure->final_balance, 2));
        if ($amount <= 0 || ! $closure->payment_method) {
            return null;
        }

        if ((float) $closure->final_balance > 0 && $this->currentCashBalance() < $amount) {
            throw ValidationException::withMessages(['payment_method' => ['No hay saldo suficiente en caja para realizar la devolucion al socio.']]);
        }

        $movement = CashMovement::where('related_type', MemberAccountClosure::class)->where('related_id', $closure->id)->lockForUpdate()->first();
        $movement ??= new CashMovement(['movement_number' => CashMovement::nextCode(), 'created_by' => auth()->id()]);

        $isReturn = (float) $closure->final_balance > 0;
        $movement->fill([
            'movement_date' => $closure->closure_date,
            'type' => $isReturn ? 'egreso' : 'ingreso',
            'category' => $isReturn ? 'devolucion_socio' : 'cierre_socio',
            'concept' => 'Retiro y cierre de cuenta ' . $closure->code . ' del socio ' . ($closure->member?->full_name ?? '-'),
            'amount' => $amount,
            'payment_method' => $closure->payment_method,
            'reference' => $closure->payment_reference,
            'voucher_path' => $closure->voucher_path,
            'related_type' => MemberAccountClosure::class,
            'related_id' => $closure->id,
            'observation' => $closure->reason,
            'status' => 'registrado',
            'updated_by' => auth()->id(),
        ]);
        $movement->save();

        app(ShareCashMovementService::class)->recalculateBalances();

        return $movement->fresh();
    }

    private function createOrUpdateReceipt(MemberAccountClosure $closure, ?CashMovement $movement): Receipt
    {
        $receipt = Receipt::firstOrNew(['related_type' => MemberAccountClosure::class, 'related_id' => $closure->id]);
        if (! $receipt->exists) {
            $receipt->receipt_number = $this->generateNextReceiptNumber();
            $receipt->created_by = auth()->id();
        }

        $receipt->fill([
            'receipt_date' => $closure->closure_date,
            'member_id' => $closure->member_id,
            'type' => 'cierre_socio',
            'amount' => abs((float) $closure->final_balance),
            'payment_method' => $closure->payment_method,
            'payment_reference' => $closure->payment_reference,
            'voucher_path' => $closure->voucher_path,
            'observation' => 'Cierre de cuenta ' . $closure->code . ' - ' . $this->settlementLabel($closure->settlement_type),
            'status' => 'registrado',
            'updated_by' => auth()->id(),
        ]);
        $receipt->save();

        return $receipt;
    }

    private function currentCashBalance(): float
    {
        $query = CashMovement::where('status', 'registrado');

        return (float) (clone $query)->where('type', 'ingreso')->sum('amount') - (float) (clone $query)->where('type', 'egreso')->sum('amount');
    }

    private function storeVoucher(Request $request, array &$data, MemberAccountClosure $closure): void
    {
        if (! $request->hasFile('voucher_path')) {
            unset($data['voucher_path']);
            return;
        }

        if ($closure->voucher_path) {
            Storage::disk('public')->delete($closure->voucher_path);
        }

        $data['voucher_path'] = $request->file('voucher_path')->store('member-account-closures', 'public');
    }

    private function closurePayload(MemberAccountClosure $closure): array
    {
        $closure->load(['member', 'details', 'creator', 'closer', 'annuller', 'receipt', 'cashMovement']);
        $voucherExists = filled($closure->voucher_path) && Storage::disk('public')->exists($closure->voucher_path);
        $voucherExtension = $voucherExists ? strtolower(pathinfo($closure->voucher_path, PATHINFO_EXTENSION)) : null;
        $movement = $closure->cashMovement;
        $receipt = $closure->receipt;

        return [
            'id' => $closure->id,
            'code' => $closure->code,
            'member_id' => $closure->member_id,
            'member' => $closure->member ? $this->memberSummary($closure->member) : null,
            'closure_date' => optional($closure->closure_date)->format('Y-m-d'),
            'closure_date_formatted' => optional($closure->closure_date)->format('d/m/Y'),
            'retirement_date' => optional($closure->retirement_date)->format('Y-m-d'),
            'retirement_date_formatted' => optional($closure->retirement_date)->format('d/m/Y'),
            'total_contributions' => number_format((float) $closure->total_contributions, 2, '.', ''),
            'total_contributions_formatted' => $this->money($closure->total_contributions),
            'total_shares' => number_format((float) $closure->total_shares, 4, '.', ''),
            'pending_loans_amount' => number_format((float) $closure->pending_loans_amount, 2, '.', ''),
            'pending_loans_amount_formatted' => $this->money($closure->pending_loans_amount),
            'pending_utilities_amount' => number_format((float) $closure->pending_utilities_amount, 2, '.', ''),
            'pending_utilities_amount_formatted' => $this->money($closure->pending_utilities_amount),
            'has_pending_utilities' => (float) $closure->pending_utilities_amount > 0,
            'utilities_note' => (float) $closure->pending_utilities_amount > 0
                ? 'Incluye solo utilidades calculadas y pendientes registradas en el modulo de Utilidades.'
                : ((float) $closure->utility_action_month > 0 ? 'Utilidad pendiente de cálculo. El socio tiene participación acumulada, pero aún no existe cierre de utilidad registrado.' : 'El socio no registra meses productivos para el periodo.'),
            'utility_mode' => $closure->utility_mode,
            'utility_status' => $closure->utility_status,
            'utility_status_label' => $this->utilityStatusLabel($closure->utility_status),
            'utility_period_year' => $closure->utility_period_year,
            'utility_actions_considered' => number_format((float) $closure->utility_actions_considered, 4, '.', ''),
            'utility_productive_months' => $closure->utility_productive_months,
            'utility_action_month' => number_format((float) $closure->utility_action_month, 4, '.', ''),
            'utility_available_formatted' => $this->money($closure->utility_available_snapshot),
            'utility_estimated_formatted' => $this->money($closure->utility_estimated_amount),
            'utility_paid_now_formatted' => $this->money($closure->utility_paid_now),
            'utility_pending_annual_formatted' => $this->money($closure->utility_mode === 'pending' ? $closure->utility_estimated_amount : 0),
            'utility_note' => (float) $closure->utility_available_snapshot <= 0 && (float) $closure->utility_action_month > 0
                ? 'Utilidad pendiente de cálculo. El socio tiene participación acumulada, pero aún no existe cierre de utilidad registrado.'
                : ($closure->utility_mode === 'pending' ? 'El derecho quedó pendiente para el cierre anual.' : 'La utilidad proporcional fue incluida en el cierre.'),
            'total_in_favor' => number_format((float) $closure->total_in_favor, 2, '.', ''),
            'total_in_favor_formatted' => $this->money($closure->total_in_favor),
            'total_against' => number_format((float) $closure->total_against, 2, '.', ''),
            'total_against_formatted' => $this->money($closure->total_against),
            'final_balance' => number_format((float) $closure->final_balance, 2, '.', ''),
            'final_balance_formatted' => $this->signedMoney($closure->final_balance),
            'settlement_type' => $closure->settlement_type,
            'settlement_label' => $this->settlementLabel($closure->settlement_type),
            'payment_method' => $closure->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($closure->payment_method),
            'payment_reference' => $closure->payment_reference,
            'voucher_exists' => $voucherExists,
            'voucher_type' => in_array($voucherExtension, ['jpg', 'jpeg', 'png', 'webp'], true) ? 'image' : ($voucherExtension === 'pdf' ? 'pdf' : null),
            'voucher_view_url' => $voucherExists ? route('admin.retiros-socios.voucher.view', $closure) : null,
            'voucher_download_url' => $voucherExists ? route('admin.retiros-socios.voucher', $closure) : null,
            'receipt_generated' => (bool) $receipt,
            'receipt_number' => $receipt?->receipt_number,
            'receipt_status_label' => $receipt ? ucfirst((string) $receipt->status) : 'No aplica',
            'receipt_url' => $receipt ? route('admin.retiros-socios.receipt', $closure) : null,
            'receipt_pdf_url' => $receipt ? route('admin.retiros-socios.receipt.pdf', $closure) : null,
            'cash_movement_generated' => (bool) $movement,
            'cash_movement' => $movement ? [
                'id' => $movement->id,
                'number' => $movement->movement_number,
                'date' => optional($movement->movement_date)->format('d/m/Y'),
                'type' => $movement->type,
                'type_label' => $movement->type === 'egreso' ? 'Egreso' : 'Ingreso',
                'amount_formatted' => $this->money($movement->amount),
                'payment_method_label' => $this->paymentMethodLabel($movement->payment_method),
                'reference' => $movement->payment_method === 'efectivo' ? 'No aplica' : ($movement->reference ?: 'No aplica'),
                'balance_after_formatted' => $movement->balance_after !== null ? $this->money($movement->balance_after) : 'No aplica',
                'status_label' => $movement->status === 'registrado' ? 'Registrado' : ucfirst((string) $movement->status),
                'url' => route('admin.caja.index', ['movement_id' => $movement->id]),
            ] : null,
            'reversal_movement' => null,
            'report_url' => route('admin.retiros-socios.report', $closure),
            'pdf_url' => route('admin.retiros-socios.pdf', $closure),
            'reason' => $closure->reason,
            'observation' => $closure->observation,
            'status' => $closure->status,
            'status_label' => $this->statusLabelForClosure($closure),
            'status_message' => $this->closureStatusMessage($closure),
            'result_tone' => (float) $closure->final_balance > 0 ? 'favor' : ((float) $closure->final_balance < 0 ? 'contra' : 'zero'),
            'confirmation_scenario' => $this->confirmationScenario($closure),
            'can_confirm' => in_array($closure->status, ['calculado', 'pendiente_regularizacion'], true) && ! $this->requiresRegularization(
                (float) $closure->final_balance,
                (float) $closure->total_in_favor,
                (float) $closure->total_against
            ),
            'created_by_name' => $closure->creator?->name,
            'calculated_by_name' => $closure->creator?->name,
            'created_at' => optional($closure->created_at)->format('d/m/Y H:i'),
            'calculated_at' => optional($closure->created_at)->format('d/m/Y H:i'),
            'closed_by_name' => $closure->closer?->name,
            'confirmed_by_name' => $closure->closer?->name,
            'closed_at' => optional($closure->closed_at)->format('d/m/Y H:i'),
            'confirmed_at' => optional($closure->closed_at)->format('d/m/Y H:i'),
            'annulled_by_name' => $closure->annuller?->name,
            'annulled_at' => optional($closure->annulled_at)->format('d/m/Y H:i'),
            'annulment_reason' => $closure->annulment_reason,
            'details' => $closure->details->map(fn (MemberAccountClosureDetail $detail) => $this->detailPayload($detail))->values(),
        ];
    }

    private function memberSummary(Member $member): array
    {
        return [
            'id' => $member->id,
            'code' => $member->code,
            'dni' => $member->dni,
            'full_name' => $member->full_name,
            'admission_date' => optional($member->admission_date)->format('Y-m-d'),
            'admission_date_formatted' => optional($member->admission_date)->format('d/m/Y'),
            'membership_time' => $member->admission_date ? $member->admission_date->diffForHumans(null, true) : '-',
            'status' => $member->status,
            'status_label' => ucfirst(str_replace('_', ' ', $member->status ?? '-')),
        ];
    }

    private function calculationDetailPayload(array $detail): array
    {
        $origin = $this->friendlyOrigin($detail['related_type'] ?? null, $detail['related_id'] ?? null, $detail['origin_code'] ?? null);
        return [
            'item_type' => $detail['item_type'],
            'item_type_label' => $this->itemTypeLabel($detail['item_type'], $detail['sign'] ?? null),
            'description' => $detail['description'],
            'amount' => round((float) $detail['amount'], 2),
            'amount_formatted' => $this->money($detail['amount']),
            'favor_amount_formatted' => $detail['sign'] === 'favor' ? $this->money($detail['amount']) : 'No aplica',
            'against_amount_formatted' => $detail['sign'] === 'contra' ? $this->money($detail['amount']) : 'No aplica',
            'sign' => $detail['sign'],
            'sign_label' => $detail['sign'] === 'favor' ? 'A favor' : 'En contra',
            'origin_label' => $origin['label'],
            'origin_code' => $origin['code'],
            'related_label' => $origin['code'] ? $origin['label'] . ' · ' . $origin['code'] : $origin['label'],
            'related_type' => $detail['related_type'] ?? null,
            'related_id' => $detail['related_id'] ?? null,
            'utility_period' => $detail['utility_period'] ?? null,
            'utility_months' => $detail['utility_months'] ?? null,
            'utility_shares' => $detail['utility_shares'] ?? null,
            'utility_profit_per_share_formatted' => $detail['utility_profit_per_share_formatted'] ?? null,
        ];
    }

    private function detailPayload(MemberAccountClosureDetail $detail): array
    {
        $data = $detail->toArray();
        if ($detail->item_type === 'aporte_accion') {
            $memberId = $detail->closure()->value('member_id');
            $codes = MemberShare::where('member_id', $memberId)
                ->where('status', 'registrado')
                ->orderBy('date')
                ->pluck('code')
                ->filter()
                ->implode(', ');
            $data['origin_code'] = $codes ?: null;
        }
        if ($detail->item_type === 'utilidad_pendiente' && $detail->related_id) {
            $utility = ProfitDistributionDetail::with('distribution')->find($detail->related_id);
            if ($utility) $data = array_merge($data, $this->utilityDetailMetadata($utility));
        }
        return $this->calculationDetailPayload($data);
    }

    private function friendlyOrigin(?string $type, ?int $id, ?string $knownCode = null): array
    {
        $label = match ($type) {
            MemberShare::class => 'Acciones / Aportes',
            Loan::class => 'Préstamos',
            ProfitDistributionDetail::class => 'Utilidades',
            \App\Models\LoanPayment::class => 'Cobros',
            CashMovement::class => 'Caja',
            Receipt::class => 'Recibos',
            MemberAccountClosure::class => 'Retiro / Cierre de cuenta',
            \App\Models\SolidarityMovement::class => 'Solidaridad',
            null, '' => 'Otros conceptos',
            default => 'Otros movimientos',
        };

        $code = $knownCode;
        if (! $code && $id) {
            $code = match ($type) {
                MemberShare::class => MemberShare::whereKey($id)->value('code'),
                Loan::class => Loan::whereKey($id)->value('loan_number'),
                ProfitDistributionDetail::class => ProfitDistributionDetail::with('distribution')->find($id)?->distribution?->code,
                \App\Models\LoanPayment::class => \App\Models\LoanPayment::whereKey($id)->value('payment_number'),
                CashMovement::class => CashMovement::whereKey($id)->value('movement_number'),
                Receipt::class => Receipt::whereKey($id)->value('receipt_number'),
                MemberAccountClosure::class => MemberAccountClosure::whereKey($id)->value('code'),
                default => null,
            };
        }

        return ['label' => $label, 'code' => $code];
    }

    private function utilityDetailMetadata(ProfitDistributionDetail $detail): array
    {
        $distribution = $detail->distribution;
        $period = $distribution?->period_month
            ? str_pad((string) $distribution->period_month, 2, '0', STR_PAD_LEFT) . '/' . $distribution->period_year
            : (string) ($distribution?->period_year ?? '-');
        $months = $detail->months_considered ?: ($distribution?->start_date && $distribution?->end_date
            ? $distribution->start_date->copy()->startOfMonth()->diffInMonths($distribution->end_date->copy()->startOfMonth()) + 1
            : null);

        return [
            'utility_period' => $period,
            'utility_months' => $months,
            'utility_shares' => number_format((float) ((float) $detail->action_month > 0 ? $detail->action_month : $detail->shares_quantity), 4, '.', ''),
            'utility_profit_per_share_formatted' => 'S/ ' . number_format((float) ((float) ($distribution?->profit_per_action_month ?? 0) > 0 ? $distribution->profit_per_action_month : ($distribution?->profit_per_share ?? 0)), 8),
        ];
    }

    private function membersForSelect()
    {
        return Member::query()
            ->where('status', 'vigente')
            ->whereDoesntHave('accountClosures', fn ($query) => $query->where('status', '!=', 'anulado'))
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name', 'admission_date', 'status']);
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['payment_method', 'payment_reference', 'observation'];
        $normalized = [];

        foreach ($fields as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()->whereNotNull('receipt_number')->where('receipt_number', 'like', 'REC-%')->orderByDesc('id')->lockForUpdate()->value('receipt_number');
        $lastNumber = $lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches) ? (int) $matches[1] : 0;

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function settlementType(float $balance): string
    {
        return $balance > 0 ? 'favor_socio' : ($balance < 0 ? 'contra_socio' : 'sin_saldo');
    }

    private function settlementLabel(?string $type): string
    {
        return match ($type) {
            'favor_socio' => 'Saldo a favor del socio',
            'contra_socio' => 'Saldo en contra del socio',
            'sin_saldo' => 'Sin saldo',
            default => '-',
        };
    }

    private function itemTypeLabel(?string $type, ?string $sign = null): string
    {
        return match ($type) {
            'aporte_accion' => 'Aporte de acciones',
            'utilidad_pendiente' => 'Utilidad pendiente',
            'utilidad_provisional' => 'Utilidad provisional',
            'utilidad_pendiente_cierre' => 'Derecho de utilidad',
            'prestamo_pendiente' => 'Préstamo pendiente',
            'cuota_pendiente' => 'Cuota pendiente',
            'deuda_vencida' => 'Deuda vencida',
            'solidaridad' => 'Solidaridad',
            'ajuste' => $sign === 'favor' ? 'Otros ingresos' : 'Otros descuentos',
            default => $sign === 'favor' ? 'Otros ingresos' : 'Otros descuentos',
        };
    }

    private function utilityStatusLabel(?string $status): string
    {
        return match ($status) {
            'provisional' => 'Provisional',
            'pendiente_cierre_anual' => 'Pendiente de cierre anual',
            'liquidada' => 'Liquidada',
            default => 'No calculada',
        };
    }

    private function closureStatusMessage(MemberAccountClosure $closure): string
    {
        if ($closure->status === 'anulado') {
            return 'Este cierre fue anulado y se conserva únicamente como historial de auditoría.';
        }
        if (in_array($closure->status, ['calculado', 'pendiente_regularizacion'], true) && $this->requiresRegularization((float) $closure->final_balance, (float) $closure->total_in_favor, (float) $closure->total_against)) {
            return 'El socio tiene saldo en contra. No se puede confirmar el retiro hasta regularizar la deuda pendiente.';
        }
        if ($closure->status === 'cerrado') {
            return (float) $closure->final_balance == 0.0
                ? 'Cierre confirmado correctamente. El socio fue marcado como retirado sin generar movimiento de caja porque no existía saldo.'
                : 'Cierre confirmado correctamente. El socio fue marcado como retirado y se generó el movimiento de caja correspondiente.';
        }

        return 'Cálculo de cierre registrado. El socio continúa vigente hasta que el cierre sea confirmado.';
    }

    private function paymentMethods(): array
    {
        return ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque', 'otro' => 'Otro'];
    }

    private function paymentMethodLabel(?string $method): string
    {
        return $this->paymentMethods()[$method] ?? '-';
    }

    private function statusBadge(MemberAccountClosure $closure): string
    {
        $isPendingRegularization = $closure->status === 'pendiente_regularizacion' || ($closure->status === 'calculado' && $this->requiresRegularization(
            (float) $closure->final_balance,
            (float) $closure->total_in_favor,
            (float) $closure->total_against
        ));
        $class = match (true) {
            $isPendingRegularization => 'warning',
            $closure->status === 'cerrado' => 'success',
            $closure->status === 'anulado' => 'danger',
            default => 'secondary',
        };

        return '<span class="badge badge-' . $class . '">' . e($this->statusLabelForClosure($closure)) . '</span>';
    }

    private function statusLabelForClosure(MemberAccountClosure $closure): string
    {
        if ($closure->status === 'pendiente_regularizacion' || ($closure->status === 'calculado' && $this->requiresRegularization(
            (float) $closure->final_balance,
            (float) $closure->total_in_favor,
            (float) $closure->total_against
        ))) {
            return 'Pendiente de regularización';
        }

        return $this->statusLabel($closure->status);
    }

    private function confirmationScenario(MemberAccountClosure $closure): string
    {
        if ($this->requiresRegularization((float) $closure->final_balance, (float) $closure->total_in_favor, (float) $closure->total_against)) {
            return 'saldo_en_contra';
        }

        return (float) $closure->final_balance > 0 ? 'saldo_a_favor' : 'saldo_cero';
    }

    private function requiresRegularization(float $finalBalance, float $totalInFavor, float $totalAgainst): bool
    {
        return $finalBalance < -0.009 || $totalAgainst > $totalInFavor + 0.009;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'calculado' => 'Calculado',
            'pendiente_regularizacion' => 'Pendiente de regularización',
            'cerrado' => 'Confirmado',
            'anulado' => 'Anulado',
            default => '-',
        };
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function signedMoney(mixed $amount): string
    {
        $value = (float) $amount;
        return ($value < 0 ? '- ' : '') . 'S/ ' . number_format(abs($value), 2);
    }

    private function messages(): array
    {
        return [
            'member_id.required' => 'Seleccione un socio valido.',
            'member_id.exists' => 'Seleccione un socio valido.',
            'closure_date.required' => 'La fecha de cierre es obligatoria.',
            'closure_date.date' => 'La fecha de cierre debe ser valida.',
            'retirement_date.required' => 'La fecha de retiro es obligatoria.',
            'retirement_date.date' => 'La fecha de retiro debe ser valida.',
            'reason.required' => 'El motivo del retiro es obligatorio.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
        ];
    }
}
