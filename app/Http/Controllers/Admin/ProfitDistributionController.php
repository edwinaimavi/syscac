<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\MemberShare;
use App\Models\Member;
use App\Models\ProfitDistribution;
use App\Models\ProfitDistributionDetail;
use App\Models\Receipt;
use App\Services\ShareCashMovementService;
use App\Services\ProfitAvailabilityService;
use App\Services\MonthlyProfitAccrualService;
use App\Models\MonthlyProfitAccrual;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ProfitDistributionController extends Controller
{
    private const PAYMENT_METHODS = ['efectivo', 'yape', 'plin', 'transferencia', 'otro'];

    public function __construct()
    {
        $this->middleware('can:admin.utilidades.index')->only(['index', 'list', 'summary', 'availability', 'nextCode']);
        $this->middleware('can:admin.utilidades.sources')->only(['sources']);
        $this->middleware('can:admin.utilidades.calculate')->only(['calculate']);
        $this->middleware('can:admin.utilidades.create')->only(['store']);
        $this->middleware('can:admin.utilidades.edit')->only(['edit', 'update']);
        $this->middleware('can:admin.utilidades.show')->only(['show']);
        $this->middleware('can:admin.utilidades.approve')->only(['approve']);
        $this->middleware('can:admin.utilidades.pay')->only(['payDetail']);
        $this->middleware('can:admin.utilidades.anular')->only(['annul', 'destroy']);
        $this->middleware('can:admin.utilidades.report')->only(['report']);
        $this->middleware('can:admin.utilidades.report_pdf')->only(['reportPdf']);
        $this->middleware('can:admin.utilidades.receipt')->only(['receipt']);
        $this->middleware('can:admin.utilidades.receipt_pdf')->only(['receiptPdf']);
        $this->middleware('can:admin.utilidades.voucher')->only(['voucher']);
    }

    public function index()
    {
        return view('admin.profit-distributions.index', [
            'nextCode' => ProfitDistribution::nextCode(),
            'months' => $this->months(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function list(Request $request)
    {
        $items = ProfitDistribution::query()
            ->when($request->filled('period_year'), fn ($query) => $query->where('period_year', $request->integer('period_year')))
            ->when($request->filled('period_month'), fn ($query) => $query->where('period_month', $request->integer('period_month')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('start_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('end_date', '<=', $request->input('date_to')))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id');

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('period', fn (ProfitDistribution $item) => $this->periodLabel($item))
            ->editColumn('start_date', fn (ProfitDistribution $item) => optional($item->start_date)->format('d/m/Y') ?? '-')
            ->editColumn('end_date', fn (ProfitDistribution $item) => optional($item->end_date)->format('d/m/Y') ?? '-')
            ->editColumn('total_profit', fn (ProfitDistribution $item) => $this->money($item->total_profit))
            ->editColumn('total_shares', fn (ProfitDistribution $item) => number_format((float) ((float) $item->total_action_month > 0 ? $item->total_action_month : $item->total_shares), 4))
            ->editColumn('profit_per_share', fn (ProfitDistribution $item) => $this->money((float) $item->profit_per_action_month > 0 ? $item->profit_per_action_month : $item->profit_per_share))
            ->editColumn('status', fn (ProfitDistribution $item) => $this->statusBadge($item->status))
            ->addColumn('acciones', fn (ProfitDistribution $item) => view('admin.profit-distributions.partials.acciones', ['distribution' => $item])->render())
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function summary()
    {
        return response()->json([
            'distributed' => number_format((float) ProfitDistribution::whereIn('status', ['aprobado', 'pagado'])->sum('total_profit'), 2),
            'calculated' => ProfitDistribution::where('status', 'calculado')->count(),
            'approved' => ProfitDistribution::where('status', 'aprobado')->count(),
            'pending' => number_format((float) ProfitDistributionDetail::where('status', 'pendiente')->selectRaw('COALESCE(SUM(profit_amount - paid_amount), 0) as pending')->value('pending'), 2),
        ]);
    }

    public function availability(Request $request, ProfitAvailabilityService $availability)
    {
        $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]);
        $summary = $availability->summary((string) $request->string('start_date'), (string) $request->string('end_date'), $request->integer('exclude_id') ?: null);
        $shares = MemberShare::query()->where('status', 'registrado');
        $summary['contributions_total'] = round((float) (clone $shares)->sum('share_capital_amount'), 2);
        $summary['shares_total'] = round((float) (clone $shares)->sum('shares_quantity'), 4);

        return response()->json($this->availabilityPayload($summary));
    }

    public function sources(Request $request, ProfitAvailabilityService $availability)
    {
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date']]);
        return response()->json($availability->sources($data['start_date'], $data['end_date'])->get()->map(function ($detail) {
            return [
                'date' => optional($detail->payment->payment_date)->format('d/m/Y'),
                'member' => $detail->payment->member?->full_name ?? '-',
                'loan' => $detail->payment->loan?->loan_number ?? '-',
                'installment' => $detail->installment?->installment_number ?? '-',
                'interest' => (float) $detail->interest_paid,
                'late_fee' => (float) $detail->late_fee_paid,
                'total' => round((float) $detail->interest_paid + (float) $detail->late_fee_paid, 2),
                'status' => 'Registrado',
            ];
        })->values());
    }

    public function nextCode()
    {
        return response()->json(['code' => ProfitDistribution::nextCode()]);
    }

    public function monthlyList()
    {
        return response()->json(MonthlyProfitAccrual::withCount('details')->latest('month')->get()->map(fn($m)=>[
            'id'=>$m->id,'code'=>$m->code,'month'=>$m->month->translatedFormat('F Y'),'interest'=>(float)$m->interest_collected,
            'late_fees'=>(float)$m->late_fees_collected,'adjustments'=>round((float)$m->positive_adjustments-(float)$m->negative_adjustments,2),
            'total_profit'=>(float)$m->total_profit,'total_shares'=>(float)$m->total_shares,'profit_per_share'=>(float)$m->profit_per_share,
            'members'=>$m->details_count,'status'=>$m->status,
        ]));
    }

    public function monthlyCalculate(Request $request, MonthlyProfitAccrualService $service)
    {
        $data=$request->validate(['month'=>['required','date_format:Y-m']]);
        return response()->json($service->preview($data['month'].'-01'));
    }

    public function monthlyStore(Request $request, MonthlyProfitAccrualService $service)
    {
        $data=$request->validate(['month'=>['required','date_format:Y-m']]);
        $record=$service->save($data['month'].'-01');
        return response()->json(['message'=>'Utilidad mensual calculada correctamente.','id'=>$record->id]);
    }

    public function monthlyShow(MonthlyProfitAccrual $monthly)
    {
        $monthly->load('details.member:id,code,dni,full_name');
        return response()->json(['id'=>$monthly->id,'code'=>$monthly->code,'month'=>$monthly->month->format('Y-m'),'status'=>$monthly->status,
            'interest_collected'=>(float)$monthly->interest_collected,'late_fees_collected'=>(float)$monthly->late_fees_collected,
            'positive_adjustments'=>(float)$monthly->positive_adjustments,'negative_adjustments'=>(float)$monthly->negative_adjustments,
            'total_profit'=>(float)$monthly->total_profit,'total_shares'=>(float)$monthly->total_shares,'profit_per_share'=>(float)$monthly->profit_per_share,
            'details'=>$monthly->details->map(fn($d)=>['member'=>$d->member?->full_name,'dni'=>$d->member?->dni,'code'=>$d->member?->code,'shares'=>(float)$d->shares_quantity,'profit'=>(float)$d->profit_amount,'paid'=>(float)$d->paid_amount,'pending'=>round((float)$d->profit_amount-(float)$d->paid_amount,2),'status'=>$d->status])]);
    }

    public function monthlyApprove(MonthlyProfitAccrual $monthly)
    {
        if(!in_array($monthly->status,['calculada'],true))return response()->json(['message'=>'Solo una utilidad calculada puede aprobarse.'],422);
        $monthly->update(['status'=>'aprobada','approved_by'=>auth()->id(),'approved_at'=>now()]);
        return response()->json(['message'=>'Utilidad mensual aprobada correctamente.']);
    }

    public function calculate(Request $request, ProfitAvailabilityService $availability)
    {
        $data = $this->validatedData($request, false);
        $financial = $availability->validateAmount((float) $data['total_profit'], $data['start_date'], $data['end_date'], $request->integer('distribution_id') ?: null);

        $payload = $this->calculationPayload((float) $data['total_profit'], $data['start_date'], $data['end_date']);
        $payload['availability'] = $this->availabilityPayload($financial);

        return response()->json($payload);
    }

    public function store(Request $request, ProfitAvailabilityService $availability)
    {
        $data = $this->validatedData($request);
        $this->ensurePeriodDoesNotOverlap($data['start_date'], $data['end_date']);

        $distribution = DB::transaction(function () use ($data, $availability) {
            $availability->validateAmount((float) $data['total_profit'], $data['start_date'], $data['end_date'], null, true);
            $calculation = $this->calculationPayload((float) $data['total_profit'], $data['start_date'], $data['end_date']);
            $distribution = ProfitDistribution::create([
                'code' => ProfitDistribution::nextCode(),
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_profit' => $calculation['summary']['total_profit'],
                'total_shares' => $calculation['summary']['total_shares'],
                'total_action_month' => $calculation['summary']['total_action_month'],
                'profit_per_share' => $calculation['summary']['profit_per_share'],
                'profit_per_action_month' => $calculation['summary']['profit_per_action_month'],
                'source_type' => 'loan_payments',
                'calculated_at' => now(),
                'calculated_by' => auth()->id(),
                'observation' => $data['observation'] ?? null,
                'status' => 'calculado',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $distribution->details()->createMany(collect($calculation['details'])->map(fn ($row) => [
                'member_id' => $row['member_id'],
                'shares_quantity' => $row['shares_quantity'],
                'actions_considered' => $row['actions_considered'],
                'months_considered' => $row['months_considered'],
                'action_month' => $row['action_month'],
                'calculation_breakdown' => $row['calculation_breakdown'],
                'participation_percentage' => $row['participation_percentage'],
                'profit_amount' => $row['profit_amount'],
                'paid_amount' => 0,
                'status' => 'pendiente',
            ])->all());

            return $distribution;
        });

        return response()->json(['message' => 'Distribucion de utilidades calculada correctamente.', 'id' => $distribution->id]);
    }

    public function show(ProfitDistribution $utilidade)
    {
        return response()->json($this->distributionPayload($utilidade));
    }

    public function edit(ProfitDistribution $utilidade)
    {
        return response()->json($this->distributionPayload($utilidade));
    }

    public function update(Request $request, ProfitDistribution $utilidade, ProfitAvailabilityService $availability)
    {
        if ($utilidade->status !== 'calculado') {
            return response()->json(['message' => 'Solo se puede editar una distribucion en estado calculado.'], 422);
        }

        $data = $this->validatedData($request);
        $this->ensurePeriodDoesNotOverlap($data['start_date'], $data['end_date'], $utilidade->id);

        DB::transaction(function () use ($utilidade, $data, $availability) {
            $availability->validateAmount((float) $data['total_profit'], $data['start_date'], $data['end_date'], $utilidade->id, true);
            $calculation = $this->calculationPayload((float) $data['total_profit'], $data['start_date'], $data['end_date']);
            $utilidade->update([
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_profit' => $calculation['summary']['total_profit'],
                'total_shares' => $calculation['summary']['total_shares'],
                'total_action_month' => $calculation['summary']['total_action_month'],
                'profit_per_share' => $calculation['summary']['profit_per_share'],
                'profit_per_action_month' => $calculation['summary']['profit_per_action_month'],
                'calculated_at' => now(),
                'calculated_by' => auth()->id(),
                'observation' => $data['observation'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $utilidade->details()->delete();
            $utilidade->details()->createMany(collect($calculation['details'])->map(fn ($row) => [
                'member_id' => $row['member_id'],
                'shares_quantity' => $row['shares_quantity'],
                'actions_considered' => $row['actions_considered'],
                'months_considered' => $row['months_considered'],
                'action_month' => $row['action_month'],
                'calculation_breakdown' => $row['calculation_breakdown'],
                'participation_percentage' => $row['participation_percentage'],
                'profit_amount' => $row['profit_amount'],
                'paid_amount' => 0,
                'status' => 'pendiente',
            ])->all());
        });

        return response()->json(['message' => 'Distribucion de utilidades actualizada correctamente.']);
    }

    public function approve(ProfitDistribution $utilidade, ProfitAvailabilityService $availability)
    {
        if ($utilidade->status !== 'calculado') {
            return response()->json(['message' => 'Solo se pueden aprobar distribuciones calculadas.'], 422);
        }

        DB::transaction(function () use ($utilidade, $availability) {
            $availability->validateAmount((float) $utilidade->total_profit, $utilidade->start_date->toDateString(), $utilidade->end_date->toDateString(), $utilidade->id, true);
            $utilidade->update([
                'status' => 'aprobado',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Distribucion de utilidades aprobada correctamente.']);
    }

    public function payDetail(Request $request, ProfitDistributionDetail $detail)
    {
        $detail->load(['distribution', 'member']);

        if ($detail->distribution->status !== 'aprobado') {
            return response()->json(['message' => 'Solo se pueden pagar utilidades aprobadas.'], 422);
        }

        if ($detail->status === 'pagado') {
            return response()->json(['message' => 'Esta utilidad ya se encuentra pagada.'], 422);
        }

        $data = $this->validatedPaymentData($request);
        $pending = round((float) $detail->profit_amount - (float) $detail->paid_amount, 2);
        $amount = $pending;

        if ($this->currentCashBalance() < $amount) {
            throw ValidationException::withMessages(['paid_amount' => ['No hay saldo suficiente en caja para pagar esta utilidad.']]);
        }

        DB::transaction(function () use ($request, $detail, $data, $amount) {
            $this->storeVoucher($request, $data, $detail);

            $detail->update([
                'paid_amount' => $amount,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'voucher_path' => $data['voucher_path'] ?? $detail->voucher_path,
                'status' => 'pagado',
                'paid_at' => now(),
                'paid_by' => auth()->id(),
                'observation' => $data['observation'] ?? null,
            ]);

            $this->syncCashMovement($detail->fresh(['distribution', 'member']));
            $receipt = $this->createOrUpdateReceipt($detail->fresh(['distribution', 'member']));
            $detail->update(['receipt_id' => $receipt->id]);
            $this->markDistributionPaidIfComplete($detail->distribution);
        });

        return response()->json(['message' => 'Utilidad pagada correctamente.']);
    }

    public function destroy(ProfitDistribution $utilidade)
    {
        return $this->annul($utilidade);
    }

    public function annul(ProfitDistribution $utilidade)
    {
        if ($utilidade->details()->where('status', 'pagado')->exists()) {
            return response()->json(['message' => 'No se puede anular esta distribucion porque ya tiene utilidades pagadas.'], 422);
        }

        $utilidade->update([
            'status' => 'anulado',
            'annulled_by' => auth()->id(),
            'annulled_at' => now(),
            'updated_by' => auth()->id(),
        ]);
        $utilidade->details()->update(['status' => 'anulado']);

        return response()->json(['message' => 'Distribucion de utilidades anulada correctamente.']);
    }

    public function report(ProfitDistribution $utilidade)
    {
        $utilidade->load(['details.member', 'creator', 'calculator', 'approver']);

        return view('admin.profit-distributions.report', ['distribution' => $utilidade]);
    }

    public function reportPdf(ProfitDistribution $utilidade)
    {
        $utilidade->load(['details.member', 'creator', 'calculator', 'approver']);

        return Pdf::loadView('admin.profit-distributions.report', ['distribution' => $utilidade, 'pdfMode' => true])
            ->setPaper('a4', 'landscape')
            ->stream('Reporte Utilidades ' . $utilidade->code . '.pdf');
    }

    public function receipt(ProfitDistributionDetail $detail)
    {
        $receipt = $detail->receipt ?: Receipt::where('related_type', ProfitDistributionDetail::class)->where('related_id', $detail->id)->firstOrFail();

        return redirect()->route('admin.recibos.print', $receipt);
    }

    public function receiptPdf(ProfitDistributionDetail $detail)
    {
        $receipt = $detail->receipt ?: Receipt::where('related_type', ProfitDistributionDetail::class)->where('related_id', $detail->id)->firstOrFail();

        return redirect()->route('admin.recibos.pdf', $receipt);
    }

    public function voucher(ProfitDistributionDetail $detail)
    {
        if (! $detail->voucher_path || ! Storage::disk('public')->exists($detail->voucher_path)) {
            abort(404, 'Comprobante no encontrado.');
        }

        return Storage::disk('public')->download($detail->voucher_path);
    }

    private function validatedData(Request $request, bool $requireStatus = true): array
    {
        $year = $request->integer('period_year');
        if ($year >= 2000) {
            $period = \Carbon\Carbon::create($year, (int) config('utility.fiscal_start_month', 3), 1);
            $request->merge([
                'period_month' => null,
                'start_date' => $period->format('Y-m-d'),
                'end_date' => $period->copy()->addYear()->format('Y-m-d'),
            ]);
        }

        return $request->validate([
            'period_year' => ['required', 'integer', 'min:2000'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_profit' => ['required', 'numeric', 'min:0.01'],
            'status' => [$requireStatus ? 'required' : 'nullable', Rule::in(['calculado', 'aprobado', 'pagado', 'anulado'])],
            'observation' => ['nullable', 'string'],
        ], $this->messages());
    }

    private function ensurePeriodDoesNotOverlap(string $startDate, string $endDate, ?int $excludeId = null): void
    {
        $exists = ProfitDistribution::query()
            ->whereIn('status', ['aprobado', 'pagado'])
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->whereDate('start_date', '<', $endDate)
            ->whereDate('end_date', '>', $startDate)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['start_date' => ['El periodo se cruza con una distribución ya confirmada.']]);
        }
    }

    private function validatedPaymentData(Request $request): array
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'voucher_path' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'observation' => ['nullable', 'string'],
        ], $this->messages());

        if (in_array($data['payment_method'], ['yape', 'plin', 'transferencia'], true) && blank($data['payment_reference'] ?? null)) {
            throw ValidationException::withMessages(['payment_reference' => ['La referencia de pago es obligatoria para este metodo de pago.']]);
        }

        return $data;
    }

    private function calculationPayload(float $totalProfit, string $startDate, string $endDate): array
    {
        $monthly = MonthlyProfitAccrual::with('details.member:id,code,dni,full_name,status')
            ->whereIn('status', ['calculada', 'aprobada', 'pagada'])
            ->whereDate('month', '>=', $startDate)->whereDate('month', '<', $endDate)->get();
        if ($monthly->isNotEmpty()) {
            return $this->monthlyConsolidationPayload($monthly, $totalProfit);
        }
        $periodStart = \Carbon\Carbon::parse($startDate)->startOfMonth();
        $closingMonth = \Carbon\Carbon::parse($endDate)->startOfMonth();
        $periodEnd = $closingMonth->copy()->subDay()->endOfDay();
        $rows = MemberShare::query()
            ->where('status', 'registrado')
            ->where('shares_quantity', '>', 0)
            ->whereDate('date', '<', $closingMonth)
            ->with(['member:id,code,dni,full_name,status,retirement_date', 'member.accountClosures' => fn ($query) => $query->latest('id')])
            ->get()
            ->groupBy('member_id')
            ->map(function ($shares) use ($periodStart, $periodEnd) {
                $member = $shares->first()->member;
                if (! $member) return null;

                $alreadyLiquidated = $member->accountClosures->contains(fn ($closure) => $closure->status === 'cerrado'
                    && $closure->utility_status === 'liquidada'
                    && (int) $closure->utility_period_year === (int) $periodStart->year);
                if ($alreadyLiquidated) return null;

                $retirementDate = $member->retirement_date ?: $member->accountClosures->firstWhere('status', 'cerrado')?->retirement_date;
                $memberEnd = $retirementDate
                    ? \Carbon\Carbon::parse($retirementDate)->startOfMonth()->subMonth()->endOfMonth()->min($periodEnd)
                    : $periodEnd->copy();
                $breakdown = $shares->map(function ($share) use ($periodStart, $memberEnd) {
                    $effectiveStart = \Carbon\Carbon::parse($share->date)->startOfMonth()->max($periodStart);
                    if ($effectiveStart->gt($memberEnd)) return null;
                    $months = (int) $effectiveStart->diffInMonths($memberEnd->copy()->startOfMonth()) + 1;
                    $actions = round((float) $share->shares_quantity, 4);

                    return [
                        'share_id' => $share->id,
                        'share_code' => $share->code,
                        'contribution_date' => optional($share->date)->format('Y-m-d'),
                        'effective_from' => $effectiveStart->format('Y-m-d'),
                        'effective_to' => $memberEnd->format('Y-m-d'),
                        'actions' => $actions,
                        'months' => $months,
                        'action_month' => round($actions * $months, 4),
                    ];
                })->filter()->values();

                if ($breakdown->isEmpty()) return null;
                $firstEffectiveMonth = $breakdown->min('effective_from');

                return [
                    'member' => $member,
                    'actions_considered' => round((float) $breakdown->sum('actions'), 4),
                    'months_considered' => (int) \Carbon\Carbon::parse($firstEffectiveMonth)->diffInMonths($memberEnd->copy()->startOfMonth()) + 1,
                    'action_month' => round((float) $breakdown->sum('action_month'), 4),
                    'calculation_breakdown' => $breakdown->all(),
                ];
            })
            ->filter(fn ($row) => $row && (float) $row['action_month'] > 0)
            ->values();

        $totalActionMonth = round((float) $rows->sum('action_month'), 4);

        if ($totalActionMonth <= 0) {
            throw ValidationException::withMessages([
                'total_action_month' => ['No hay aportes válidos para calcular utilidades en este periodo.'],
                'calculation_causes' => [
                    'No existen aportes con utilidad generada dentro del periodo.',
                    'Los aportes pueden ser del mismo mes y recién generar desde el mes siguiente.',
                    'Los socios pueden estar retirados o sus utilidades ya liquidadas.',
                    'Los aportes pueden encontrarse anulados.',
                ],
            ]);
        }

        $profitPerActionMonth = $totalProfit / $totalActionMonth;
        $allocated = 0.0;
        $details = $rows->map(function ($row, $index) use ($rows, $totalActionMonth, $profitPerActionMonth, $totalProfit, &$allocated) {
            $actionMonth = (float) $row['action_month'];
            $amount = $index === $rows->count() - 1
                ? round($totalProfit - $allocated, 2)
                : round($actionMonth * $profitPerActionMonth, 2);
            $allocated += $amount;
            $member = $row['member'];

            return [
                'member_id' => $member->id,
                'member_name' => $member->full_name,
                'member_dni' => $member->dni,
                'member_code' => $member->code,
                'contributions_count' => count($row['calculation_breakdown']),
                'actions_considered' => $row['actions_considered'],
                'months_considered' => $row['months_considered'],
                'action_month' => $actionMonth,
                'calculation_breakdown' => $row['calculation_breakdown'],
                'shares_quantity' => $row['actions_considered'],
                'participation_percentage' => round(($actionMonth / $totalActionMonth) * 100, 4),
                'profit_amount' => $amount,
                'profit_amount_formatted' => $this->money($amount),
                ...$this->utilityMemberStatus($member),
            ];
        })->values();

        return [
            'summary' => [
                'total_profit' => round($totalProfit, 2),
                'total_profit_formatted' => $this->money($totalProfit),
                'total_action_month' => $totalActionMonth,
                'total_action_month_formatted' => number_format($totalActionMonth, 4),
                'profit_per_action_month' => round($profitPerActionMonth, 8),
                'profit_per_action_month_formatted' => 'S/ ' . number_format($profitPerActionMonth, 8),
                // Compatibilidad con documentos y registros anteriores.
                'total_shares' => $totalActionMonth,
                'profit_per_share' => round($profitPerActionMonth, 8),
                'profit_per_share_formatted' => 'S/ ' . number_format($profitPerActionMonth, 8),
                'members_count' => $details->count(),
            ],
            'details' => $details,
        ];
    }

    private function monthlyConsolidationPayload($monthly, float $requestedTotal): array
    {
        $rows=$monthly->flatMap->details->groupBy('member_id')->map(function($details){
            $member=$details->first()->member;$accrued=round((float)$details->sum('profit_amount'),2);$paid=round((float)$details->sum('paid_amount'),2);
            return ['member'=>$member,'accrued'=>$accrued,'paid'=>$paid,'pending'=>round(max(0,$accrued-$paid),2),'shares'=>round((float)$details->sum('shares_quantity'),4),'months'=>$details->count()];
        })->filter(fn($r)=>$r['member']&&$r['pending']>0)->values();
        $pendingTotal=round((float)$rows->sum('pending'),2);
        if($pendingTotal<=0)throw ValidationException::withMessages(['total_action_month'=>['No existen utilidades mensuales pendientes para consolidar.']]);
        if($requestedTotal>$pendingTotal+0.001)throw ValidationException::withMessages(['total_profit'=>['El cierre anual no puede superar la suma pendiente de las utilidades mensuales.']]);
        $allocated=0;$details=$rows->map(function($r,$i)use($rows,$requestedTotal,$pendingTotal,&$allocated){$amount=$i===$rows->count()-1?round($requestedTotal-$allocated,2):round($requestedTotal*$r['pending']/$pendingTotal,2);$allocated+=$amount;$m=$r['member'];return [
            'member_id'=>$m->id,'member_name'=>$m->full_name,'member_dni'=>$m->dni,'member_code'=>$m->code,'contributions_count'=>$r['months'],
            'actions_considered'=>$r['shares'],'months_considered'=>$r['months'],'action_month'=>$r['pending'],'calculation_breakdown'=>[],
            'shares_quantity'=>$r['shares'],'participation_percentage'=>round($r['pending']/$pendingTotal*100,4),'profit_amount'=>$amount,
            'profit_amount_formatted'=>$this->money($amount),...$this->utilityMemberStatus($m)];
        });
        $rate=$requestedTotal/$pendingTotal;
        return ['summary'=>['total_profit'=>round($requestedTotal,2),'total_profit_formatted'=>$this->money($requestedTotal),'total_action_month'=>$pendingTotal,
            'total_action_month_formatted'=>number_format($pendingTotal,4),'profit_per_action_month'=>round($rate,8),'profit_per_action_month_formatted'=>'S/ '.number_format($rate,8),
            'total_shares'=>$pendingTotal,'profit_per_share'=>round($rate,8),'profit_per_share_formatted'=>'S/ '.number_format($rate,8),'members_count'=>$details->count(),
            'monthly_accrued_total'=>$monthly->sum('total_profit'),'monthly_paid_total'=>$monthly->flatMap->details->sum('paid_amount')],'details'=>$details];
    }

    private function utilityMemberStatus(Member $member): array
    {
        $closure = $member->accountClosures->sortByDesc('id')->first();
        if ($closure && in_array($closure->status, ['calculado', 'pendiente_regularizacion', 'en_proceso'], true)) {
            $regularization = $closure->status === 'pendiente_regularizacion'
                || (float) $closure->final_balance < -0.009
                || (float) $closure->total_against > (float) $closure->total_in_favor + 0.009;

            return [
                'member_status' => $regularization ? 'pendiente_regularizacion' : 'en_retiro',
                'member_status_label' => $regularization ? 'Pendiente de regularización' : 'En retiro',
                'member_status_tone' => 'warning',
                'member_status_warning' => $regularization
                    ? 'El socio tiene un cierre pendiente de regularización.'
                    : 'El socio se encuentra en proceso de retiro.',
            ];
        }
        if ($member->status === 'retirado' || $member->retirement_date) {
            return ['member_status' => 'retirado', 'member_status_label' => 'Retirado', 'member_status_tone' => 'secondary', 'member_status_warning' => null];
        }

        return ['member_status' => 'vigente', 'member_status_label' => 'Vigente', 'member_status_tone' => 'success', 'member_status_warning' => null];
    }

    private function distributionPayload(ProfitDistribution $distribution): array
    {
        $distribution->load(['details.member.accountClosures', 'details.receipt', 'details.cashMovement', 'creator', 'calculator', 'approver']);

        return [
            'id' => $distribution->id,
            'code' => $distribution->code,
            'period_year' => $distribution->period_year,
            'period_month' => $distribution->period_month,
            'period' => $this->periodLabel($distribution),
            'start_date' => optional($distribution->start_date)->format('Y-m-d'),
            'start_date_formatted' => optional($distribution->start_date)->format('d/m/Y'),
            'end_date' => optional($distribution->end_date)->format('Y-m-d'),
            'end_date_formatted' => optional($distribution->end_date)->format('d/m/Y'),
            'total_profit' => number_format((float) $distribution->total_profit, 2, '.', ''),
            'total_profit_formatted' => $this->money($distribution->total_profit),
            'total_shares' => $distribution->total_shares,
            'total_action_month' => number_format((float) ((float) $distribution->total_action_month > 0 ? $distribution->total_action_month : $distribution->total_shares), 4, '.', ''),
            'profit_per_share_formatted' => 'S/ ' . number_format((float) ((float) $distribution->profit_per_action_month > 0 ? $distribution->profit_per_action_month : $distribution->profit_per_share), 8),
            'profit_per_action_month_formatted' => 'S/ ' . number_format((float) ((float) $distribution->profit_per_action_month > 0 ? $distribution->profit_per_action_month : $distribution->profit_per_share), 8),
            'status' => $distribution->status,
            'status_label' => $this->statusLabel($distribution->status),
            'approved_at' => optional($distribution->approved_at)->format('d/m/Y H:i'),
            'approved_by_name' => $distribution->approver?->name,
            'calculated_at' => optional($distribution->calculated_at ?: $distribution->created_at)->format('d/m/Y H:i'),
            'calculated_by_name' => $distribution->calculator?->name ?: $distribution->creator?->name,
            'observation' => $distribution->observation,
            'report_url' => route('admin.utilidades.report', $distribution),
            'report_pdf_url' => route('admin.utilidades.report.pdf', $distribution),
            'details' => $distribution->details->map(fn (ProfitDistributionDetail $detail) => $this->detailPayload($detail))->values(),
        ];
    }

    private function detailPayload(ProfitDistributionDetail $detail): array
    {
        $pending = round((float) $detail->profit_amount - (float) $detail->paid_amount, 2);

        return [
            'id' => $detail->id,
            'member_name' => $detail->member?->full_name,
            'member_dni' => $detail->member?->dni,
            'member_code' => $detail->member?->code,
            'contributions_count' => count($detail->calculation_breakdown ?: []),
            'shares_quantity' => number_format((float) $detail->shares_quantity, 2),
            'actions_considered' => number_format((float) ((float) $detail->actions_considered > 0 ? $detail->actions_considered : $detail->shares_quantity), 4, '.', ''),
            'months_considered' => $detail->months_considered,
            'action_month' => number_format((float) ((float) $detail->action_month > 0 ? $detail->action_month : $detail->shares_quantity), 4, '.', ''),
            'calculation_breakdown' => $detail->calculation_breakdown ?: [],
            'participation_percentage' => number_format((float) $detail->participation_percentage, 4) . '%',
            'profit_amount' => number_format((float) $detail->profit_amount, 2, '.', ''),
            'profit_amount_formatted' => $this->money($detail->profit_amount),
            'paid_amount_formatted' => $this->money($detail->paid_amount),
            'pending_amount_formatted' => $this->money($pending),
            'payment_method' => $detail->payment_method,
            'payment_reference' => $detail->payment_reference,
            'voucher_url' => $detail->voucher_path ? Storage::url($detail->voucher_path) : null,
            'voucher_download_url' => $detail->voucher_path ? route('admin.utilidades.voucher', $detail) : null,
            'receipt_url' => $detail->receipt ? route('admin.utilidades.receipt', $detail) : null,
            'receipt_pdf_url' => $detail->receipt ? route('admin.utilidades.receipt.pdf', $detail) : null,
            'status' => $detail->status,
            'status_label' => ucfirst($detail->status),
            ...($detail->member ? $this->utilityMemberStatus($detail->member) : ['member_status' => '-', 'member_status_label' => '-', 'member_status_tone' => 'secondary', 'member_status_warning' => null]),
        ];
    }

    private function syncCashMovement(ProfitDistributionDetail $detail): CashMovement
    {
        $movement = CashMovement::where('related_type', ProfitDistributionDetail::class)->where('related_id', $detail->id)->lockForUpdate()->first();
        $movement ??= new CashMovement(['movement_number' => CashMovement::nextCode(), 'created_by' => auth()->id()]);

        $period = $this->periodLabel($detail->distribution);
        $movement->fill([
            'movement_date' => now()->toDateString(),
            'type' => 'egreso',
            'category' => 'utilidad',
            'concept' => 'Pago de utilidad al socio ' . ($detail->member?->full_name ?? '-') . ' periodo ' . $period,
            'amount' => $detail->paid_amount,
            'payment_method' => $detail->payment_method,
            'reference' => $detail->payment_reference,
            'voucher_path' => $detail->voucher_path,
            'related_type' => ProfitDistributionDetail::class,
            'related_id' => $detail->id,
            'observation' => $detail->observation,
            'status' => 'registrado',
            'updated_by' => auth()->id(),
        ]);
        $movement->save();
        app(ShareCashMovementService::class)->recalculateBalances();

        return $movement->fresh();
    }

    private function createOrUpdateReceipt(ProfitDistributionDetail $detail): Receipt
    {
        $receipt = Receipt::firstOrNew(['related_type' => ProfitDistributionDetail::class, 'related_id' => $detail->id]);
        if (! $receipt->exists) {
            $receipt->receipt_number = $this->generateNextReceiptNumber();
            $receipt->created_by = auth()->id();
        }
        $receipt->fill([
            'receipt_date' => now()->toDateString(),
            'member_id' => $detail->member_id,
            'type' => 'utilidad',
            'amount' => $detail->paid_amount,
            'payment_method' => $detail->payment_method,
            'payment_reference' => $detail->payment_reference,
            'voucher_path' => $detail->voucher_path,
            'observation' => 'Pago de utilidad ' . $this->periodLabel($detail->distribution),
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

    private function storeVoucher(Request $request, array &$data, ProfitDistributionDetail $detail): void
    {
        if (! $request->hasFile('voucher_path')) {
            unset($data['voucher_path']);
            return;
        }
        if ($detail->voucher_path) {
            Storage::disk('public')->delete($detail->voucher_path);
        }
        $data['voucher_path'] = $request->file('voucher_path')->store('profit-distributions', 'public');
    }

    private function markDistributionPaidIfComplete(ProfitDistribution $distribution): void
    {
        if ($distribution->details()->where('status', '!=', 'pagado')->doesntExist()) {
            $distribution->update(['status' => 'pagado', 'paid_at' => now(), 'paid_by' => auth()->id(), 'updated_by' => auth()->id()]);
        }
    }

    private function generateNextReceiptNumber(): string
    {
        $lastCode = Receipt::withTrashed()->whereNotNull('receipt_number')->where('receipt_number', 'like', 'REC-%')->orderByDesc('id')->lockForUpdate()->value('receipt_number');
        $lastNumber = $lastCode && preg_match('/REC-(\d+)/', $lastCode, $matches) ? (int) $matches[1] : 0;
        return 'REC-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function periodLabel(ProfitDistribution $distribution): string
    {
        return ($distribution->period_month ? str_pad((string) $distribution->period_month, 2, '0', STR_PAD_LEFT) . '/' : '') . $distribution->period_year;
    }

    private function months(): array
    {
        return [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    }

    private function paymentMethods(): array
    {
        return ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin', 'transferencia' => 'Transferencia', 'otro' => 'Otro'];
    }

    private function statusBadge(?string $status): string
    {
        $class = match ($status) {
            'aprobado' => 'info',
            'pagado' => 'success',
            'anulado' => 'danger',
            default => 'secondary',
        };
        return '<span class="badge badge-' . $class . '">' . e($this->statusLabel($status)) . '</span>';
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'calculado' => 'Calculado',
            'aprobado' => 'Aprobado',
            'pagado' => 'Pagado',
            'anulado' => 'Anulado',
            default => '-',
        };
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function availabilityPayload(array $summary): array
    {
        return $summary + [
            'generated_formatted' => $this->money($summary['generated']),
            'distributed_formatted' => $this->money($summary['distributed']),
            'available_formatted' => $this->money($summary['available']),
            'remaining_formatted' => $this->money($summary['remaining'] ?? $summary['available']),
            'interest_collected_formatted' => $this->money($summary['interestCollected'] ?? 0),
            'late_fees_collected_formatted' => $this->money($summary['lateFeesCollected'] ?? 0),
            'positive_adjustments_formatted' => $this->money($summary['positiveAdjustments'] ?? 0),
            'negative_adjustments_formatted' => $this->money($summary['negativeAdjustments'] ?? 0),
            'generated_formatted' => $this->money($summary['generated'] ?? 0),
            'contributions_total_formatted' => $this->money($summary['contributions_total'] ?? 0),
        ];
    }

    private function messages(): array
    {
        return [
            'period_year.required' => 'El anio es obligatorio.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'end_date.required' => 'La fecha fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha fin no puede ser menor a la fecha de inicio.',
            'total_profit.required' => 'La utilidad total es obligatoria.',
            'total_profit.min' => 'La utilidad total debe ser mayor a cero.',
            'status.required' => 'Seleccione un estado valido.',
            'status.in' => 'Seleccione un estado valido.',
            'payment_method.required' => 'Seleccione un metodo de pago valido.',
            'payment_method.in' => 'Seleccione un metodo de pago valido.',
            'voucher_path.file' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.mimes' => 'El comprobante debe ser una imagen o PDF valido.',
            'voucher_path.max' => 'El comprobante no debe superar los 4 MB.',
        ];
    }
}
