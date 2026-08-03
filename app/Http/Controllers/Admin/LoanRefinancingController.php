<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanRefinancing;
use App\Models\Member;
use App\Models\Receipt;
use App\Services\LoanSimulationCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LoanRefinancingController extends Controller
{
    public function __construct(private readonly LoanSimulationCalculator $calculator)
    {
        $this->middleware('can:admin.refinanciamientos.index')->only(['index', 'list', 'summary', 'nextCode', 'loansByMember', 'loanBalance']);
        $this->middleware('can:admin.refinanciamientos.create')->only(['calculate', 'store']);
        $this->middleware('can:admin.refinanciamientos.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.refinanciamientos.show')->only(['show']);
        $this->middleware('can:admin.refinanciamientos.anular')->only(['annul', 'destroy']);
        $this->middleware('can:admin.refinanciamientos.schedule')->only(['schedule']);
        $this->middleware('can:admin.refinanciamientos.print')->only(['print']);
        $this->middleware('can:admin.refinanciamientos.pdf')->only(['pdf']);
    }

    public function index()
    {
        return view('admin.loan-refinancings.index', [
            'members' => Member::where('status', 'vigente')->orderBy('full_name')->get(['id', 'code', 'dni', 'full_name']),
            'nextCode' => LoanRefinancing::nextCode(),
        ]);
    }

    public function list(Request $request)
    {
        $items = LoanRefinancing::with(['member', 'originalLoan.installments', 'newLoan'])
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('refinancing_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('refinancing_date', '<=', $request->input('date_to')))
            ->when($request->input('has_overdue') === '1', fn ($query) => $query->whereHas('originalLoan.installments', fn ($subQuery) => $this->historicalOverdueInstallmentQuery($subQuery)))
            ->orderByDesc('refinancing_date')
            ->orderByDesc('id');

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('member_name', fn (LoanRefinancing $refinancing) => $refinancing->member?->full_name ?? '-')
            ->addColumn('member_dni', fn (LoanRefinancing $refinancing) => $refinancing->member?->dni ?? '-')
            ->addColumn('original_loan_number', fn (LoanRefinancing $refinancing) => $refinancing->originalLoan?->loan_number ?? '-')
            ->addColumn('new_loan_number', fn (LoanRefinancing $refinancing) => $refinancing->newLoan?->loan_number ?? '-')
            ->addColumn('overdue_installments', fn (LoanRefinancing $refinancing) => $this->overdueCountForRefinancing($refinancing))
            ->editColumn('refinancing_date', fn (LoanRefinancing $refinancing) => optional($refinancing->refinancing_date)->format('d/m/Y') ?? '-')
            ->editColumn('previous_balance', fn (LoanRefinancing $refinancing) => $this->money($refinancing->previous_balance))
            ->editColumn('new_amount', fn (LoanRefinancing $refinancing) => $this->money($refinancing->new_amount))
            ->editColumn('status', fn (LoanRefinancing $refinancing) => $this->statusBadge($refinancing->status))
            ->addColumn('acciones', fn (LoanRefinancing $refinancing) => view('admin.loan-refinancings.partials.acciones', compact('refinancing'))->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'total' => number_format((float) LoanRefinancing::where('status', 'registrado')->sum('new_amount'), 2),
            'registered' => LoanRefinancing::where('status', 'registrado')->count(),
            'refinanced_loans' => Loan::where('status', 'refinanciado')->count(),
            'month' => number_format((float) LoanRefinancing::where('status', 'registrado')->whereYear('refinancing_date', now()->year)->whereMonth('refinancing_date', now()->month)->sum('new_amount'), 2),
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => LoanRefinancing::nextCode()]);
    }

    public function loansByMember(Member $member)
    {
        return response()->json(Loan::where('member_id', $member->id)
            ->where('status', 'desembolsado')
            ->whereHas('installments', fn ($query) => $this->refinanceableInstallmentQuery($query))
            ->with('installments')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Loan $loan) => $this->canLoanBeRefinanced($loan))
            ->values()
            ->map(fn (Loan $loan) => $this->loanPayload($loan)));
    }

    public function loanBalance(Loan $loan)
    {
        $loan->load('installments');
        $this->ensureLoanCanRefinance($loan);

        return response()->json([
            'loan' => $this->loanPayload($loan),
            'closed_installments' => $this->refinanceableInstallments($loan)->values()->map(fn ($item) => $this->installmentPayload($item)),
            'paid_installments' => $loan->installments->where('status', 'pagado')->count(),
        ]);
    }

    public function calculate(Request $request)
    {
        $data = $this->validatedData($request, false);
        $loan = Loan::with('installments')->findOrFail($data['original_loan_id']);
        $this->ensureLoanCanRefinance($loan);

        return response()->json($this->calculationPayload($this->refinancingBalance($loan), $data));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $refinancing = DB::transaction(function () use ($data) {
            $originalLoan = Loan::with(['member', 'installments'])->lockForUpdate()->findOrFail($data['original_loan_id']);
            $this->ensureLoanCanRefinance($originalLoan);

            $previousBalance = $this->refinancingBalance($originalLoan);
            $calculation = $this->calculationPayload($previousBalance, $data);
            $closedSnapshot = $this->closedInstallmentsSnapshot($originalLoan);

            $refinancing = LoanRefinancing::create([
                'code' => LoanRefinancing::nextCode(),
                'original_loan_id' => $originalLoan->id,
                'member_id' => $originalLoan->member_id,
                'refinancing_date' => $data['refinancing_date'],
                'previous_balance' => $previousBalance,
                'additional_amount' => (float) ($data['additional_amount'] ?? 0),
                'new_amount' => $calculation['new_amount'],
                'interest_rate' => $data['interest_rate'],
                'term_months' => $data['term_months'],
                'start_date' => $data['start_date'],
                'first_payment_date' => $calculation['summary']['first_payment_date'],
                'amortization_method' => 'aleman',
                'fixed_principal' => $calculation['summary']['fixed_principal'],
                'total_interest' => $calculation['summary']['total_interest'],
                'total_amount' => $calculation['summary']['total_payment'],
                'reason' => $data['reason'],
                'observation' => $data['observation'] ?? null,
                'closed_installments_snapshot' => $closedSnapshot,
                'status' => 'registrado',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $originalLoan->installments()
                ->where(fn ($query) => $this->refinanceableInstallmentQuery($query))
                ->update(['status' => 'refinanciado']);

            $originalLoan->update(['status' => 'refinanciado', 'updated_by' => auth()->id()]);

            $newLoan = Loan::create([
                'refinancing_id' => $refinancing->id,
                'member_id' => $originalLoan->member_id,
                'loan_number' => Loan::nextCode(),
                'requested_amount' => $calculation['new_amount'],
                'approved_amount' => $calculation['new_amount'],
                'interest_rate' => $data['interest_rate'],
                'interest_type' => 'mensual',
                'term_months' => $data['term_months'],
                'start_date' => $data['start_date'],
                'first_payment_date' => $calculation['summary']['first_payment_date'],
                'payment_frequency' => 'mensual',
                'amortization_method' => 'aleman',
                'fixed_principal' => $calculation['summary']['fixed_principal'],
                'total_interest' => $calculation['summary']['total_interest'],
                'total_amount' => $calculation['summary']['total_payment'],
                'current_balance' => $calculation['summary']['total_payment'],
                'disbursed_amount' => $calculation['new_amount'],
                'approved_at' => $data['refinancing_date'],
                'disbursed_at' => $data['refinancing_date'],
                'status' => 'desembolsado',
                'purpose' => 'Refinanciamiento de prestamo ' . $originalLoan->loan_number,
                'observation' => $data['observation'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'approved_by' => auth()->id(),
                'disbursed_by' => auth()->id(),
            ]);

            $newLoan->installments()->createMany($this->installmentRows($calculation['installments']));

            $refinancing->update(['new_loan_id' => $newLoan->id]);
            $receipt = $this->createReceipt($refinancing->fresh(['member', 'originalLoan', 'newLoan']));
            $refinancing->update(['receipt_id' => $receipt->id]);

            return $refinancing;
        });

        return response()->json(['message' => 'Refinanciamiento registrado correctamente.', 'id' => $refinancing->id]);
    }

    public function show(LoanRefinancing $refinanciamiento)
    {
        return response()->json($this->refinancingPayload($refinanciamiento));
    }

    public function edit(LoanRefinancing $refinanciamiento)
    {
        return response()->json($this->refinancingPayload($refinanciamiento));
    }

    public function update(Request $request, LoanRefinancing $refinanciamiento)
    {
        if ($refinanciamiento->status === 'anulado') {
            return response()->json(['message' => 'No se puede editar un refinanciamiento anulado.'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string'],
            'observation' => ['nullable', 'string'],
        ], $this->messages());

        $refinanciamiento->update($data + ['updated_by' => auth()->id()]);

        return response()->json(['message' => 'Refinanciamiento actualizado correctamente.']);
    }

    public function destroy(LoanRefinancing $refinanciamiento)
    {
        return $this->annul($refinanciamiento);
    }

    public function annul(LoanRefinancing $refinanciamiento)
    {
        if ($refinanciamiento->status === 'anulado') {
            return response()->json(['message' => 'El refinanciamiento ya se encuentra anulado.'], 422);
        }

        DB::transaction(function () use ($refinanciamiento) {
            $refinanciamiento->load(['originalLoan', 'newLoan.payments', 'newLoan.installments', 'receipt']);

            if ($refinanciamiento->newLoan?->payments()->where('status', 'registrado')->exists()) {
                throw ValidationException::withMessages([
                    'new_loan_id' => ['No se puede anular este refinanciamiento porque el nuevo prestamo ya tiene cobros registrados.'],
                ]);
            }

            $refinanciamiento->newLoan?->installments()->update(['status' => 'anulado']);
            $refinanciamiento->newLoan?->update([
                'status' => 'anulado',
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $refinanciamiento->originalLoan?->update(['status' => 'desembolsado', 'updated_by' => auth()->id()]);

            foreach (($refinanciamiento->closed_installments_snapshot ?? []) as $row) {
                LoanInstallment::where('id', $row['id'])->update([
                    'paid_amount' => $row['paid_amount'],
                    'remaining_amount' => $row['remaining_amount'],
                    'status' => $row['status'],
                    'paid_at' => $row['paid_at'],
                ]);
            }

            $refinanciamiento->update([
                'status' => 'anulado',
                'annulled_by' => auth()->id(),
                'annulled_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $refinanciamiento->receipt?->update(['status' => 'anulado', 'updated_by' => auth()->id()]);
        });

        return response()->json(['message' => 'Refinanciamiento anulado correctamente.']);
    }

    public function schedule(LoanRefinancing $refinanciamiento)
    {
        $loan = $refinanciamiento->newLoan?->load(['installments' => fn ($query) => $query->orderBy('installment_number')]);

        return response()->json([
            'refinancing' => $this->refinancingPayload($refinanciamiento),
            'installments' => $loan ? $loan->installments->map(fn ($item) => $this->installmentPayload($item))->values() : [],
        ]);
    }

    public function print(LoanRefinancing $refinanciamiento)
    {
        $refinanciamiento->load(['member', 'originalLoan', 'newLoan.installments', 'creator']);

        return view('admin.loan-refinancings.print', ['refinancing' => $refinanciamiento]);
    }

    public function pdf(LoanRefinancing $refinanciamiento)
    {
        $refinanciamiento->load(['member', 'originalLoan', 'newLoan.installments', 'creator']);

        return Pdf::loadView('admin.loan-refinancings.print', ['refinancing' => $refinanciamiento, 'pdfMode' => true])
            ->setPaper('a4', 'portrait')
            ->stream('Constancia ' . $refinanciamiento->code . '.pdf');
    }

    private function validatedData(Request $request, bool $requireReason = true): array
    {
        return $request->validate([
            'original_loan_id' => ['required', 'integer', Rule::exists('loans', 'id')],
            'refinancing_date' => ['required', 'date'],
            'additional_amount' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'first_payment_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => [$requireReason ? 'required' : 'nullable', 'string'],
            'observation' => ['nullable', 'string'],
        ], $this->messages());
    }

    private function calculationPayload(float $previousBalance, array $data): array
    {
        $additional = round((float) ($data['additional_amount'] ?? 0), 2);
        $newAmount = round($previousBalance + $additional, 2);

        if ($previousBalance <= 0) {
            throw ValidationException::withMessages(['previous_balance' => ['El saldo pendiente debe ser mayor a cero.']]);
        }

        $calculation = $this->calculator->calculate($newAmount, (float) $data['interest_rate'], (int) $data['term_months'], $data['start_date'], $data['first_payment_date'] ?? null);

        return [
            'previous_balance' => $previousBalance,
            'additional_amount' => $additional,
            'new_amount' => $newAmount,
            'summary' => $calculation['summary'],
            'summary_formatted' => $this->formatSummary($calculation['summary']),
            'installments' => $calculation['installments'],
        ];
    }

    private function ensureLoanCanRefinance(Loan $loan): void
    {
        $loan->loadMissing('installments');

        if ($loan->status === 'refinanciado') {
            throw ValidationException::withMessages(['original_loan_id' => ['Este prestamo ya fue refinanciado o no esta disponible.']]);
        }

        if ($loan->status !== 'desembolsado') {
            throw ValidationException::withMessages(['original_loan_id' => ['Solo se pueden refinanciar prestamos desembolsados.']]);
        }

        if ($this->refinancingBalance($loan) <= 0) {
            throw ValidationException::withMessages(['original_loan_id' => ['El prestamo seleccionado no tiene saldo pendiente.']]);
        }

        if ($this->refinanceableInstallments($loan)->isEmpty()) {
            throw ValidationException::withMessages(['original_loan_id' => ['El prestamo no tiene cuotas pendientes para refinanciar.']]);
        }
    }

    private function closedInstallmentsSnapshot(Loan $loan): array
    {
        return $this->refinanceableInstallments($loan)
            ->map(fn (LoanInstallment $item) => [
                'id' => $item->id,
                'installment_number' => $item->installment_number,
                'due_date' => optional($item->due_date)->format('Y-m-d'),
                'paid_amount' => (float) $item->paid_amount,
                'remaining_amount' => (float) $item->remaining_amount,
                'status' => $item->status,
                'was_overdue' => $this->isOverdueInstallment($item),
                'paid_at' => optional($item->paid_at)->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    private function installmentRows(array $installments): array
    {
        return collect($installments)->map(fn ($row) => array_merge($row, [
            'paid_amount' => 0,
            'remaining_amount' => $row['installment_amount'],
            'status' => 'pendiente',
        ]))->all();
    }

    private function createReceipt(LoanRefinancing $refinancing): Receipt
    {
        $receipt = Receipt::create([
            'receipt_number' => $this->generateNextReceiptNumber(),
            'receipt_date' => $refinancing->refinancing_date,
            'member_id' => $refinancing->member_id,
            'type' => 'refinanciamiento',
            'amount' => $refinancing->new_amount,
            'related_type' => LoanRefinancing::class,
            'related_id' => $refinancing->id,
            'observation' => $refinancing->reason,
            'status' => 'registrado',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $receipt;
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()->whereNotNull('receipt_number')->where('receipt_number', 'like', 'REC-%')->orderByDesc('id')->lockForUpdate()->value('receipt_number');
        $lastNumber = $lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches) ? (int) $matches[1] : 0;

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function refinancingPayload(LoanRefinancing $refinancing): array
    {
        $refinancing->load(['member', 'originalLoan.installments', 'newLoan.installments', 'creator', 'receipt']);

        return [
            'id' => $refinancing->id,
            'code' => $refinancing->code,
            'refinancing_date' => optional($refinancing->refinancing_date)->format('Y-m-d'),
            'refinancing_date_formatted' => optional($refinancing->refinancing_date)->format('d/m/Y'),
            'member_name' => $refinancing->member?->full_name,
            'member_dni' => $refinancing->member?->dni,
            'original_loan_number' => $refinancing->originalLoan?->loan_number,
            'new_loan_number' => $refinancing->newLoan?->loan_number,
            'previous_balance_formatted' => $this->money($refinancing->previous_balance),
            'additional_amount_formatted' => $this->money($refinancing->additional_amount),
            'new_amount_formatted' => $this->money($refinancing->new_amount),
            'interest_rate_formatted' => number_format((float) $refinancing->interest_rate, 2) . '% mensual',
            'term_months_formatted' => $refinancing->term_months . ' meses',
            'total_interest_formatted' => $this->money($refinancing->total_interest),
            'total_amount_formatted' => $this->money($refinancing->total_amount),
            'reason' => $refinancing->reason,
            'observation' => $refinancing->observation,
            'status' => $refinancing->status,
            'status_label' => $refinancing->status === 'anulado' ? 'Anulado' : 'Registrado',
            'created_by_name' => $refinancing->creator?->name,
            'created_at' => optional($refinancing->created_at)->format('d/m/Y H:i'),
            'print_url' => route('admin.refinanciamientos.print', $refinancing),
            'pdf_url' => route('admin.refinanciamientos.pdf', $refinancing),
            'closed_installments' => collect($refinancing->closed_installments_snapshot ?? [])->values(),
            'new_installments' => $refinancing->newLoan?->installments->sortBy('installment_number')->map(fn ($item) => $this->installmentPayload($item))->values() ?? [],
        ];
    }

    private function loanPayload(Loan $loan): array
    {
        $loan->loadMissing('installments');

        $refinanceableInstallments = $this->refinanceableInstallments($loan);
        $overdueInstallments = $loan->installments->filter(fn (LoanInstallment $item) => $this->isOverdueInstallment($item));
        $paidInstallments = $loan->installments->where('status', 'pagado');
        $pendingBalance = $this->refinancingBalance($loan);
        $oldestOverdue = $overdueInstallments->sortBy('due_date')->first();
        $overdueLabel = $overdueInstallments->count() > 0
            ? $overdueInstallments->count() . ' cuotas vencidas'
            : 'Al dia';

        return [
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'approved_amount' => number_format((float) $loan->approved_amount, 2, '.', ''),
            'approved_amount_formatted' => $this->money($loan->approved_amount),
            'current_balance' => number_format($pendingBalance, 2, '.', ''),
            'current_balance_formatted' => $this->money($pendingBalance),
            'loan_current_balance_formatted' => $this->money($loan->current_balance),
            'total_paid_formatted' => $this->money($paidInstallments->sum(fn ($item) => (float) $item->paid_amount)),
            'total_pending_formatted' => $this->money($pendingBalance),
            'status' => $loan->status,
            'status_label' => ucfirst($loan->status),
            'pending_installments' => $refinanceableInstallments->count(),
            'paid_installments' => $paidInstallments->count(),
            'overdue_installments' => $overdueInstallments->count(),
            'oldest_overdue_date' => $oldestOverdue ? optional($oldestOverdue->due_date)->format('d/m/Y') : null,
            'has_overdue' => $overdueInstallments->isNotEmpty(),
            'overdue_label' => $overdueLabel,
            'option_label' => $loan->loan_number . ' - Saldo: ' . $this->money($pendingBalance) . ' - ' . $overdueLabel,
        ];
    }

    private function installmentPayload(LoanInstallment $installment): array
    {
        return [
            'installment_number' => $installment->installment_number,
            'due_date' => optional($installment->due_date)->format('d/m/Y'),
            'opening_balance' => $this->money($installment->opening_balance),
            'principal_amount' => $this->money($installment->principal_amount),
            'interest_amount' => $this->money($installment->interest_amount),
            'installment_amount' => $this->money($installment->installment_amount),
            'closing_balance' => $this->money($installment->closing_balance),
            'status_label' => ucfirst($installment->status),
            'is_overdue' => $this->isOverdueInstallment($installment),
        ];
    }

    private function canLoanBeRefinanced(Loan $loan): bool
    {
        return $loan->status === 'desembolsado'
            && $this->refinancingBalance($loan) > 0
            && $this->refinanceableInstallments($loan)->isNotEmpty();
    }

    private function refinancingBalance(Loan $loan): float
    {
        $loan->loadMissing('installments');

        $installmentBalance = $this->refinanceableInstallments($loan)
            ->sum(fn (LoanInstallment $item) => (float) $item->remaining_amount);

        return round($installmentBalance > 0 ? $installmentBalance : (float) $loan->current_balance, 2);
    }

    private function refinanceableInstallments(Loan $loan): Collection
    {
        $loan->loadMissing('installments');

        return $loan->installments
            ->filter(fn (LoanInstallment $item) => $this->isRefinanceableInstallment($item))
            ->values();
    }

    private function isRefinanceableInstallment(LoanInstallment $installment): bool
    {
        if ((float) $installment->remaining_amount <= 0) {
            return false;
        }

        if (in_array($installment->status, ['pagado', 'anulado', 'refinanciado'], true)) {
            return false;
        }

        return in_array($installment->status, ['pendiente', 'parcial', 'vencido'], true)
            || $this->isOverdueInstallment($installment);
    }

    private function isOverdueInstallment(LoanInstallment $installment): bool
    {
        return $installment->due_date
            && $installment->due_date->lt(today())
            && ! in_array($installment->status, ['pagado', 'anulado', 'refinanciado'], true)
            && (float) $installment->remaining_amount > 0;
    }

    private function refinanceableInstallmentQuery($query)
    {
        return $query
            ->where('remaining_amount', '>', 0)
            ->whereNotIn('status', ['pagado', 'anulado', 'refinanciado'])
            ->where(function ($subQuery) {
                $subQuery->whereIn('status', ['pendiente', 'parcial', 'vencido'])
                    ->orWhereDate('due_date', '<', today());
            });
    }

    private function overdueInstallmentQuery($query)
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->where('remaining_amount', '>', 0)
            ->whereNotIn('status', ['pagado', 'anulado', 'refinanciado']);
    }

    private function overdueCountForRefinancing(LoanRefinancing $refinancing): string
    {
        $fromSnapshot = collect($refinancing->closed_installments_snapshot ?? [])
            ->filter(fn ($row) => ($row['was_overdue'] ?? false) || ($row['status'] ?? null) === 'vencido')
            ->count();

        if ($fromSnapshot > 0) {
            return (string) $fromSnapshot;
        }

        $loan = $refinancing->originalLoan;

        if (! $loan) {
            return '0';
        }

        $loan->loadMissing('installments');

        return (string) $loan->installments
            ->filter(fn (LoanInstallment $item) => $item->due_date && $item->due_date->lt(today()) && (float) $item->remaining_amount > 0 && ! in_array($item->status, ['pagado', 'anulado'], true))
            ->count();
    }

    private function historicalOverdueInstallmentQuery($query)
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->where('remaining_amount', '>', 0)
            ->whereNotIn('status', ['pagado', 'anulado']);
    }

    private function formatSummary(array $summary): array
    {
        return [
            'fixed_principal' => $this->money($summary['fixed_principal']),
            'total_interest' => $this->money($summary['total_interest']),
            'total_payment' => $this->money($summary['total_payment']),
            'first_installment' => $this->money($summary['first_installment']),
            'last_installment' => $this->money($summary['last_installment']),
            'first_payment_date' => $summary['first_payment_date'],
        ];
    }

    private function statusBadge(?string $status): string
    {
        return '<span class="badge badge-' . ($status === 'anulado' ? 'danger' : 'success') . '">' . e($status === 'anulado' ? 'Anulado' : 'Registrado') . '</span>';
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function messages(): array
    {
        return [
            'original_loan_id.required' => 'Seleccione un prestamo valido.',
            'original_loan_id.exists' => 'El prestamo seleccionado no es valido.',
            'refinancing_date.required' => 'La fecha de refinanciamiento es obligatoria.',
            'refinancing_date.date' => 'La fecha de refinanciamiento debe ser valida.',
            'additional_amount.min' => 'El monto adicional no puede ser negativo.',
            'interest_rate.required' => 'La tasa de interes es obligatoria.',
            'interest_rate.min' => 'La tasa de interes no puede ser negativa.',
            'term_months.required' => 'El plazo es obligatorio.',
            'term_months.min' => 'El plazo debe ser como minimo de 1 mes.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'first_payment_date.after_or_equal' => 'La fecha de primera cuota no puede ser menor a la fecha de inicio.',
            'reason.required' => 'El motivo del refinanciamiento es obligatorio.',
        ];
    }
}
