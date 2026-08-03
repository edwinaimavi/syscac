<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanSimulation;
use App\Models\Member;
use App\Services\LoanSimulationCalculator;
use App\Services\LoanEligibilityService;
use App\Services\MemberCreditHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LoanSimulationController extends Controller
{
    public function __construct(private readonly LoanSimulationCalculator $calculator, private readonly LoanEligibilityService $eligibility, private readonly MemberCreditHistoryService $creditHistory)
    {
        $this->middleware('can:admin.simulaciones.index')->only(['index', 'list', 'summary', 'nextCode']);
        $this->middleware('can:admin.simulaciones.create')->only(['store', 'calculate']);
        $this->middleware('can:admin.simulaciones.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.simulaciones.show')->only(['show']);
        $this->middleware('can:admin.simulaciones.print')->only(['print']);
        $this->middleware('can:admin.simulaciones.anular')->only(['annul']);
        $this->middleware('can:admin.simulaciones.edit')->only(['withoutEffect']);
        $this->middleware('can:admin.simulaciones.delete')->only(['destroy']);
    }

    public function index()
    {
        $members = Member::query()
            ->availableForLoanOperations()
            ->withSum(['shares as registered_contributions' => fn ($query) => $query->where('status', 'registrado')], 'amount')
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name', 'birth_date']);

        $guarantors = Member::query()
            ->eligibleGuarantors()
            ->withSum(['shares as registered_contributions' => fn ($query) => $query->where('status', 'registrado')], 'amount')
            ->orderBy('full_name')
            ->get(['id', 'code', 'dni', 'full_name']);

        return view('admin.loan-simulations.index', [
            'members' => $members,
            'guarantors' => $guarantors,
            'nextCode' => $this->generateNextCode(),
        ]);
    }

    public function list(Request $request)
    {
        $simulations = LoanSimulation::with('member')
            ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->integer('member_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('simulation_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('simulation_date', '<=', $request->input('date_to')))
            ->orderByDesc('simulation_date')
            ->orderByDesc('id');

        return DataTables::of($simulations)
            ->addIndexColumn()
            ->editColumn('simulation_date', fn (LoanSimulation $simulation) => optional($simulation->simulation_date)->format('d/m/Y') ?? '-')
            ->addColumn('member_name', fn (LoanSimulation $simulation) => $simulation->member?->full_name ?? '-')
            ->addColumn('member_dni', fn (LoanSimulation $simulation) => $simulation->member?->dni ?? '-')
            ->editColumn('amount', fn (LoanSimulation $simulation) => 'S/ ' . number_format((float) $simulation->amount, 2))
            ->editColumn('interest_rate', fn (LoanSimulation $simulation) => number_format((float) $simulation->interest_rate, 2) . '%')
            ->editColumn('term_months', fn (LoanSimulation $simulation) => $simulation->term_months . ' meses')
            ->editColumn('total_interest', fn (LoanSimulation $simulation) => 'S/ ' . number_format((float) $simulation->total_interest, 2))
            ->editColumn('total_payment', fn (LoanSimulation $simulation) => 'S/ ' . number_format((float) $simulation->total_payment, 2))
            ->editColumn('status', fn (LoanSimulation $simulation) => $this->statusBadge($simulation->status))
            ->addColumn('acciones', fn (LoanSimulation $simulation) => view('admin.loan-simulations.partials.acciones', compact('simulation'))->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary(Request $request)
    {
        $query = LoanSimulation::query()
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('simulation_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('simulation_date', '<=', $request->input('date_to')));

        $totalSimulatedCurrent = (clone $query)->where('status', 'simulada')->sum('amount');
        $totalConverted = (clone $query)->where('status', 'convertida')->sum('amount');
        $count = (clone $query)->count();
        $last = (clone $query)->latest('simulation_date')->latest('id')->first();

        return response()->json([
            'total_simulado_vigente' => number_format((float) $totalSimulatedCurrent, 2),
            'total_convertido' => number_format((float) $totalConverted, 2),
            'total_registros' => $count,
            'ultima_simulacion' => $last
                ? $last->code . ' - S/ ' . number_format((float) $last->amount, 2) . ' - ' . $this->statusLabel($last->status)
                : '-',
        ]);
    }

    public function nextCode()
    {
        return response()->json(['code' => $this->generateNextCode()]);
    }

    public function calculate(Request $request)
    {
        $data = $this->validatedData($request, false);
        $member = Member::findOrFail($data['member_id']);
        $evaluation = $this->eligibility->evaluate($member, (float) $data['amount'], $data['guarantor_member_id'] ?? null);
        $evaluation['credit_history'] = $this->creditHistory->evaluate($member);

        $payload = $this->calculationPayload($data);
        $payload['eligibility'] = $evaluation;
        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $simulation = DB::transaction(function () use ($data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $this->ensureActiveMember($member);
            $evaluation = $this->eligibility->validate($member, (float) $data['amount'], $data['guarantor_member_id'] ?? null);

            $data['code'] = $this->generateNextCode();
            $this->normalizeSimulationData($data);
            $calculation = $this->calculator->calculate(
                (float) $data['amount'],
                (float) $data['interest_rate'],
                (int) $data['term_months'],
                $data['start_date'],
                $data['first_payment_date'] ?? null
            );

            $data = array_merge($data, [
                ...$this->eligibility->snapshot($evaluation),
                'first_payment_date' => $calculation['summary']['first_payment_date'],
                'fixed_principal' => $calculation['summary']['fixed_principal'],
                'total_interest' => $calculation['summary']['total_interest'],
                'total_payment' => $calculation['summary']['total_payment'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $simulation = LoanSimulation::create($data);
            $simulation->installments()->createMany($calculation['installments']);

            return $simulation;
        });

        return response()->json([
            'message' => 'La simulacion fue creada correctamente.',
            'id' => $simulation->id,
        ]);
    }

    public function show(LoanSimulation $loanSimulation)
    {
        return response()->json($this->simulationPayload($loanSimulation));
    }

    public function edit(LoanSimulation $loanSimulation)
    {
        if ($loanSimulation->status !== 'simulada') {
            return response()->json(['message' => 'Solo se puede editar una simulacion vigente.'], 422);
        }

        return response()->json($this->simulationPayload($loanSimulation));
    }

    public function update(Request $request, LoanSimulation $loanSimulation)
    {
        if ($loanSimulation->status !== 'simulada') {
            return response()->json(['message' => 'Solo se puede editar una simulacion vigente.'], 422);
        }

        $data = $this->validatedData($request, true, $loanSimulation);

        DB::transaction(function () use ($loanSimulation, $data) {
            $member = Member::lockForUpdate()->findOrFail($data['member_id']);
            $this->ensureActiveMember($member);
            $evaluation = $this->eligibility->validate($member, (float) $data['amount'], $data['guarantor_member_id'] ?? null);

            $this->normalizeSimulationData($data);
            unset($data['code']);

            $calculation = $this->calculator->calculate(
                (float) $data['amount'],
                (float) $data['interest_rate'],
                (int) $data['term_months'],
                $data['start_date'],
                $data['first_payment_date'] ?? null
            );

            $data = array_merge($data, [
                ...$this->eligibility->snapshot($evaluation),
                'first_payment_date' => $calculation['summary']['first_payment_date'],
                'fixed_principal' => $calculation['summary']['fixed_principal'],
                'total_interest' => $calculation['summary']['total_interest'],
                'total_payment' => $calculation['summary']['total_payment'],
                'updated_by' => auth()->id(),
                'annulled_by' => $data['status'] === 'anulada' ? auth()->id() : null,
                'annulled_at' => $data['status'] === 'anulada' ? now() : null,
            ]);

            $loanSimulation->update($data);
            $loanSimulation->installments()->delete();
            $loanSimulation->installments()->createMany($calculation['installments']);
        });

        return response()->json(['message' => 'La simulacion fue actualizada correctamente.']);
    }

    public function destroy(LoanSimulation $loanSimulation)
    {
        return $this->annul($loanSimulation);
    }

    public function annul(Request $request, LoanSimulation $loanSimulation)
    {
        if ($loanSimulation->status !== 'simulada') {
            return response()->json(['message' => 'Solo una simulacion vigente puede anularse.'], 422);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'El motivo de anulacion es obligatorio.']);

        $loanSimulation->update([
            'status' => 'anulada',
            'effect_reason' => $data['reason'],
            'effected_by' => auth()->id(),
            'effected_at' => now(),
            'updated_by' => auth()->id(),
            'annulled_by' => auth()->id(),
            'annulled_at' => now(),
        ]);

        return response()->json(['message' => 'La simulacion fue anulada correctamente.']);
    }

    public function withoutEffect(Request $request, LoanSimulation $loanSimulation)
    {
        if ($loanSimulation->status !== 'simulada') {
            return response()->json(['message' => $loanSimulation->status === 'sin_efecto'
                ? 'Esta simulacion se encuentra sin efecto y no puede modificarse.'
                : 'Solo las simulaciones vigentes pueden dejarse sin efecto.'], 422);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [
            'reason.required' => 'El motivo para dejar la simulacion sin efecto es obligatorio.',
        ]);

        $loanSimulation->update([
            'status' => 'sin_efecto',
            'effect_reason' => $data['reason'],
            'effected_by' => auth()->id(),
            'effected_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'La simulacion quedo sin efecto correctamente.']);
    }

    public function print(LoanSimulation $loanSimulation)
    {
        return view('admin.loan-simulations.print', [
            'simulation' => $loanSimulation->load(['member', 'installments', 'convertedLoan', 'converter']),
            'generatedBy' => auth()->user()?->name ?? '-',
            'generatedAt' => now(),
        ]);
    }

    private function validatedData(Request $request, bool $withStatus = true, ?LoanSimulation $simulation = null): array
    {
        $this->normalizeNullableRequestFields($request);

        $rules = [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'guarantor_member_id' => ['nullable', 'integer', 'different:member_id', Rule::exists('members', 'id')],
            'simulation_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'interest_type' => ['nullable', Rule::in(['mensual'])],
            'term_months' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'first_payment_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'amortization_method' => ['required', Rule::in(['aleman'])],
            'observation' => ['nullable', 'string'],
        ];

        if ($withStatus) {
            $rules['status'] = ['required', Rule::in(['simulada', 'anulada'])];
        }

        return $request->validate($rules, $this->messages());
    }

    private function normalizeNullableRequestFields(Request $request): void
    {
        $fields = ['first_payment_date', 'observation', 'guarantor_member_id'];
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

    private function normalizeSimulationData(array &$data): void
    {
        $data['amount'] = (float) $data['amount'];
        $data['interest_rate'] = (float) $data['interest_rate'];
        $data['interest_type'] = $data['interest_type'] ?? 'mensual';
        $data['term_months'] = (int) $data['term_months'];
        $data['amortization_method'] = 'aleman';
        $data['status'] = $data['status'] ?? 'simulada';
    }

    private function ensureActiveMember(Member $member): void
    {
        $this->eligibility->assertCanRequestLoan($member);
    }

    private function calculationPayload(array $data): array
    {
        $this->normalizeSimulationData($data);

        $calculation = $this->calculator->calculate(
            (float) $data['amount'],
            (float) $data['interest_rate'],
            (int) $data['term_months'],
            $data['start_date'],
            $data['first_payment_date'] ?? null
        );

        return [
            'summary' => $this->formatSummary($calculation['summary']),
            'installments' => $this->formatInstallments($calculation['installments']),
        ];
    }

    private function simulationPayload(LoanSimulation $simulation): array
    {
        $simulation->load([
            'member',
            'creator',
            'updater',
            'annuller',
            'effecter',
            'convertedLoan',
            'converter',
            'guarantorMember',
            'installments' => fn ($query) => $query->orderBy('installment_number'),
        ]);

        return [
            'id' => $simulation->id,
            'code' => $simulation->code,
            'simulation_date' => optional($simulation->simulation_date)->format('Y-m-d'),
            'simulation_date_formatted' => optional($simulation->simulation_date)->format('d/m/Y'),
            'member_id' => $simulation->member_id,
            'guarantor_member_id' => $simulation->guarantor_member_id,
            'eligibility' => array_merge([
                'member_type' => $simulation->member_type_at_evaluation,
                'contribution_count' => $simulation->member_contribution_count_at_evaluation,
                'total_contributions' => $simulation->member_total_contributions_at_evaluation,
                'loan_limit_without_guarantor' => $simulation->loan_limit_without_guarantor,
                'requires_guarantor' => $simulation->requires_guarantor,
                'guarantor_total_contributions' => $simulation->guarantor_total_contributions_at_evaluation,
                'guarantor_requirement_reason' => $simulation->guarantor_requirement_reason,
                'guarantor_name' => $simulation->guarantorMember?->full_name,
            ], $simulation->member ? array_merge(
                $this->eligibility->loanEligibilitySummary($simulation->member),
                ['credit_history' => $this->creditHistory->evaluate($simulation->member)]
            ) : []),
            'member_code' => $simulation->member?->code,
            'member_dni' => $simulation->member?->dni,
            'member_name' => $simulation->member?->full_name,
            'amount' => number_format((float) $simulation->amount, 2, '.', ''),
            'amount_formatted' => 'S/ ' . number_format((float) $simulation->amount, 2),
            'interest_rate' => number_format((float) $simulation->interest_rate, 2, '.', ''),
            'interest_rate_formatted' => number_format((float) $simulation->interest_rate, 2) . '% mensual',
            'interest_type' => $simulation->interest_type,
            'term_months' => $simulation->term_months,
            'term_months_formatted' => $simulation->term_months . ' meses',
            'start_date' => optional($simulation->start_date)->format('Y-m-d'),
            'start_date_formatted' => optional($simulation->start_date)->format('d/m/Y'),
            'first_payment_date' => optional($simulation->first_payment_date)->format('Y-m-d'),
            'first_payment_date_formatted' => optional($simulation->first_payment_date)->format('d/m/Y'),
            'amortization_method' => $simulation->amortization_method,
            'amortization_method_label' => 'Alemán',
            'fixed_principal' => number_format((float) $simulation->fixed_principal, 2, '.', ''),
            'fixed_principal_formatted' => 'S/ ' . number_format((float) $simulation->fixed_principal, 2),
            'total_interest' => number_format((float) $simulation->total_interest, 2, '.', ''),
            'total_interest_formatted' => 'S/ ' . number_format((float) $simulation->total_interest, 2),
            'total_payment' => number_format((float) $simulation->total_payment, 2, '.', ''),
            'total_payment_formatted' => 'S/ ' . number_format((float) $simulation->total_payment, 2),
            'first_installment_formatted' => 'S/ ' . number_format((float) ($simulation->installments->first()?->installment_amount ?? 0), 2),
            'last_installment_formatted' => 'S/ ' . number_format((float) ($simulation->installments->last()?->installment_amount ?? 0), 2),
            'observation' => $simulation->observation,
            'status' => $simulation->status,
            'status_label' => $this->statusLabel($simulation->status),
            'print_url' => route('admin.loan-simulations.print', $simulation),
            'converted_loan_id' => $simulation->converted_loan_id,
            'converted_loan_number' => $simulation->convertedLoan?->loan_number,
            'converted_loan_url' => $simulation->convertedLoan ? route('admin.prestamos.index') . '?loan_id=' . $simulation->convertedLoan->id : null,
            'converted_at' => optional($simulation->converted_at)->format('d/m/Y H:i'),
            'converted_by_name' => $simulation->converter?->name,
            'effect_reason' => $simulation->effect_reason,
            'effected_at' => optional($simulation->effected_at)->format('d/m/Y H:i'),
            'effected_by_name' => $simulation->effecter?->name,
            'annulled_at' => optional($simulation->annulled_at)->format('d/m/Y H:i'),
            'annulled_by_name' => $simulation->annuller?->name,
            'created_at' => optional($simulation->created_at)->format('d/m/Y H:i'),
            'created_by_name' => $simulation->creator?->name,
            'installments' => $this->formatInstallments($simulation->installments->toArray()),
        ];
    }

    private function formatSummary(array $summary): array
    {
        return [
            'fixed_principal' => number_format((float) $summary['fixed_principal'], 2, '.', ''),
            'fixed_principal_formatted' => 'S/ ' . number_format((float) $summary['fixed_principal'], 2),
            'total_interest' => number_format((float) $summary['total_interest'], 2, '.', ''),
            'total_interest_formatted' => 'S/ ' . number_format((float) $summary['total_interest'], 2),
            'total_payment' => number_format((float) $summary['total_payment'], 2, '.', ''),
            'total_payment_formatted' => 'S/ ' . number_format((float) $summary['total_payment'], 2),
            'first_installment' => number_format((float) $summary['first_installment'], 2, '.', ''),
            'first_installment_formatted' => 'S/ ' . number_format((float) $summary['first_installment'], 2),
            'last_installment' => number_format((float) $summary['last_installment'], 2, '.', ''),
            'last_installment_formatted' => 'S/ ' . number_format((float) $summary['last_installment'], 2),
            'first_payment_date' => $summary['first_payment_date'],
        ];
    }

    private function formatInstallments(array $installments): array
    {
        return collect($installments)->map(fn ($row) => [
            'installment_number' => $row['installment_number'],
            'due_date' => optional(\Carbon\Carbon::parse($row['due_date']))->format('Y-m-d'),
            'due_date_formatted' => optional(\Carbon\Carbon::parse($row['due_date']))->format('d/m/Y'),
            'opening_balance' => number_format((float) $row['opening_balance'], 2, '.', ''),
            'opening_balance_formatted' => 'S/ ' . number_format((float) $row['opening_balance'], 2),
            'principal_amount' => number_format((float) $row['principal_amount'], 2, '.', ''),
            'principal_amount_formatted' => 'S/ ' . number_format((float) $row['principal_amount'], 2),
            'interest_amount' => number_format((float) $row['interest_amount'], 2, '.', ''),
            'interest_amount_formatted' => 'S/ ' . number_format((float) $row['interest_amount'], 2),
            'installment_amount' => number_format((float) $row['installment_amount'], 2, '.', ''),
            'installment_amount_formatted' => 'S/ ' . number_format((float) $row['installment_amount'], 2),
            'closing_balance' => number_format((float) $row['closing_balance'], 2, '.', ''),
            'closing_balance_formatted' => 'S/ ' . number_format((float) $row['closing_balance'], 2),
        ])->values()->all();
    }

    private function generateNextCode(): string
    {
        return LoanSimulation::nextCode();
    }

    private function statusBadge(?string $status): string
    {
        $class = match ($status) {
            'simulada' => 'success',
            'convertida' => 'info',
            'sin_efecto' => 'secondary',
            'anulada' => 'danger',
            default => 'secondary',
        };

        return '<span class="badge badge-' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'simulada' => 'Simulada',
            'convertida' => 'Convertida',
            'sin_efecto' => 'Sin efecto',
            'anulada' => 'Anulada',
            default => 'No definido',
        };
    }

    private function messages(): array
    {
        return [
            'member_id.required' => 'Seleccione un socio valido.',
            'member_id.exists' => 'Seleccione un socio valido.',
            'simulation_date.required' => 'La fecha de simulacion es obligatoria.',
            'simulation_date.date' => 'La fecha de simulacion debe ser valida.',
            'amount.required' => 'El monto del prestamo es obligatorio.',
            'amount.numeric' => 'El monto del prestamo debe ser un numero valido.',
            'amount.min' => 'El monto del prestamo debe ser mayor a cero.',
            'interest_rate.required' => 'La tasa de interes es obligatoria.',
            'interest_rate.numeric' => 'La tasa de interes debe ser un numero valido.',
            'interest_rate.min' => 'La tasa de interes no puede ser negativa.',
            'interest_type.in' => 'Seleccione un tipo de interes valido.',
            'term_months.required' => 'El plazo es obligatorio.',
            'term_months.integer' => 'El plazo debe ser un numero entero.',
            'term_months.min' => 'El plazo debe ser como minimo de 1 mes.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio debe ser valida.',
            'first_payment_date.date' => 'La fecha de primera cuota debe ser valida.',
            'first_payment_date.after_or_equal' => 'La fecha de primera cuota no puede ser menor a la fecha de inicio.',
            'amortization_method.required' => 'Seleccione un metodo de amortizacion valido.',
            'amortization_method.in' => 'Seleccione un metodo de amortizacion valido.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
        ];
    }
}
