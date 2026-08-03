<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanSimulation;
use App\Models\Member;
use App\Models\Receipt;
use App\Services\LoanSimulationCalculator;
use App\Services\LoanEligibilityService;
use App\Services\ShareCashMovementService;
use App\Services\MemberCreditHistoryService;
use App\Services\CreditHistoryService;
use App\Services\LateFeeService;
use App\Models\LoanInstallment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LoanController extends Controller
{
    public function __construct(private readonly LoanSimulationCalculator $calculator, private readonly LoanEligibilityService $eligibility, private readonly MemberCreditHistoryService $creditHistory, private readonly CreditHistoryService $creditHistoryManager, private readonly LateFeeService $lateFees)
    {
        $this->middleware('can:admin.prestamos.index')->only(['index', 'list', 'summary', 'nextCode']);
        $this->middleware('can:admin.prestamos.create')->only(['pendingSimulations']);
        $this->middleware('can:admin.prestamos.create')->only(['store', 'calculate']);
        $this->middleware('can:admin.prestamos.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.prestamos.show')->only(['show']);
        $this->middleware('can:admin.prestamos.delete')->only(['destroy']);
        $this->middleware('can:admin.prestamos.approve')->only(['approve']);
        $this->middleware('can:admin.prestamos.disburse')->only(['disburse', 'cashBalance']);
        $this->middleware('can:admin.prestamos.annul')->only(['annul']);
        $this->middleware('can:admin.prestamos.schedule')->only(['schedule']);
        $this->middleware('can:admin.prestamos.schedule_print')->only(['schedulePrint']);
        $this->middleware('can:admin.prestamos.schedule_pdf')->only(['schedulePdf']);
        $this->middleware('can:admin.prestamos.disbursement_receipt')->only(['disbursementReceipt', 'disbursementReceiptPdf']);
        $this->middleware('can:admin.prestamos.disbursement_voucher')->only(['disbursementVoucher']);
    }

    public function index()
    {
        $members = Member::query()
            ->availableForLoanOperations()
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name', 'birth_date']);

        $guarantors = Member::query()
            ->eligibleGuarantors()
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name']);

        $simulations = class_exists(LoanSimulation::class)
            ? LoanSimulation::query()
                ->with('member')
                ->where('status', 'simulada')
                ->whereHas('member', fn ($query) => $query->availableForLoanOperations())
                ->latest('id')
                ->get()
            : collect();

        return view('admin.loans.index', [
            'members' => $members,
            'guarantors' => $guarantors,
            'simulations' => $simulations,
            'nextCode' => $this->generateNextCode(),
        ]);
    }

    public function list(Request $request)
    {
        $loans = Loan::with('member')
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('start_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('start_date', '<=', $request->input('date_to')))
            ->orderByDesc('id');

        return DataTables::of($loans)
            ->addIndexColumn()
            ->addColumn('member_name', fn (Loan $loan) => $loan->member?->full_name ?? '-')
            ->addColumn('member_dni', fn (Loan $loan) => $loan->member?->dni ?? '-')
            ->addColumn('date', fn (Loan $loan) => optional($loan->start_date)->format('d/m/Y') ?? '-')
            ->editColumn('approved_amount', fn (Loan $loan) => 'S/ ' . number_format((float) $loan->approved_amount, 2))
            ->editColumn('interest_rate', fn (Loan $loan) => number_format((float) $loan->interest_rate, 2) . '%')
            ->editColumn('term_months', fn (Loan $loan) => $loan->term_months . ' meses')
            ->editColumn('current_balance', fn (Loan $loan) => 'S/ ' . number_format((float) $loan->current_balance, 2))
            ->editColumn('status', fn (Loan $loan) => $this->statusBadge($loan->status))
            ->addColumn('acciones', fn (Loan $loan) => view('admin.loans.partials.acciones', compact('loan'))->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'total_approved' => number_format((float) Loan::whereNotIn('status', ['anulado'])->sum('approved_amount'), 2),
            'pending' => Loan::where('status', 'pendiente')->count(),
            'disbursed' => Loan::where('status', 'desembolsado')->count(),
            'receivable' => number_format((float) Loan::where('status', 'desembolsado')->sum('current_balance'), 2),
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => $this->generateNextCode()]);
    }

    public function pendingSimulations(Member $member)
    {
        $this->ensureActiveMember($member);

        return response()->json(['simulations' => $member->loanSimulations()
            ->where('status', 'simulada')
            ->orderByDesc('simulation_date')
            ->get()
            ->map(fn (LoanSimulation $simulation) => [
                'id' => $simulation->id,
                'code' => $simulation->code,
                'simulation_date' => optional($simulation->simulation_date)->format('d/m/Y'),
                'amount' => number_format((float) $simulation->amount, 2, '.', ''),
                'amount_formatted' => 'S/ ' . number_format((float) $simulation->amount, 2),
                'interest_rate' => number_format((float) $simulation->interest_rate, 2, '.', ''),
                'term_months' => $simulation->term_months,
                'total_payment_formatted' => 'S/ ' . number_format((float) $simulation->total_payment, 2),
            ])->values()]);
    }

    public function calculate(Request $request)
    {
        $data = $this->validatedData($request, false);

        $evaluation = $this->eligibility->evaluate(Member::findOrFail($data['member_id']), (float) $data['approved_amount'], $data['guarantor_member_id'] ?? null);
        $evaluation['credit_history'] = $this->creditHistory->evaluate(Member::findOrFail($data['member_id']));
        $payload = $this->calculationPayload($data);
        $payload['eligibility'] = $evaluation;
        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $loan = DB::transaction(function () use ($data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $simulation = $this->simulationForConversion($data['loan_simulation_id'] ?? null, null, $member->id);
            $this->ensureActiveMember($member);
            if (! $simulation && LoanSimulation::where('member_id', $member->id)->where('status', 'simulada')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['loan_simulation_id' => ['Este socio tiene simulaciones pendientes. Tome una simulacion o dejela sin efecto antes de registrar un prestamo directo.']]);
            }
            if ($simulation) $this->applySimulationData($data, $simulation);
            $evaluation = $this->eligibility->validate($member, (float) $data['approved_amount'], $data['guarantor_member_id'] ?? null);

            $data['loan_number'] = $this->generateNextCode();
            $this->normalizeLoanData($data);
            $calculation = $this->calculateGermanFromData($data);

            $data = array_merge($data, [
                ...$this->eligibility->snapshot($evaluation),
                'first_payment_date' => $calculation['summary']['first_payment_date'],
                'fixed_principal' => $calculation['summary']['fixed_principal'],
                'total_interest' => $calculation['summary']['total_interest'],
                'total_amount' => $calculation['summary']['total_payment'],
                'current_balance' => $calculation['summary']['total_payment'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $loan = Loan::create($data);
            $loan->installments()->createMany($this->installmentRows($calculation['installments']));
            $this->markSimulationAsConverted($simulation, $loan);

            return $loan;
        });

        $this->creditHistoryManager->recalculate($loan->member_id);

        return response()->json(['message' => 'Prestamo registrado correctamente.', 'id' => $loan->id]);
    }

    public function show(Loan $loan)
    {
        return response()->json($this->loanPayload($loan));
    }

    public function edit(Loan $loan)
    {
        return response()->json($this->loanPayload($loan));
    }

    public function update(Request $request, Loan $loan)
    {
        $this->eligibility->assertCanRequestLoan($loan->member);

        if ($loan->status === 'desembolsado') {
            $loan->update($request->validate([
                'observation' => ['nullable', 'string'],
            ], $this->messages()) + ['updated_by' => auth()->id()]);

            return response()->json(['message' => 'Observacion del prestamo actualizada correctamente.']);
        }

        if (! in_array($loan->status, ['pendiente', 'aprobado'], true)) {
            return response()->json(['message' => 'El prestamo no se puede editar en su estado actual.'], 422);
        }

        $data = $this->validatedData($request, true, $loan);

        DB::transaction(function () use ($loan, $data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $this->ensureActiveMember($member);
            $oldSimulationId = $loan->loan_simulation_id;
            $newSimulationId = $data['loan_simulation_id'] ?? null;
            $simulation = $newSimulationId !== $oldSimulationId
                ? $this->simulationForConversion($newSimulationId, $loan, $member->id)
                : null;
            if ($simulation) $this->applySimulationData($data, $simulation);
            $evaluation = $this->eligibility->validate($member, (float) $data['approved_amount'], $data['guarantor_member_id'] ?? null);

            $this->normalizeLoanData($data);
            unset($data['loan_number']);
            $calculation = $this->calculateGermanFromData($data);

            $data = array_merge($data, [
                ...$this->eligibility->snapshot($evaluation),
                'first_payment_date' => $calculation['summary']['first_payment_date'],
                'fixed_principal' => $calculation['summary']['fixed_principal'],
                'total_interest' => $calculation['summary']['total_interest'],
                'total_amount' => $calculation['summary']['total_payment'],
                'current_balance' => $calculation['summary']['total_payment'],
                'updated_by' => auth()->id(),
            ]);

            $loan->update($data);
            $loan->installments()->delete();
            $loan->installments()->createMany($this->installmentRows($calculation['installments']));
            $this->releaseConvertedSimulation($oldSimulationId, $loan, $newSimulationId);
            $this->markSimulationAsConverted($simulation, $loan);
        });

        $this->creditHistoryManager->recalculate($loan->member_id);

        return response()->json(['message' => 'Prestamo actualizado correctamente.']);
    }

    public function destroy(Loan $loan)
    {
        return $this->annul($loan);
    }

    public function approve(Loan $loan)
    {
        if ($loan->status !== 'pendiente') {
            return response()->json(['message' => 'Solo se pueden aprobar prestamos pendientes.'], 422);
        }

        try {
            $evaluation = $this->eligibility->validate($loan->member, (float) $loan->approved_amount, $loan->guarantor_member_id);
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first() ?: 'No se puede aprobar el prestamo porque el socio no cumple las reglas vigentes.', 'errors' => $exception->errors()], 422);
        }

        $loan->update([
            'status' => 'aprobado',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->creditHistoryManager->recalculate($loan->member_id);

        return response()->json(['message' => 'Prestamo aprobado correctamente.']);
    }

    public function cashBalance()
    {
        return response()->json(['balance' => number_format($this->currentCashBalance(), 2)]);
    }

    public function disburse(Request $request, Loan $loan)
    {
        $this->normalizeNullableRequestFields($request);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'cheque', 'otro'])],
            'reference' => [
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['yape', 'plin', 'transferencia', 'cheque', 'otro'], true)),
                'nullable',
                'string',
                'max:100',
            ],
            'disbursed_at' => ['required', 'date'],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], $this->messages());

        if ($loan->status === 'desembolsado') {
            return response()->json(['message' => 'El prestamo ya fue desembolsado.'], 422);
        }

        if ($loan->status !== 'aprobado') {
            return response()->json(['message' => 'Solo se pueden desembolsar prestamos aprobados.'], 422);
        }

        try {
            $this->eligibility->validate($loan->member, (float) $loan->approved_amount, $loan->guarantor_member_id);
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first() ?: 'No se puede desembolsar el prestamo porque el socio no cumple las reglas vigentes.', 'errors' => $exception->errors()], 422);
        }

        $voucherPath = null;

        try {
            DB::transaction(function () use ($loan, $data, $request, &$voucherPath) {
                $amount = (float) $loan->approved_amount;
                $disbursedAt = \Carbon\Carbon::parse($data['disbursed_at']);

                if ($this->currentCashBalance() < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => ['No hay saldo suficiente en caja para realizar este desembolso.'],
                    ]);
                }

                $voucherPath = $request->hasFile('voucher_path')
                    ? $request->file('voucher_path')->store('loan-disbursements', 'public')
                    : null;

                $movement = CashMovement::query()
                    ->where('related_type', Loan::class)
                    ->where('related_id', $loan->id)
                    ->where('category', 'desembolso_prestamo')
                    ->lockForUpdate()
                    ->first();

                if (! $movement) {
                    $movement = CashMovement::create([
                        'movement_number' => CashMovement::nextCode(),
                        'movement_date' => $disbursedAt->toDateString(),
                        'type' => 'egreso',
                        'category' => 'desembolso_prestamo',
                        'concept' => 'Desembolso de prestamo ' . $loan->loan_number . ' al socio ' . ($loan->member?->full_name ?? ''),
                        'amount' => $amount,
                        'payment_method' => $data['payment_method'],
                        'reference' => $data['reference'] ?? null,
                        'voucher_path' => $voucherPath,
                        'related_type' => Loan::class,
                        'related_id' => $loan->id,
                        'status' => 'registrado',
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }

                $receipt = Receipt::create([
                    'receipt_number' => $this->generateNextReceiptNumber(),
                    'receipt_date' => $disbursedAt->toDateString(),
                    'member_id' => $loan->member_id,
                    'type' => 'desembolso_prestamo',
                    'amount' => $amount,
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $data['reference'] ?? null,
                    'voucher_path' => $voucherPath,
                    'related_type' => Loan::class,
                    'related_id' => $loan->id,
                    'observation' => $loan->observation,
                    'status' => 'registrado',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $loan->update([
                    'status' => 'desembolsado',
                    'disbursed_amount' => $amount,
                    'disbursed_at' => $disbursedAt,
                    'disbursed_by' => auth()->id(),
                    'disbursement_payment_method' => $data['payment_method'],
                    'disbursement_reference' => $data['reference'] ?? null,
                    'disbursement_voucher_path' => $voucherPath,
                    'disbursement_receipt_id' => $receipt->id,
                    'current_balance' => $loan->total_amount,
                    'updated_by' => auth()->id(),
                ]);

                app(ShareCashMovementService::class)->recalculateBalances();
            });
        } catch (\Throwable $exception) {
            if ($voucherPath) {
                Storage::disk('public')->delete($voucherPath);
            }

            throw $exception;
        }

        $this->creditHistoryManager->recalculate($loan->member_id);

        return response()->json(['message' => 'Prestamo desembolsado correctamente.']);
    }

    public function annul(Loan $loan)
    {
        if ($loan->status === 'desembolsado') {
            return response()->json(['message' => 'No se puede anular directamente un prestamo desembolsado sin reverso de caja.'], 422);
        }

        if ($loan->status === 'anulado') {
            return response()->json(['message' => 'El prestamo ya se encuentra anulado.'], 422);
        }

        $loan->update([
            'status' => 'anulado',
            'updated_by' => auth()->id(),
            'annulled_by' => auth()->id(),
            'annulled_at' => now(),
        ]);

        $loan->installments()->update(['status' => 'anulado']);

        $this->creditHistoryManager->recalculate($loan->member_id);

        return response()->json(['message' => 'Prestamo anulado correctamente.']);
    }

    public function schedule(Loan $loan)
    {
        return response()->json([
            'loan' => $this->loanPayload($loan),
            'installments' => $this->formatInstallments($loan->installments()->orderBy('installment_number')->get()->toArray()),
        ]);
    }

    public function schedulePrint(Loan $loan)
    {
        $loan->load(['member', 'approver', 'disburser', 'installments' => fn ($query) => $query->orderBy('installment_number'), 'payments' => fn ($query) => $query->where('status', 'registrado')->with('creator')]);

        return view('admin.loans.schedule-print', [
            'loan' => $loan,
            'generatedBy' => auth()->user()?->name ?? '-',
            'generatedAt' => now(),
            'financialSummary' => $this->financialClosureSummary($loan),
        ]);
    }

    public function schedulePdf(Loan $loan)
    {
        $loan->load(['member', 'approver', 'disburser', 'installments' => fn ($query) => $query->orderBy('installment_number'), 'payments' => fn ($query) => $query->where('status', 'registrado')->with('creator')]);
        $generatedAt = now();
        $generatedBy = auth()->user()?->name ?? '-';
        $financialSummary = $this->financialClosureSummary($loan);

        return Pdf::loadView('admin.loans.schedule-pdf', compact('loan', 'generatedAt', 'generatedBy', 'financialSummary'))
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', true)
            ->stream('Cronograma ' . $loan->loan_number . '.pdf');
    }

    public function disbursementReceipt(Loan $loan)
    {
        $loan->load(['member', 'disburser', 'disbursementReceipt']);

        abort_unless($loan->disbursementReceipt, 404);

        return view('admin.loans.disbursement-receipt', [
            'loan' => $loan,
            'receipt' => $loan->disbursementReceipt,
        ]);
    }

    public function disbursementReceiptPdf(Loan $loan)
    {
        $loan->load('disbursementReceipt');
        abort_unless($loan->disbursementReceipt, 404);

        return redirect()->route('admin.recibos.pdf', $loan->disbursementReceipt);
    }

    public function disbursementVoucher(Loan $loan)
    {
        abort_unless($loan->disbursement_voucher_path && Storage::disk('public')->exists($loan->disbursement_voucher_path), 404);

        return Storage::disk('public')->download($loan->disbursement_voucher_path);
    }

    public function viewDisbursementVoucher(Loan $loan)
    {
        abort_unless($loan->disbursement_voucher_path && Storage::disk('public')->exists($loan->disbursement_voucher_path), 404);

        $disk = Storage::disk('public');

        return response()->file($disk->path($loan->disbursement_voucher_path), [
            'Content-Type' => $disk->mimeType($loan->disbursement_voucher_path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($loan->disbursement_voucher_path) . '"',
        ]);
    }

    private function validatedData(Request $request, bool $withStatus = true, ?Loan $loan = null): array
    {
        $this->normalizeNullableRequestFields($request);

        $rules = [
            'loan_simulation_id' => ['nullable', 'integer', Rule::exists('loan_simulations', 'id')],
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'guarantor_member_id' => ['nullable', 'integer', 'different:member_id', Rule::exists('members', 'id')],
            'requested_amount' => ['required', 'numeric', 'min:0.01'],
            'approved_amount' => ['required', 'numeric', 'min:0.01', 'lte:requested_amount'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'interest_type' => ['nullable', Rule::in(['mensual'])],
            'term_months' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'first_payment_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_frequency' => ['nullable', Rule::in(['mensual'])],
            'amortization_method' => ['required', Rule::in(['aleman'])],
            'purpose' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string'],
        ];

        if ($withStatus) {
            $rules['status'] = ['required', Rule::in(['pendiente', 'aprobado', 'desembolsado', 'pagado', 'refinanciado', 'anulado'])];
        }

        return $request->validate($rules, $this->messages());
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['loan_simulation_id', 'guarantor_member_id', 'first_payment_date', 'purpose', 'observation', 'reference', 'disbursed_at'];
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

    private function normalizeLoanData(array &$data): void
    {
        $data['requested_amount'] = (float) $data['requested_amount'];
        $data['approved_amount'] = (float) $data['approved_amount'];
        $data['interest_rate'] = (float) $data['interest_rate'];
        $data['interest_type'] = $data['interest_type'] ?? 'mensual';
        $data['payment_frequency'] = $data['payment_frequency'] ?? 'mensual';
        $data['term_months'] = (int) $data['term_months'];
        $data['amortization_method'] = 'aleman';
        $data['status'] = $data['status'] ?? 'pendiente';
    }

    private function calculateGermanFromData(array $data): array
    {
        return $this->calculator->calculate(
            (float) $data['approved_amount'],
            (float) $data['interest_rate'],
            (int) $data['term_months'],
            $data['start_date'],
            $data['first_payment_date'] ?? null
        );
    }

    private function installmentRows(array $installments): array
    {
        return collect($installments)->map(fn ($row) => array_merge($row, [
            'paid_amount' => 0,
            'remaining_amount' => $row['installment_amount'],
            'status' => 'pendiente',
        ]))->all();
    }

    private function calculationPayload(array $data): array
    {
        $this->normalizeLoanData($data);
        $calculation = $this->calculateGermanFromData($data);

        return [
            'summary' => $this->formatSummary($calculation['summary'], (float) $data['approved_amount']),
            'installments' => $this->formatInstallments($this->installmentRows($calculation['installments'])),
        ];
    }

    private function loanPayload(Loan $loan): array
    {
        $loan->load(['member', 'guarantorMember', 'creator', 'approver', 'disburser', 'disbursementReceipt', 'installments' => fn ($query) => $query->orderBy('installment_number'), 'payments' => fn ($query) => $query->where('status', 'registrado')->with(['creator', 'receipt'])]);
        $collectibleInstallments = $loan->installments->filter(fn ($row) => ! in_array($row->status, ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'], true));
        $pendingCapital = round((float) $collectibleInstallments->sum(fn ($row) => max(0, (float) $row->principal_amount - (float) $row->capital_paid)), 2);
        $estimatedInterest = round(max(0, (float) $loan->current_balance - $pendingCapital), 2);
        $lateQuotes = $collectibleInstallments->map(fn ($row) => $this->lateFees->quote($row, today()));
        $lateFeePending = round((float) $lateQuotes->sum('pending'), 2);
        $overdueCount = $lateQuotes->where('days', '>', 0)->count();
        $financialSummary = $this->financialClosureSummary($loan);

        return [
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'loan_simulation_id' => $loan->loan_simulation_id,
            'member_id' => $loan->member_id,
            'guarantor_member_id' => $loan->guarantor_member_id,
            'guarantor_member_name' => $loan->guarantorMember?->full_name,
            'guarantor_member_dni' => $loan->guarantorMember?->dni,
            'evaluation' => array_merge([
                'member_type' => $loan->member_type_at_evaluation,
                'admission_date' => optional($loan->member?->admission_date)->format('d/m/Y'),
                'contribution_count' => $loan->member_contribution_count_at_evaluation,
                'total_contributions' => $loan->member_total_contributions_at_evaluation,
                'loan_limit_without_guarantor' => $loan->loan_limit_without_guarantor,
                'requires_guarantor' => $loan->requires_guarantor,
                'guarantor_requirement_reason' => $loan->guarantor_requirement_reason,
                'guarantor_total_contributions' => $loan->guarantor_total_contributions_at_evaluation,
                'credit_history' => $loan->member ? $this->creditHistory->evaluate($loan->member) : null,
            ], $loan->member ? $this->eligibility->loanEligibilitySummary($loan->member) : []),
            'member_name' => $loan->member?->full_name,
            'member_code' => $loan->member?->code,
            'member_dni' => $loan->member?->dni,
            'requested_amount' => number_format((float) $loan->requested_amount, 2, '.', ''),
            'requested_amount_formatted' => 'S/ ' . number_format((float) $loan->requested_amount, 2),
            'approved_amount' => number_format((float) $loan->approved_amount, 2, '.', ''),
            'approved_amount_formatted' => 'S/ ' . number_format((float) $loan->approved_amount, 2),
            'interest_rate' => number_format((float) $loan->interest_rate, 2, '.', ''),
            'interest_rate_formatted' => number_format((float) $loan->interest_rate, 2) . '% mensual',
            'term_months' => $loan->term_months,
            'term_months_formatted' => $loan->term_months . ' meses',
            'start_date' => optional($loan->start_date)->format('Y-m-d'),
            'start_date_formatted' => optional($loan->start_date)->format('d/m/Y'),
            'first_payment_date' => optional($loan->first_payment_date)->format('Y-m-d'),
            'first_payment_date_formatted' => optional($loan->first_payment_date)->format('d/m/Y'),
            'amortization_method' => $loan->amortization_method,
            'fixed_principal_formatted' => 'S/ ' . number_format((float) $loan->fixed_principal, 2),
            'total_interest_formatted' => 'S/ ' . number_format((float) $loan->total_interest, 2),
            'total_amount_formatted' => 'S/ ' . number_format((float) $loan->total_amount, 2),
            'current_balance_formatted' => 'S/ ' . number_format((float) $loan->current_balance, 2),
            'pending_capital' => number_format($pendingCapital, 2, '.', ''),
            'pending_capital_formatted' => 'S/ ' . number_format($pendingCapital, 2),
            'estimated_future_interest' => number_format($estimatedInterest, 2, '.', ''),
            'estimated_future_interest_formatted' => 'S/ ' . number_format($estimatedInterest, 2),
            'estimated_collectible_balance_formatted' => 'S/ ' . number_format((float) $loan->current_balance, 2),
            'late_fee_pending' => number_format($lateFeePending, 2, '.', ''),
            'late_fee_pending_formatted' => 'S/ ' . number_format($lateFeePending, 2),
            'overdue_installments' => $overdueCount,
            'max_late_days' => (int) ($lateQuotes->max('days') ?? 0),
            'real_total_pending_formatted' => 'S/ ' . number_format((float) $loan->current_balance + $lateFeePending, 2),
            'first_installment_formatted' => 'S/ ' . number_format((float) ($loan->installments->first()?->installment_amount ?? 0), 2),
            'last_installment_formatted' => 'S/ ' . number_format((float) ($loan->installments->last()?->installment_amount ?? 0), 2),
            'purpose' => $loan->purpose,
            'observation' => $loan->observation,
            'status' => $loan->status,
            'status_label' => $this->statusLabel($loan->status),
            'approved_at' => optional($loan->approved_at)->format('d/m/Y H:i'),
            'disbursed_at' => optional($loan->disbursed_at)->format('d/m/Y H:i'),
            'disbursement_payment_method' => $loan->disbursement_payment_method,
            'disbursement_payment_method_label' => $this->paymentMethodLabel($loan->disbursement_payment_method),
            'disbursement_reference' => $loan->disbursement_reference,
            'disbursement_voucher_path' => $loan->disbursement_voucher_path,
            'disbursement_voucher_url' => $loan->disbursement_voucher_path ? route('admin.prestamos.disbursement.voucher', $loan) : null,
            'disbursement_voucher_view_url' => $loan->disbursement_voucher_path ? route('admin.prestamos.disbursement.voucher.view', $loan) : null,
            'disbursement_voucher_name' => $loan->disbursement_voucher_path ? basename($loan->disbursement_voucher_path) : null,
            'disbursement_voucher_type' => $loan->disbursement_voucher_path && in_array(strtolower(pathinfo($loan->disbursement_voucher_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true) ? 'image' : 'pdf',
            'disbursement_receipt_number' => $loan->disbursementReceipt?->receipt_number,
            'disbursement_receipt_url' => $loan->disbursementReceipt ? route('admin.prestamos.disbursement.receipt', $loan) : null,
            'disbursement_receipt_pdf_url' => $loan->disbursementReceipt ? route('admin.prestamos.disbursement.receipt.pdf', $loan) : null,
            'schedule_print_url' => route('admin.prestamos.schedule.print', $loan),
            'schedule_pdf_url' => route('admin.prestamos.schedule.pdf', $loan),
            'created_by_name' => $loan->creator?->name,
            'approved_by_name' => $loan->approver?->name,
            'disbursed_by_name' => $loan->disburser?->name,
            'installments' => $this->formatInstallments($loan->installments->toArray()),
            'financial_summary' => $financialSummary,
            'related_payments' => $loan->payments->sortBy('payment_date')->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_type_label' => $this->loanPaymentTypeLabel($payment->payment_type),
                'amount_formatted' => 'S/ ' . number_format((float) $payment->amount, 2),
                'payment_date_formatted' => optional($payment->payment_date)->format('d/m/Y'),
                'created_by_name' => $payment->creator?->name ?? '-',
                'created_at_formatted' => optional($payment->created_at)->format('d/m/Y H:i'),
                'show_url' => route('admin.cobros.index', ['payment_id' => $payment->id]),
                'receipt_url' => $payment->receipt ? route('admin.cobros.receipt', $payment) : null,
                'voucher_url' => $payment->voucher_path ? route('admin.cobros.voucher', $payment) : null,
            ])->values(),
        ];
    }

    private function financialClosureSummary(Loan $loan): array
    {
        $payments = $loan->relationLoaded('payments') ? $loan->payments->where('status', 'registrado') : $loan->payments()->where('status', 'registrado')->with('creator')->get();
        $liquidation = $payments->where('payment_type', 'liquidacion')->sortByDesc('payment_date')->first();
        $totalPaid = round((float) $payments->sum('amount'), 2);
        $capitalPaid = round((float) $payments->sum('capital_amount'), 2);
        $interestPaid = round((float) $payments->sum('interest_amount'), 2);
        $advanceExonerated = round((float) $payments->where('payment_type', 'adelanto_cuotas')->sum('interest_exonerated_amount'), 2);
        $liquidationExonerated = round((float) $payments->where('payment_type', 'liquidacion')->sum('interest_exonerated_amount'), 2);

        return [
            'projected_total' => (float) $loan->total_amount,
            'projected_total_formatted' => 'S/ ' . number_format((float) $loan->total_amount, 2),
            'total_paid' => $totalPaid,
            'total_paid_formatted' => 'S/ ' . number_format($totalPaid, 2),
            'capital_paid_formatted' => 'S/ ' . number_format($capitalPaid, 2),
            'interest_paid_formatted' => 'S/ ' . number_format($interestPaid, 2),
            'advance_interest_exonerated_formatted' => 'S/ ' . number_format($advanceExonerated, 2),
            'liquidation_interest_not_collected_formatted' => 'S/ ' . number_format($liquidationExonerated, 2),
            'total_interest_not_collected_formatted' => 'S/ ' . number_format($advanceExonerated + $liquidationExonerated, 2),
            'final_balance_formatted' => 'S/ ' . number_format((float) $loan->current_balance, 2),
            'liquidated_at' => $liquidation ? optional($liquidation->payment_date)->format('d/m/Y') : null,
            'liquidated_by' => $liquidation?->creator?->name,
            'liquidation_created_at' => $liquidation ? optional($liquidation->created_at)->format('d/m/Y H:i') : null,
            'final_status' => $this->statusLabel($loan->status),
        ];
    }

    private function loanPaymentTypeLabel(?string $type): string
    {
        return match ($type) {
            'cuota' => 'Cuota', 'parcial' => 'Pago parcial', 'adelanto_cuotas' => 'Adelanto de cuotas',
            'abono_capital' => 'Abono a capital', 'liquidacion' => 'Liquidacion', default => '-',
        };
    }

    private function formatSummary(array $summary, float $approvedAmount): array
    {
        return [
            'fixed_principal_formatted' => 'S/ ' . number_format((float) $summary['fixed_principal'], 2),
            'total_interest_formatted' => 'S/ ' . number_format((float) $summary['total_interest'], 2),
            'total_payment_formatted' => 'S/ ' . number_format((float) $summary['total_payment'], 2),
            'first_installment_formatted' => 'S/ ' . number_format((float) $summary['first_installment'], 2),
            'last_installment_formatted' => 'S/ ' . number_format((float) $summary['last_installment'], 2),
            'initial_balance_formatted' => 'S/ ' . number_format($approvedAmount, 2),
            'first_payment_date' => $summary['first_payment_date'],
        ];
    }

    private function formatInstallments(array $installments): array
    {
        return collect($installments)->map(function ($row) {
            $quote = $this->lateFees->quote(new LoanInstallment($row), today());
            $credit = $this->creditHistoryManager->installmentStatus($row['due_date'], $row['paid_at'] ?? null, (float) ($row['remaining_amount'] ?? $row['installment_amount']));
            return [
            'installment_number' => $row['installment_number'],
            'due_date_formatted' => optional(\Carbon\Carbon::parse($row['due_date']))->format('d/m/Y'),
            'opening_balance_formatted' => 'S/ ' . number_format((float) $row['opening_balance'], 2),
            'principal_amount_formatted' => 'S/ ' . number_format((float) $row['principal_amount'], 2),
            'interest_amount_formatted' => 'S/ ' . number_format((float) $row['interest_amount'], 2),
            'interest_exonerated_formatted' => 'S/ ' . number_format((float) ($row['interest_exonerated'] ?? 0), 2),
            'installment_amount_formatted' => 'S/ ' . number_format((float) $row['installment_amount'], 2),
            'paid_amount_formatted' => 'S/ ' . number_format((float) ($row['paid_amount'] ?? 0), 2),
            'remaining_amount_formatted' => 'S/ ' . number_format((float) ($row['remaining_amount'] ?? $row['installment_amount']), 2),
            'closing_balance_formatted' => 'S/ ' . number_format((float) $row['closing_balance'], 2),
            'status' => $row['status'] ?? 'pendiente',
            'status_label' => $this->installmentStatusLabel($row['status'] ?? 'pendiente'),
            'payment_date_formatted' => ! empty($row['paid_at']) ? \Carbon\Carbon::parse($row['paid_at'])->format('d/m/Y') : 'No pagada',
            'days_late' => $credit['days_late'],
            'credit_status_label' => $credit['label'],
            'credit_color' => $credit['color'],
            'late_days' => $quote['days'],
            'late_fee_pending_formatted' => 'S/ ' . number_format($quote['pending'], 2),
            'late_fee_paid_formatted' => 'S/ ' . number_format((float) ($row['late_fee_paid'] ?? 0), 2),
            'visual_status' => in_array($row['status'] ?? '', ['pagado','adelantado','liquidado'], true) ? 'settled' : ($quote['pending'] > 0 ? 'late' : ((isset($row['due_date']) && \Carbon\Carbon::parse($row['due_date'])->lt(today())) ? 'grace' : 'current')),
            ];
        })->values()->all();
    }

    private function ensureActiveMember(Member $member): void
    {
        $this->eligibility->assertCanRequestLoan($member);
    }

    private function simulationForConversion(?int $simulationId, ?Loan $loan = null, ?int $memberId = null): ?LoanSimulation
    {
        if (! $simulationId) {
            return null;
        }

        $simulation = LoanSimulation::with('member')->lockForUpdate()->findOrFail($simulationId);

        if (! $simulation->member) {
            throw ValidationException::withMessages(['loan_simulation_id' => ['La simulacion no tiene un socio valido.']]);
        }
        $this->eligibility->assertCanRequestLoan($simulation->member);

        if ($memberId && (int) $simulation->member_id !== $memberId) {
            throw ValidationException::withMessages(['loan_simulation_id' => ['La simulacion seleccionada no pertenece al socio del prestamo.']]);
        }

        if ($simulation->status === 'convertida' && (int) $simulation->converted_loan_id !== (int) ($loan?->id)) {
            throw ValidationException::withMessages([
                'loan_simulation_id' => ['Esta simulacion ya fue convertida a prestamo y no puede volver a procesarse.'],
            ]);
        }

        if ($simulation->status === 'anulada') {
            throw ValidationException::withMessages([
                'loan_simulation_id' => ['No se puede convertir una simulacion anulada.'],
            ]);
        }

        if ($simulation->status === 'sin_efecto') {
            throw ValidationException::withMessages([
                'loan_simulation_id' => ['Esta simulacion se encuentra sin efecto y no puede convertirse en prestamo.'],
            ]);
        }

        if ($simulation->status !== 'simulada' && (int) $simulation->converted_loan_id !== (int) ($loan?->id)) {
            throw ValidationException::withMessages([
                'loan_simulation_id' => ['La simulacion ya fue convertida a prestamo.'],
            ]);
        }

        return $simulation;
    }

    private function applySimulationData(array &$data, LoanSimulation $simulation): void
    {
        $data['member_id'] = $simulation->member_id;
        $data['requested_amount'] = (float) $simulation->amount;
        $data['approved_amount'] = (float) $simulation->amount;
        $data['interest_rate'] = (float) $simulation->interest_rate;
        $data['interest_type'] = $simulation->interest_type;
        $data['term_months'] = $simulation->term_months;
        $data['start_date'] = $simulation->start_date->format('Y-m-d');
        $data['first_payment_date'] = $simulation->first_payment_date?->format('Y-m-d');
        $data['amortization_method'] = $simulation->amortization_method;
    }

    private function markSimulationAsConverted(?LoanSimulation $simulation, Loan $loan): void
    {
        if (! $simulation) {
            return;
        }

        $simulation->update([
            'status' => 'convertida',
            'converted_loan_id' => $loan->id,
            'converted_at' => now(),
            'converted_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function releaseConvertedSimulation(?int $oldSimulationId, Loan $loan, ?int $newSimulationId): void
    {
        if (! $oldSimulationId || (int) $oldSimulationId === (int) $newSimulationId) {
            return;
        }

        LoanSimulation::where('id', $oldSimulationId)
            ->where('converted_loan_id', $loan->id)
            ->update([
                'status' => 'simulada',
                'converted_loan_id' => null,
                'converted_at' => null,
                'converted_by' => null,
                'updated_by' => auth()->id(),
            ]);
    }

    private function generateNextCode(): string
    {
        return Loan::nextCode();
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()
            ->whereNotNull('receipt_number')
            ->where('receipt_number', 'like', 'REC-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('receipt_number');

        $lastNumber = 0;

        if ($lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function currentCashBalance(): float
    {
        $query = CashMovement::query()->where('status', 'registrado');
        $income = (clone $query)->where('type', 'ingreso')->sum('amount');
        $expense = (clone $query)->where('type', 'egreso')->sum('amount');

        return (float) $income - (float) $expense;
    }

    private function statusBadge(?string $status): string
    {
        $class = match ($status) {
            'pendiente' => 'warning',
            'aprobado' => 'info',
            'desembolsado', 'pagado' => 'success',
            'anulado' => 'danger',
            default => 'secondary',
        };

        return '<span class="badge badge-' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'desembolsado' => 'Desembolsado',
            'pagado' => 'Pagado',
            'refinanciado' => 'Refinanciado',
            'anulado' => 'Anulado',
            default => 'No definido',
        };
    }

    private function installmentStatusLabel(?string $status): string
    {
        return match ($status) {
            'pendiente' => 'Pendiente',
            'parcial' => 'Parcial',
            'pagado' => 'Pagado',
            'vencido' => 'Vencido',
            'adelantado' => 'Adelantado',
            'liquidado' => 'Liquidado',
            'refinanciado' => 'Refinanciado',
            'anulado' => 'Anulado',
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

    private function messages(): array
    {
        return [
            'member_id.required' => 'Seleccione un socio valido.',
            'member_id.exists' => 'Seleccione un socio valido.',
            'requested_amount.required' => 'El monto solicitado es obligatorio.',
            'requested_amount.numeric' => 'El monto solicitado debe ser un numero valido.',
            'requested_amount.min' => 'El monto solicitado debe ser mayor a cero.',
            'approved_amount.required' => 'El monto aprobado es obligatorio.',
            'approved_amount.numeric' => 'El monto aprobado debe ser un numero valido.',
            'approved_amount.min' => 'El monto aprobado debe ser mayor a cero.',
            'approved_amount.lte' => 'El monto aprobado no puede ser mayor al monto solicitado.',
            'interest_rate.required' => 'La tasa de interes es obligatoria.',
            'interest_rate.numeric' => 'La tasa de interes debe ser un numero valido.',
            'interest_rate.min' => 'La tasa de interes no puede ser negativa.',
            'term_months.required' => 'El plazo es obligatorio.',
            'term_months.integer' => 'El plazo debe ser un numero entero.',
            'term_months.min' => 'El plazo debe ser como minimo de 1 mes.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio debe ser valida.',
            'first_payment_date.after_or_equal' => 'La fecha de primera cuota no puede ser menor a la fecha de inicio.',
            'amortization_method.required' => 'Seleccione un metodo de amortizacion valido.',
            'amortization_method.in' => 'Seleccione un metodo de amortizacion valido.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'disbursed_at.required' => 'La fecha de desembolso es obligatoria.',
            'disbursed_at.date' => 'La fecha de desembolso debe ser valida.',
            'reference.required' => 'La referencia del desembolso es obligatoria para este metodo de pago.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
        ];
    }
}
