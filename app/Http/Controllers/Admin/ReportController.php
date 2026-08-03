<?php

namespace App\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityMovement;
use App\Models\CashMovement;
use App\Models\CreditHistory;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanRefinancing;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberShare;
use App\Models\ProfitDistributionDetail;
use App\Models\SolidarityMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const PAYMENT_METHODS = ['efectivo', 'yape', 'plin', 'transferencia', 'otro'];

    public function __construct()
    {
        $this->middleware('can:reportes.index')->only(['index']);

        foreach ($this->reportPermissions() as $method => $permission) {
            $this->middleware("can:{$permission}")->only([$method]);
        }

        $this->middleware('can:reportes.print')->only(['print']);
        $this->middleware('can:reportes.pdf')->only(['pdf']);
        $this->middleware('can:reportes.excel')->only(['excel']);
    }

    public function index()
    {
        return view('admin.reports.index', [
            'cards' => $this->dashboardCards(),
        ]);
    }

    public function activeMembers(Request $request)
    {
        return $this->renderReport($request, 'socios-vigentes');
    }

    public function retiredMembers(Request $request)
    {
        return $this->renderReport($request, 'socios-retirados');
    }

    public function sharesByMember(Request $request)
    {
        return $this->renderReport($request, 'acciones-por-socio');
    }

    public function sharesMonthly(Request $request)
    {
        return $this->renderReport($request, 'acciones-mensual');
    }

    public function sharesAnnual(Request $request)
    {
        return $this->renderReport($request, 'acciones-anual');
    }

    public function majorityMember(Request $request)
    {
        return $this->renderReport($request, 'socio-mayoritario');
    }

    public function sharesGeneral(Request $request)
    {
        return $this->renderReport($request, 'acciones-general');
    }

    public function activeLoans(Request $request)
    {
        return $this->renderReport($request, 'prestamos-activos');
    }

    public function paidLoans(Request $request)
    {
        return $this->renderReport($request, 'prestamos-pagados');
    }

    public function overdueLoans(Request $request)
    {
        return $this->renderReport($request, 'prestamos-vencidos');
    }

    public function memberHistory(Request $request)
    {
        return $this->renderReport($request, 'historial-socio');
    }

    public function creditHistory(Request $request)
    {
        return $this->renderReport($request, 'historial-crediticio');
    }

    public function dailyPayments(Request $request)
    {
        return $this->renderReport($request, 'cobros-diarios');
    }

    public function monthlyPayments(Request $request)
    {
        return $this->renderReport($request, 'cobros-mensuales');
    }

    public function cashGeneral(Request $request)
    {
        return $this->renderReport($request, 'caja-general');
    }

    public function solidarityReport(Request $request)
    {
        return $this->renderReport($request, 'solidaridad');
    }

    public function activitiesReport(Request $request)
    {
        return $this->renderReport($request, 'actividades');
    }

    public function profitsByMember(Request $request)
    {
        return $this->renderReport($request, 'utilidades-socio');
    }

    public function activityDetail(Activity $activity)
    {
        $activity->load(['movements.member']);

        return view('admin.activities.report', compact('activity'));
    }

    public function print(Request $request, string $type)
    {
        $payload = $this->payload($request, $type, true);

        return view('admin.reports.print', $payload);
    }

    public function pdf(Request $request, string $type)
    {
        $payload = $this->payload($request, $type, true);
        $payload['pdfMode'] = true;

        return Pdf::loadView('admin.reports.print', $payload)
            ->setPaper('a4', count($payload['report']['columns']) > 6 ? 'landscape' : 'portrait')
            ->stream('Reporte ' . $payload['definition']['title'] . ' ' . now()->format('Y-m-d') . '.pdf');
    }

    public function excel(Request $request, string $type): StreamedResponse
    {
        $payload = $this->payload($request, $type, true);
        $filename = 'reporte-' . $type . '-' . now()->format('Ymd-His') . '.csv';
        $headers = $payload['report']['columns'];
        $rows = $payload['report']['rows'];

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values($headers), ';');

            foreach ($rows as $row) {
                fputcsv($handle, collect(array_keys($headers))->map(fn ($key) => $this->plainValue($row[$key] ?? ''))->all(), ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function renderReport(Request $request, string $type)
    {
        return view('admin.reports.show', $this->payload($request, $type));
    }

    private function payload(Request $request, string $type, bool $forExport = false): array
    {
        $definition = $this->definition($type);
        abort_unless($request->user()?->can($definition['permission']), 403);
        $filters = $this->validatedFilters($request, $definition, $forExport);
        $report = $this->buildReport($type, $filters);

        return [
            'type' => $type,
            'definition' => $definition,
            'report' => $report,
            'filters' => $filters,
            'filterLabels' => $this->filterLabels($filters),
            'members' => Member::orderBy('full_name')->get(['id', 'code', 'dni', 'full_name', 'status']),
            'activities' => Activity::orderByDesc('activity_date')->get(['id', 'code', 'name']),
            'months' => $this->months(),
            'paymentMethods' => $this->paymentMethods(),
            'generatedAt' => now(),
            'generatedBy' => auth()->user()?->name ?? '-',
            'isPrint' => $forExport,
        ];
    }

    private function buildReport(string $type, array $filters): array
    {
        return match ($type) {
            'socios-vigentes' => $this->activeMembersReport($filters),
            'socios-retirados' => $this->retiredMembersReport($filters),
            'acciones-por-socio' => $this->sharesByMemberReport($filters),
            'acciones-mensual' => $this->sharesMonthlyReport($filters),
            'acciones-anual' => $this->sharesAnnualReport($filters),
            'socio-mayoritario' => $this->majorityMemberReport($filters),
            'acciones-general' => $this->sharesGeneralReport($filters),
            'prestamos-activos' => $this->activeLoansReport($filters),
            'prestamos-pagados' => $this->paidLoansReport($filters),
            'prestamos-vencidos' => $this->overdueLoansReport($filters),
            'historial-socio' => $this->memberHistoryReport($filters),
            'historial-crediticio' => $this->creditHistoryReport($filters),
            'cobros-diarios' => $this->dailyPaymentsReport($filters),
            'cobros-mensuales' => $this->monthlyPaymentsReport($filters),
            'caja-general' => $this->cashGeneralReport($filters),
            'solidaridad' => $this->solidarityReportData($filters),
            'actividades' => $this->activitiesReportData($filters),
            'utilidades-socio' => $this->profitsByMemberReport($filters),
            default => abort(404),
        };
    }

    private function activeMembersReport(array $filters): array
    {
        $rows = Member::query()
            ->where('status', 'vigente')
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('admission_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('admission_date', '<=', $date))
            ->when($filters['civil_status'] ?? null, fn ($query, $status) => $query->where('civil_status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $this->memberSearch($query, $search))
            ->withSum(['shares as total_amount' => fn ($query) => $query->where('status', 'registrado')], 'amount')
            ->withSum(['shares as total_shares' => fn ($query) => $query->where('status', 'registrado')], 'shares_quantity')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Member $member, int $index) => [
                'index' => $index + 1,
                'code' => $member->code,
                'dni' => $member->dni,
                'member' => $member->full_name,
                'phone' => $member->phone ?: '-',
                'admission_date' => $this->date($member->admission_date),
                'shares' => $this->quantity($member->total_shares),
                'amount' => $this->money($member->total_amount),
                'status' => $this->badge($member->status),
            ]);

        return [
            'summary' => [
                'Total socios vigentes' => $rows->count(),
                'Total acciones' => $this->quantity($rows->sum(fn ($row) => (float) str_replace(',', '', $row['shares']))),
                'Total aportes' => $this->moneyFromRows($rows, 'amount'),
            ],
            'columns' => ['index' => '#', 'code' => 'Codigo', 'dni' => 'DNI', 'member' => 'Socio', 'phone' => 'Telefono', 'admission_date' => 'Fecha ingreso', 'shares' => 'Acciones', 'amount' => 'Aportes', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function retiredMembersReport(array $filters): array
    {
        $closures = MemberAccountClosure::query()->where('status', 'cerrado');
        $rows = Member::query()
            ->whereIn('status', ['retirado', 'no_vigente'])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('retirement_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('retirement_date', '<=', $date))
            ->when($filters['search'] ?? null, fn ($query, $search) => $this->memberSearch($query, $search))
            ->with(['accountClosures' => fn ($query) => $query->latest('closure_date')])
            ->orderByDesc('retirement_date')
            ->get()
            ->map(function (Member $member, int $index) {
                $closure = $member->accountClosures->first();

                return [
                    'index' => $index + 1,
                    'code' => $member->code,
                    'dni' => $member->dni,
                    'member' => $member->full_name,
                    'admission_date' => $this->date($member->admission_date),
                    'retirement_date' => $this->date($member->retirement_date),
                    'reason' => $closure?->reason ?: $member->observation ?: '-',
                    'final_balance' => $this->money($closure?->final_balance ?? 0),
                    'status' => $this->badge($member->status),
                ];
            });

        return [
            'summary' => [
                'Total socios retirados' => $rows->count(),
                'Cierres registrados' => (clone $closures)->count(),
                'Total devuelto' => $this->money((clone $closures)->where('final_balance', '>', 0)->sum('final_balance')),
                'Saldos en contra' => $this->money(abs((float) (clone $closures)->where('final_balance', '<', 0)->sum('final_balance'))),
            ],
            'columns' => ['index' => '#', 'code' => 'Codigo', 'dni' => 'DNI', 'member' => 'Socio', 'admission_date' => 'Fecha ingreso', 'retirement_date' => 'Fecha retiro', 'reason' => 'Motivo', 'final_balance' => 'Saldo final', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function sharesByMemberReport(array $filters): array
    {
        $base = $this->shareQuery($filters);
        $rows = (clone $base)
            ->selectRaw('member_id, SUM(total_paid) as total_received, SUM(share_capital_amount) as total_amount, SUM(solidarity_amount) as solidarity, SUM(administrative_fee_amount) as administrative_fees, AVG(share_value) as average_share_value, SUM(shares_quantity) as shares_quantity, MAX(date) as last_share_date')
            ->groupBy('member_id')
            ->with('member:id,code,dni,full_name,status')
            ->get()
            ->sortByDesc('shares_quantity')
            ->values()
            ->map(fn (MemberShare $share, int $index) => [
                'index' => $index + 1,
                'code' => $share->member?->code,
                'dni' => $share->member?->dni,
                'member' => $share->member?->full_name,
                'total_amount' => $this->money($share->total_amount),
                'total_received' => $this->money($share->total_received), 'solidarity' => $this->money($share->solidarity), 'administrative_fees' => $this->money($share->administrative_fees),
                'average_share_value' => $this->money($share->average_share_value),
                'shares_quantity' => $this->quantity($share->shares_quantity),
                'last_share_date' => $this->date($share->last_share_date),
                'status' => $this->badge($share->member?->status),
            ]);
        $major = $rows->first();

        return [
            'summary' => [
                'Socios con acciones' => $rows->count(),
                'Total aportado' => $this->moneyFromRows($rows, 'total_amount'),
                'Total acciones' => $this->quantityFromRows($rows, 'shares_quantity'),
                'Socio mayoritario' => $major ? $major['member'] : '-',
            ],
            'columns' => ['index' => '#', 'code' => 'Codigo socio', 'dni' => 'DNI', 'member' => 'Socio', 'total_received' => 'Total recibido', 'total_amount' => 'Capital acciones', 'solidarity' => 'Solidaridad', 'administrative_fees' => 'Gastos administrativos', 'average_share_value' => 'Valor accion', 'shares_quantity' => 'Acciones', 'last_share_date' => 'Ultimo aporte', 'status' => 'Estado socio'],
            'rows' => $rows,
        ];
    }

    private function sharesMonthlyReport(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);
        $rows = $this->shareQuery($filters)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with('member:id,code,dni,full_name')
            ->orderByDesc('date')
            ->get()
            ->map(fn (MemberShare $share, int $index) => [
                'index' => $index + 1,
                'date' => $this->date($share->date),
                'code' => $share->code,
                'member' => $share->member?->full_name,
                'dni' => $share->member?->dni,
                'amount' => $this->money($share->amount),
                'total_received' => $this->money($share->total_paid), 'solidarity' => $this->money($share->solidarity_amount), 'administrative_fees' => $this->money($share->administrative_fee_amount),
                'share_value' => $this->money($share->share_value),
                'shares_quantity' => $this->quantity($share->shares_quantity),
                'payment_method' => $this->label($share->payment_method),
                'status' => $this->badge($share->status),
            ]);
        $major = $this->maxShareMember($year, $month);

        return [
            'summary' => [
                'Total aportado del mes' => $this->moneyFromRows($rows, 'amount'),
                'Total acciones del mes' => $this->quantityFromRows($rows, 'shares_quantity'),
                'Cantidad de aportes' => $rows->count(),
                'Mayor aporte del mes' => $major,
            ],
            'columns' => ['index' => '#', 'date' => 'Fecha', 'code' => 'Codigo aporte', 'member' => 'Socio', 'dni' => 'DNI', 'total_received' => 'Total recibido', 'amount' => 'Capital acciones', 'solidarity' => 'Solidaridad', 'administrative_fees' => 'Gastos administrativos', 'share_value' => 'Valor accion', 'shares_quantity' => 'Acciones', 'payment_method' => 'Metodo pago', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function sharesAnnualReport(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $query = $this->shareQuery($filters)->whereYear('date', $year);
        $rows = (clone $query)
            ->orderBy('date')
            ->get(['date', 'total_paid', 'share_capital_amount', 'solidarity_amount', 'administrative_fee_amount', 'shares_quantity'])
            ->groupBy(fn (MemberShare $share) => (int) Carbon::parse($share->date)->month)
            ->map(fn (Collection $shares, int $month) => [
                'month' => $this->months()[$month] ?? '-',
                'total_received' => $this->money($shares->sum('total_paid')),
                'total_amount' => $this->money($shares->sum('share_capital_amount')),
                'solidarity' => $this->money($shares->sum('solidarity_amount')),
                'administrative_fees' => $this->money($shares->sum('administrative_fee_amount')),
                'shares_quantity' => $this->quantity($shares->sum('shares_quantity')),
                'records' => $shares->count(),
            ]);
        $bestMonth = $rows->sortByDesc(fn ($row) => $this->numeric($row['total_amount']))->first();
        $major = $this->maxShareMember($year);

        return [
            'summary' => [
                'Total aportado del anio' => $this->moneyFromRows($rows, 'total_amount'),
                'Total acciones del anio' => $this->quantityFromRows($rows, 'shares_quantity'),
                'Mes con mayor aporte' => $bestMonth['month'] ?? '-',
                'Socio mayoritario del anio' => $major,
            ],
            'columns' => ['month' => 'Mes', 'total_received' => 'Total recibido', 'total_amount' => 'Capital acciones', 'solidarity' => 'Solidaridad', 'administrative_fees' => 'Gastos administrativos', 'shares_quantity' => 'Total acciones', 'records' => 'Cantidad aportes'],
            'rows' => $rows,
        ];
    }

    private function majorityMemberReport(array $filters): array
    {
        $base = $this->shareQuery($filters);
        $totalShares = (float) (clone $base)->sum('shares_quantity');
        $rows = (clone $base)
            ->selectRaw('member_id, SUM(amount) as total_amount, SUM(shares_quantity) as shares_quantity')
            ->groupBy('member_id')
            ->with('member:id,code,dni,full_name,status')
            ->get()
            ->sortByDesc('shares_quantity')
            ->values()
            ->map(fn ($row, int $index) => [
                'rank' => $index + 1,
                'code' => $row->member?->code,
                'dni' => $row->member?->dni,
                'member' => $row->member?->full_name,
                'total_amount' => $this->money($row->total_amount),
                'shares_quantity' => $this->quantity($row->shares_quantity),
                'participation' => $totalShares > 0 ? number_format(((float) $row->shares_quantity / $totalShares) * 100, 4) . '%' : '0%',
                'status' => $this->badge($row->member?->status),
            ]);
        $major = $rows->first();

        return [
            'summary' => [
                'Socio mayoritario' => $major['member'] ?? '-',
                'Total acciones del sistema' => $this->quantity($totalShares),
                'Mayor participacion' => $major['participation'] ?? '0%',
            ],
            'columns' => ['rank' => 'Puesto', 'code' => 'Codigo socio', 'dni' => 'DNI', 'member' => 'Socio', 'total_amount' => 'Total aportado', 'shares_quantity' => 'Acciones', 'participation' => 'Participacion', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function sharesGeneralReport(array $filters): array
    {
        $query = $this->shareQuery($filters, false);
        $rows = (clone $query)->with('member:id,full_name,dni')->orderByDesc('date')->get()->map(fn (MemberShare $share) => [
            'date' => $this->date($share->date),
            'code' => $share->code,
            'member' => $share->member?->full_name,
            'amount' => $this->money($share->amount),
            'total_received' => $this->money($share->total_paid), 'solidarity' => $this->money($share->solidarity_amount), 'administrative_fees' => $this->money($share->administrative_fee_amount),
            'shares_quantity' => $this->quantity($share->shares_quantity),
            'payment_method' => $this->label($share->payment_method),
            'status' => $this->badge($share->status),
        ]);

        return [
            'summary' => [
                'Total aportado general' => $this->money((clone $query)->where('status', 'registrado')->sum('amount')),
                'Total acciones general' => $this->quantity((clone $query)->where('status', 'registrado')->sum('shares_quantity')),
                'Socios con acciones' => (clone $query)->where('status', 'registrado')->distinct('member_id')->count('member_id'),
                'Aportes registrados' => (clone $query)->where('status', 'registrado')->count(),
                'Aportes anulados' => (clone $query)->where('status', 'anulado')->count(),
            ],
            'columns' => ['date' => 'Fecha', 'code' => 'Codigo aporte', 'member' => 'Socio', 'total_received' => 'Total recibido', 'amount' => 'Capital acciones', 'solidarity' => 'Solidaridad', 'administrative_fees' => 'Gastos administrativos', 'shares_quantity' => 'Acciones', 'payment_method' => 'Metodo pago', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function activeLoansReport(array $filters): array
    {
        $query = Loan::query()
            ->whereIn('status', ['desembolsado', 'refinanciado'])
            ->where('current_balance', '>', 0)
            ->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id))
            ->with(['member:id,dni,full_name', 'installments']);
        $rows = (clone $query)->orderByDesc('disbursed_at')->get()->map(fn (Loan $loan) => [
            'loan_number' => $loan->loan_number,
            'member' => $loan->member?->full_name,
            'dni' => $loan->member?->dni,
            'approved_amount' => $this->money($loan->approved_amount),
            'total_amount' => $this->money($loan->total_amount),
            'current_balance' => $this->money($loan->current_balance),
            'pending_installments' => $loan->installments->whereNotIn('status', ['pagado', 'anulado', 'refinanciado'])->count(),
            'overdue_installments' => $this->overdueInstallments($loan->installments)->count(),
            'status' => $this->badge($loan->status),
        ]);

        return [
            'summary' => [
                'Prestamos activos' => $rows->count(),
                'Total desembolsado' => $this->money((clone $query)->sum('disbursed_amount')),
                'Saldo por cobrar' => $this->money((clone $query)->sum('current_balance')),
                'Cuotas vencidas' => $rows->sum('overdue_installments'),
            ],
            'columns' => ['loan_number' => 'Codigo prestamo', 'member' => 'Socio', 'dni' => 'DNI', 'approved_amount' => 'Monto aprobado', 'total_amount' => 'Total a pagar', 'current_balance' => 'Saldo actual', 'pending_installments' => 'Cuotas pendientes', 'overdue_installments' => 'Cuotas vencidas', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function paidLoansReport(array $filters): array
    {
        $query = Loan::query()->where('status', 'pagado')->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id))->with(['member:id,full_name']);
        $rows = (clone $query)->with('payments')->orderByDesc('updated_at')->get()->map(fn (Loan $loan) => [
            'loan_number' => $loan->loan_number,
            'member' => $loan->member?->full_name,
            'approved_amount' => $this->money($loan->approved_amount),
            'paid_amount' => $this->money($loan->payments->where('status', 'registrado')->sum('amount')),
            'interest' => $this->money($loan->total_interest),
            'disbursed_at' => $this->date($loan->disbursed_at),
            'paid_at' => $this->date($loan->payments->where('status', 'registrado')->max('payment_date')),
            'status' => $this->badge($loan->status),
        ]);

        return [
            'summary' => [
                'Prestamos pagados' => $rows->count(),
                'Capital recuperado' => $this->money((clone $query)->sum('approved_amount')),
                'Intereses cobrados' => $this->money((clone $query)->sum('total_interest')),
            ],
            'columns' => ['loan_number' => 'Codigo prestamo', 'member' => 'Socio', 'approved_amount' => 'Monto aprobado', 'paid_amount' => 'Total pagado', 'interest' => 'Total interes', 'disbursed_at' => 'Fecha desembolso', 'paid_at' => 'Fecha pago final', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function overdueLoansReport(array $filters): array
    {
        $today = today();
        $installments = LoanInstallment::query()
            ->whereDate('due_date', '<', $today)
            ->whereNotIn('status', ['pagado', 'anulado', 'refinanciado'])
            ->where('remaining_amount', '>', 0)
            ->with('loan.member:id,dni,full_name')
            ->when($filters['member_id'] ?? null, fn ($query, $id) => $query->whereHas('loan', fn ($loan) => $loan->where('member_id', $id)))
            ->get()
            ->groupBy('loan_id');
        $rows = $installments->map(function (Collection $items) use ($today) {
            $loan = $items->first()->loan;
            $oldest = $items->min('due_date');

            return [
                'loan_number' => $loan?->loan_number,
                'member' => $loan?->member?->full_name,
                'dni' => $loan?->member?->dni,
                'oldest_due_date' => $this->date($oldest),
                'overdue_installments' => $items->count(),
                'overdue_balance' => $this->money($items->sum('remaining_amount')),
                'current_balance' => $this->money($loan?->current_balance ?? 0),
                'days_late' => $oldest ? Carbon::parse($oldest)->diffInDays($today) : 0,
                'status' => $this->badge($loan?->status),
            ];
        })->sortByDesc(fn ($row) => $this->numeric($row['overdue_balance']))->values();

        return [
            'summary' => [
                'Prestamos vencidos' => $rows->count(),
                'Saldo vencido' => $this->moneyFromRows($rows, 'overdue_balance'),
                'Cuotas vencidas' => $rows->sum('overdue_installments'),
                'Mayor deuda vencida' => $rows->first()['member'] ?? '-',
            ],
            'columns' => ['loan_number' => 'Codigo prestamo', 'member' => 'Socio', 'dni' => 'DNI', 'oldest_due_date' => 'Cuota mas antigua', 'overdue_installments' => 'Cuotas vencidas', 'overdue_balance' => 'Saldo vencido', 'current_balance' => 'Saldo total', 'days_late' => 'Dias de atraso', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function memberHistoryReport(array $filters): array
    {
        $member = null;
        if ($filters['member_id'] ?? null) {
            $member = Member::with([
                'shares' => fn ($query) => $this->applyDateRange($query, $filters, 'date')->orderByDesc('date'),
                'loans.installments',
                'loanPayments.loan',
                'profitDistributionDetails.distribution',
                'accountClosures',
            ])->find($filters['member_id']);
        }

        $rows = collect();
        $summary = ['Seleccione un socio' => $member ? $member->full_name : 'Pendiente'];

        if ($member) {
            $shares = $member->shares->where('status', 'registrado');
            $payments = $member->loanPayments->where('status', 'registrado');
            $profitDetails = $member->profitDistributionDetails;
            $summary = [
                'Total aportado' => $this->money($shares->sum('amount')),
                'Total acciones' => $this->quantity($shares->sum('shares_quantity')),
                'Total prestamos' => $member->loans->count(),
                'Total cobrado' => $this->money($payments->sum('amount')),
                'Saldo pendiente' => $this->money($member->loans->sum('current_balance')),
                'Utilidades pendientes' => $this->money($profitDetails->sum(fn ($detail) => max(0, (float) $detail->profit_amount - (float) $detail->paid_amount))),
                'Utilidades pagadas' => $this->money($profitDetails->sum('paid_amount')),
            ];
        }

        return [
            'summary' => $summary,
            'columns' => ['section' => 'Seccion', 'date' => 'Fecha', 'code' => 'Codigo', 'concept' => 'Concepto', 'amount' => 'Monto', 'status' => 'Estado'],
            'rows' => $rows,
            'member' => $member,
        ];
    }

    private function dailyPaymentsReport(array $filters): array
    {
        $date = $filters['date'] ?? today()->toDateString();
        $dateBasis = $filters['date_basis'] ?? 'payment_date';
        $dateColumn = $dateBasis === 'registered_at' ? 'created_at' : 'payment_date';
        $historicalFilter = $filters['include_historical'] ?? '1';
        $cashFilter = $filters['affects_cash'] ?? 'all';
        $query = LoanPayment::query()
            ->whereDate($dateColumn, $date)
            ->when($historicalFilter === '0', fn ($query) => $query->where('is_historical', false))
            ->when($historicalFilter === 'only', fn ($query) => $query->where('is_historical', true))
            ->when($cashFilter !== 'all', fn ($query) => $query->where('affects_cash', $cashFilter === '1'))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('created_by', $id))
            ->with(['member:id,full_name', 'loan:id,loan_number']);
        $rows = (clone $query)->orderByDesc('payment_date')->get()->map(fn (LoanPayment $payment) => [
            'payment_number' => $payment->payment_number,
            'payment_date' => $this->date($payment->payment_date),
            'registered_at' => optional($payment->created_at)->format('d/m/Y H:i'),
            'member' => $payment->member?->full_name,
            'loan' => $payment->loan?->loan_number,
            'payment_type' => $this->label($payment->payment_type),
            'payment_method' => $this->label($payment->payment_method),
            'amount' => $this->money($payment->amount),
            'flags' => $this->paymentFlags($payment),
            'status' => $this->badge($payment->status),
        ]);
        $valid = (clone $query)->where('status', 'registrado');

        return [
            'summary' => [
                'Total cobrado que afecta caja' => $this->money((clone $valid)->where('affects_cash', true)->sum('amount')),
                'Total histórico no afecta caja' => $this->money((clone $valid)->where('is_historical', true)->where('affects_cash', false)->sum('amount')),
                'Interés para utilidades' => $this->money((clone $valid)->where('affects_profit', true)->where('profit_treatment', 'eligible')->sum('interest_amount')),
                'Mora cobrada para utilidades' => $this->money((clone $valid)->where('affects_profit', true)->where('profit_treatment', 'eligible')->sum('late_fee_paid')),
            ],
            'columns' => ['payment_number' => 'Código cobro', 'payment_date' => 'Fecha real', 'registered_at' => 'Fecha registro', 'member' => 'Socio', 'loan' => 'Préstamo', 'payment_type' => 'Tipo pago', 'payment_method' => 'Método pago', 'amount' => 'Monto', 'flags' => 'Clasificación', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function monthlyPaymentsReport(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);
        $query = LoanPayment::query()
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->where('status', 'registrado')
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id));
        $rows = (clone $query)
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total_amount, COUNT(*) as records')
            ->selectRaw("SUM(CASE WHEN payment_method = 'efectivo' THEN amount ELSE 0 END) as efectivo")
            ->selectRaw("SUM(CASE WHEN payment_method = 'yape' THEN amount ELSE 0 END) as yape")
            ->selectRaw("SUM(CASE WHEN payment_method = 'plin' THEN amount ELSE 0 END) as plin")
            ->selectRaw("SUM(CASE WHEN payment_method = 'transferencia' THEN amount ELSE 0 END) as transferencia")
            ->groupByRaw('DATE(payment_date)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $this->date($row->date),
                'total_amount' => $this->money($row->total_amount),
                'records' => $row->records,
                'efectivo' => $this->money($row->efectivo),
                'yape' => $this->money($row->yape),
                'plin' => $this->money($row->plin),
                'transferencia' => $this->money($row->transferencia),
            ]);
        $bestDay = $rows->sortByDesc(fn ($row) => $this->numeric($row['total_amount']))->first();
        $methodRows = (clone $query)->selectRaw('payment_method, COUNT(*) as records')->groupBy('payment_method')->orderByDesc('records')->first();

        return [
            'summary' => [
                'Total cobrado que afecta caja' => $this->money((clone $query)->where('affects_cash', true)->sum('amount')),
                'Total histórico no afecta caja' => $this->money((clone $query)->where('is_historical', true)->where('affects_cash', false)->sum('amount')),
                'Interés para utilidades' => $this->money((clone $query)->where('affects_profit', true)->where('profit_treatment', 'eligible')->sum('interest_amount')),
                'Mora cobrada para utilidades' => $this->money((clone $query)->where('affects_profit', true)->where('profit_treatment', 'eligible')->sum('late_fee_paid')),
                'Dia con mayor cobro' => $bestDay['date'] ?? '-',
                'Metodo mas usado' => $this->label($methodRows?->payment_method),
            ],
            'columns' => ['date' => 'Fecha', 'total_amount' => 'Total cobrado', 'records' => 'Cantidad cobros', 'efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin', 'transferencia' => 'Transferencia'],
            'rows' => $rows,
        ];
    }

    private function cashGeneralReport(array $filters): array
    {
        $query = CashMovement::query();
        $this->applyDateRange($query, $filters, 'movement_date');
        $query->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $valid = (clone $query)->where('status', '!=', 'anulado');
        $rows = (clone $query)->orderByDesc('movement_date')->orderByDesc('id')->get()->map(fn (CashMovement $movement) => [
            'code' => $movement->movement_number,
            'date' => $this->date($movement->movement_date),
            'type' => $this->badge($movement->type),
            'category' => $this->label($movement->category),
            'concept' => $movement->concept,
            'payment_method' => $this->label($movement->payment_method),
            'income' => $movement->type === 'ingreso' ? $this->money($movement->amount) : '-',
            'expense' => $movement->type === 'egreso' ? $this->money($movement->amount) : '-',
            'balance_after' => $this->money($movement->balance_after),
            'status' => $this->badge($movement->status),
        ]);
        $historicalTotal = 0.0;
        if (($filters['cash_include_historical'] ?? '0') === '1') {
            $historical = LoanPayment::query()
                ->where('is_historical', true)
                ->where('affects_cash', false)
                ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('payment_date', '<=', $date))
                ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->orderByDesc('payment_date')
                ->get();
            $historicalTotal = (float) $historical->where('status', 'registrado')->sum('amount');
            $rows = $rows->concat($historical->map(fn (LoanPayment $payment) => [
                'code' => $payment->payment_number,
                'date' => $this->date($payment->payment_date),
                'type' => '<span class="badge badge-info">Histórico</span>',
                'category' => 'Cobro histórico',
                'concept' => 'Movimiento histórico sin efecto en saldo',
                'payment_method' => $this->label($payment->payment_method),
                'income' => '-',
                'expense' => '-',
                'balance_after' => '<span class="text-muted">Sin efecto</span>',
                'status' => $this->paymentFlags($payment),
            ]));
        }

        return [
            'summary' => [
                'Saldo actual' => $this->money((float) CashMovement::where('status', '!=', 'anulado')->latest('movement_date')->latest('id')->value('balance_after')),
                'Total ingresos' => $this->money((clone $valid)->where('type', 'ingreso')->sum('amount')),
                'Total egresos' => $this->money((clone $valid)->where('type', 'egreso')->sum('amount')),
                'Movimientos del periodo' => (clone $query)->count(),
                'Cobros históricos sin efecto' => $this->money($historicalTotal),
            ],
            'columns' => ['code' => 'Codigo', 'date' => 'Fecha', 'type' => 'Tipo', 'category' => 'Categoria', 'concept' => 'Concepto', 'payment_method' => 'Metodo pago', 'income' => 'Ingreso', 'expense' => 'Egreso', 'balance_after' => 'Saldo posterior', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function solidarityReportData(array $filters): array
    {
        $query = SolidarityMovement::query()->with('member:id,full_name');
        $this->applyDateRange($query, $filters, 'movement_date');
        $query->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $valid = (clone $query)->where('status', '!=', 'anulado');
        $rows = (clone $query)->orderByDesc('movement_date')->get()->map(fn (SolidarityMovement $movement) => [
            'code' => $movement->code,
            'date' => $this->date($movement->movement_date ?: $movement->date),
            'type' => $this->badge($movement->type),
            'member' => $movement->member?->full_name ?: '-',
            'concept' => $movement->concept,
            'amount' => $this->money($movement->amount),
            'payment_method' => $this->label($movement->payment_method),
            'status' => $this->badge($movement->status),
        ]);

        return [
            'summary' => [
                'Saldo fondo solidario' => $this->money((clone $valid)->where('type', 'ingreso')->sum('amount') - (clone $valid)->where('type', 'egreso')->sum('amount')),
                'Total ingresos' => $this->money((clone $valid)->where('type', 'ingreso')->sum('amount')),
                'Total egresos' => $this->money((clone $valid)->where('type', 'egreso')->sum('amount')),
                'Movimientos anulados' => (clone $query)->where('status', 'anulado')->count(),
            ],
            'columns' => ['code' => 'Codigo', 'date' => 'Fecha', 'type' => 'Tipo', 'member' => 'Socio', 'concept' => 'Concepto', 'amount' => 'Monto', 'payment_method' => 'Metodo pago', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function activitiesReportData(array $filters): array
    {
        $query = Activity::query();
        $this->applyDateRange($query, $filters, 'activity_date');
        $query->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['activity_id'] ?? null, fn ($query, $id) => $query->where('id', $id));
        $rows = (clone $query)->orderByDesc('activity_date')->get()->map(fn (Activity $activity) => [
            'code' => $activity->code,
            'activity' => $activity->name,
            'date' => $this->date($activity->activity_date),
            'income' => $this->money($activity->total_income),
            'expense' => $this->money($activity->total_expense),
            'profit' => $this->money($activity->profit),
            'status' => $this->badge($activity->status),
            'detail' => '<a class="btn btn-xs btn-outline-info" target="_blank" href="' . e(route('admin.reportes.actividades.detalle', $activity)) . '"><i class="fas fa-eye"></i></a>',
        ]);
        $best = $rows->sortByDesc(fn ($row) => $this->numeric($row['profit']))->first();

        return [
            'summary' => [
                'Ingresos de actividades' => $this->moneyFromRows($rows, 'income'),
                'Egresos de actividades' => $this->moneyFromRows($rows, 'expense'),
                'Utilidad total' => $this->moneyFromRows($rows, 'profit'),
                'Mayor utilidad' => $best['activity'] ?? '-',
            ],
            'columns' => ['code' => 'Codigo', 'activity' => 'Actividad', 'date' => 'Fecha', 'income' => 'Ingresos', 'expense' => 'Egresos', 'profit' => 'Utilidad', 'status' => 'Estado', 'detail' => 'Detalle'],
            'rows' => $rows,
        ];
    }

    private function profitsByMemberReport(array $filters): array
    {
        $query = ProfitDistributionDetail::query()->with(['member:id,dni,full_name', 'distribution']);
        $query->whereHas('distribution', function ($distribution) use ($filters) {
            $distribution->when($filters['year'] ?? null, fn ($query, $year) => $query->where('period_year', $year))
                ->when($filters['month'] ?? null, fn ($query, $month) => $query->where('period_month', $month));
        })->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));
        $rows = (clone $query)->get()->map(fn (ProfitDistributionDetail $detail) => [
            'period' => $detail->distribution ? (($detail->distribution->period_month ? str_pad((string) $detail->distribution->period_month, 2, '0', STR_PAD_LEFT) . '/' : '') . $detail->distribution->period_year) : '-',
            'member' => $detail->member?->full_name,
            'dni' => $detail->member?->dni,
            'shares_quantity' => $this->quantity($detail->shares_quantity),
            'participation' => number_format((float) $detail->participation_percentage, 4) . '%',
            'profit_amount' => $this->money($detail->profit_amount),
            'paid_amount' => $this->money($detail->paid_amount),
            'pending_amount' => $this->money(max(0, (float) $detail->profit_amount - (float) $detail->paid_amount)),
            'status' => $this->badge($detail->status),
        ]);
        $best = $rows->sortByDesc(fn ($row) => $this->numeric($row['profit_amount']))->first();

        return [
            'summary' => [
                'Utilidad distribuida' => $this->moneyFromRows($rows, 'profit_amount'),
                'Total pagado' => $this->moneyFromRows($rows, 'paid_amount'),
                'Total pendiente' => $this->moneyFromRows($rows, 'pending_amount'),
                'Socio con mayor utilidad' => $best['member'] ?? '-',
            ],
            'columns' => ['period' => 'Periodo', 'member' => 'Socio', 'dni' => 'DNI', 'shares_quantity' => 'Acciones', 'participation' => 'Participacion', 'profit_amount' => 'Utilidad asignada', 'paid_amount' => 'Pagado', 'pending_amount' => 'Pendiente', 'status' => 'Estado'],
            'rows' => $rows,
        ];
    }

    private function creditHistoryReport(array $filters): array
    {
        $query = CreditHistory::query()->with('member');
        $this->applyDateRange($query, $filters, 'calculated_at');
        $query->when($filters['member_id'] ?? null, fn ($q, $id) => $q->where('member_id', $id))
            ->when($filters['credit_status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['member_type'] ?? null, fn ($q, $type) => $q->whereHas('member', fn ($member) => $member->where('member_type', $type)))
            ->when(($filters['has_late'] ?? null) === '1', fn ($q) => $q->where('late_payments', '>', 0))
            ->when(($filters['has_late'] ?? null) === '0', fn ($q) => $q->where('late_payments', 0))
            ->when(($filters['has_overdue'] ?? null) === '1', fn ($q) => $q->where('active_overdue_amount', '>', 0))
            ->when(($filters['has_overdue'] ?? null) === '0', fn ($q) => $q->where('active_overdue_amount', '<=', 0));
        $rows = $query->orderBy('score')->get()->map(fn (CreditHistory $history, int $index) => [
            'index' => $index + 1,
            'code' => $history->member?->code,
            'dni' => $history->member?->dni,
            'member' => $history->member?->full_name,
            'member_type' => $this->label($history->member?->member_type),
            'score' => '<strong>' . $history->score . '/100</strong>',
            'credit_status' => $this->badge($history->status),
            'loans' => $history->total_loans . ' / ' . $history->paid_loans,
            'late' => $history->mild_late_payments . ' / ' . $history->serious_late_payments,
            'max_days' => $history->max_days_late . ' días',
            'overdue' => $this->money($history->active_overdue_amount) . ' (' . $history->active_overdue_installments . ')',
            'recommendation' => e($history->recommendation ?: 'No aplica'),
            'calculated_at' => $this->date($history->calculated_at),
        ]);

        return [
            'summary' => ['Socios evaluados' => $rows->count(), 'Puntaje promedio' => number_format((float) $query->avg('score'), 1), 'Con atraso activo' => (clone $query)->where('active_overdue_amount', '>', 0)->count(), 'En riesgo o malo' => (clone $query)->whereIn('status', ['riesgo', 'malo'])->count()],
            'columns' => ['index' => '#', 'code' => 'Código', 'dni' => 'DNI', 'member' => 'Socio', 'member_type' => 'Tipo', 'score' => 'Puntaje', 'credit_status' => 'Calificación', 'loans' => 'Préstamos / pagados', 'late' => 'Leves / graves', 'max_days' => 'Máx. atraso', 'overdue' => 'Vencido activo', 'recommendation' => 'Recomendación', 'calculated_at' => 'Calculado'],
            'rows' => $rows,
        ];
    }

    private function shareQuery(array $filters, bool $onlyRegistered = true): Builder
    {
        $query = MemberShare::query();
        if ($onlyRegistered) {
            $query->where('status', 'registrado');
        }

        $this->applyDateRange($query, $filters, 'date');

        return $query
            ->when($filters['member_id'] ?? null, fn ($query, $id) => $query->where('member_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereHas('member', fn ($member) => $member->where('status', $status)));
    }

    private function paymentSummary(Builder $query, string $totalLabel): array
    {
        $valid = (clone $query)->where('status', 'registrado');

        return [
            $totalLabel => $this->money((clone $valid)->sum('amount')),
            'Efectivo' => $this->money((clone $valid)->where('payment_method', 'efectivo')->sum('amount')),
            'Yape' => $this->money((clone $valid)->where('payment_method', 'yape')->sum('amount')),
            'Plin' => $this->money((clone $valid)->where('payment_method', 'plin')->sum('amount')),
            'Transferencia' => $this->money((clone $valid)->where('payment_method', 'transferencia')->sum('amount')),
        ];
    }

    private function validatedFilters(Request $request, array $definition, bool $forExport): array
    {
        $rules = [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date' => ['nullable', 'date'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['nullable', Rule::in(self::PAYMENT_METHODS)],
            'civil_status' => ['nullable', Rule::in(['soltero', 'casado', 'conviviente', 'divorciado', 'viudo'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'search' => ['nullable', 'string', 'max:120'],
            'credit_status' => ['nullable', Rule::in(['excelente', 'bueno', 'regular', 'riesgo', 'malo'])],
            'member_type' => ['nullable', Rule::in(['nuevo', 'antiguo'])],
            'has_late' => ['nullable', Rule::in(['0', '1'])],
            'has_overdue' => ['nullable', Rule::in(['0', '1'])],
            'cash_include_historical' => ['nullable', Rule::in(['0', '1'])],
            'date_basis' => ['nullable', Rule::in(['payment_date', 'registered_at'])],
            'include_historical' => ['nullable', Rule::in(['0', '1', 'only'])],
            'affects_cash' => ['nullable', Rule::in(['0', '1', 'all'])],
        ];

        if (($definition['requires_member'] ?? false) && ! $forExport) {
            $rules['member_id'][0] = 'required';
        }

        try {
            return $request->validate($rules, [
                'date_to.after_or_equal' => 'La fecha desde no puede ser mayor a la fecha hasta.',
                'member_id.required' => 'Seleccione un socio valido.',
                'member_id.exists' => 'Seleccione un socio valido.',
                'activity_id.exists' => 'Seleccione una actividad valida.',
                'status.in' => 'Seleccione un estado valido.',
                'year.integer' => 'Seleccione un anio valido.',
                'month.integer' => 'Seleccione un mes valido.',
                'month.min' => 'Seleccione un mes valido.',
                'month.max' => 'Seleccione un mes valido.',
                'payment_method.in' => 'Seleccione un metodo de pago valido.',
                'civil_status.in' => 'Seleccione un estado civil valido.',
            ]);
        } catch (ValidationException $exception) {
            if ($forExport) {
                throw $exception;
            }

            return $request->only(array_keys($rules));
        }
    }

    private function definition(string $type): array
    {
        $definitions = [
            'socios-vigentes' => ['title' => 'Socios vigentes', 'description' => 'Socios con estado vigente y sus aportes acumulados.', 'icon' => 'fas fa-users', 'permission' => 'reportes.socios_vigentes', 'filters' => ['date_range', 'civil_status', 'search']],
            'socios-retirados' => ['title' => 'Socios retirados', 'description' => 'Socios retirados o no vigentes con informacion de cierre.', 'icon' => 'fas fa-user-slash', 'permission' => 'reportes.socios_retirados', 'filters' => ['date_range', 'search']],
            'acciones-por-socio' => ['title' => 'Acciones por socio', 'description' => 'Aportes y acciones acumuladas por socio.', 'icon' => 'fas fa-coins', 'permission' => 'reportes.acciones_socio', 'filters' => ['date_range', 'member', 'member_status']],
            'acciones-mensual' => ['title' => 'Acciones mensual', 'description' => 'Aportes registrados por mes.', 'icon' => 'fas fa-calendar-alt', 'permission' => 'reportes.acciones_mensual', 'filters' => ['year', 'month', 'member']],
            'acciones-anual' => ['title' => 'Acciones anual', 'description' => 'Aportes agrupados por mes en el anio seleccionado.', 'icon' => 'fas fa-chart-line', 'permission' => 'reportes.acciones_anual', 'filters' => ['year', 'member']],
            'socio-mayoritario' => ['title' => 'Socio mayoritario', 'description' => 'Ranking por cantidad de acciones y participacion.', 'icon' => 'fas fa-trophy', 'permission' => 'reportes.socio_mayoritario', 'filters' => ['date_range']],
            'acciones-general' => ['title' => 'Reporte general de acciones', 'description' => 'Detalle general de aportes y acciones.', 'icon' => 'fas fa-list', 'permission' => 'reportes.acciones_general', 'filters' => ['date_range', 'member']],
            'prestamos-activos' => ['title' => 'Prestamos activos', 'description' => 'Prestamos desembolsados con saldo pendiente.', 'icon' => 'fas fa-hand-holding-usd', 'permission' => 'reportes.prestamos_activos', 'filters' => ['member']],
            'prestamos-pagados' => ['title' => 'Prestamos pagados', 'description' => 'Prestamos cerrados como pagados.', 'icon' => 'fas fa-check-circle', 'permission' => 'reportes.prestamos_pagados', 'filters' => ['member']],
            'prestamos-vencidos' => ['title' => 'Prestamos vencidos', 'description' => 'Prestamos con cuotas vencidas y saldo pendiente.', 'icon' => 'fas fa-exclamation-triangle', 'permission' => 'reportes.prestamos_vencidos', 'filters' => ['member']],
            'historial-socio' => ['title' => 'Historial por socio', 'description' => 'Vista integral del historial financiero del socio.', 'icon' => 'fas fa-address-card', 'permission' => 'reportes.historial_socio', 'filters' => ['member_required', 'date_range'], 'requires_member' => true],
            'historial-crediticio' => ['title' => 'Historial crediticio', 'description' => 'Puntaje, puntualidad, atrasos y deuda vencida por socio.', 'icon' => 'fas fa-chart-line', 'permission' => 'credit-history.report', 'filters' => ['date_range', 'member', 'credit_status', 'member_type_credit', 'has_late', 'has_overdue']],
            'cobros-diarios' => ['title' => 'Cobros diarios', 'description' => 'Cobros normales e históricos por fecha real o fecha de registro.', 'icon' => 'fas fa-calendar-day', 'permission' => 'reportes.cobros_diarios', 'filters' => ['date', 'date_basis', 'include_historical', 'affects_cash', 'payment_method', 'member']],
            'cobros-mensuales' => ['title' => 'Cobros mensuales', 'description' => 'Cobros agrupados por dia en el mes seleccionado.', 'icon' => 'fas fa-calendar', 'permission' => 'reportes.cobros_mensuales', 'filters' => ['year', 'month', 'payment_method', 'member']],
            'caja-general' => ['title' => 'Caja general', 'description' => 'Movimientos de caja e impacto en saldos.', 'icon' => 'fas fa-cash-register', 'permission' => 'reportes.caja_general', 'filters' => ['date_range', 'type', 'category', 'payment_method', 'status', 'cash_include_historical']],
            'solidaridad' => ['title' => 'Solidaridad', 'description' => 'Ingresos y egresos del fondo solidario.', 'icon' => 'fas fa-hands-helping', 'permission' => 'reportes.solidaridad', 'filters' => ['date_range', 'type', 'member', 'status']],
            'actividades' => ['title' => 'Actividades', 'description' => 'Ingresos, egresos y utilidad de actividades.', 'icon' => 'fas fa-calendar-check', 'permission' => 'reportes.actividades', 'filters' => ['date_range', 'activity', 'status']],
            'utilidades-socio' => ['title' => 'Utilidades por socio', 'description' => 'Utilidades asignadas, pagadas y pendientes.', 'icon' => 'fas fa-chart-pie', 'permission' => 'reportes.utilidades_socio', 'filters' => ['year', 'month', 'member', 'status']],
        ];

        return $definitions[$type] ?? abort(404);
    }

    private function dashboardCards(): array
    {
        return [
            ['title' => 'Reportes de socios', 'description' => 'Socios vigentes y retirados.', 'icon' => 'fas fa-users', 'route' => route('admin.reportes.socios-vigentes'), 'permission' => 'reportes.socios_vigentes'],
            ['title' => 'Reportes de acciones', 'description' => 'Aportes, acciones y ranking.', 'icon' => 'fas fa-coins', 'route' => route('admin.reportes.acciones-general'), 'permission' => 'reportes.acciones_general'],
            ['title' => 'Reportes de prestamos', 'description' => 'Activos, pagados y vencidos.', 'icon' => 'fas fa-hand-holding-usd', 'route' => route('admin.reportes.prestamos-activos'), 'permission' => 'reportes.prestamos_activos'],
            ['title' => 'Reportes de cobros', 'description' => 'Cobros diarios y mensuales.', 'icon' => 'fas fa-receipt', 'route' => route('admin.reportes.cobros-diarios'), 'permission' => 'reportes.cobros_diarios'],
            ['title' => 'Reporte de caja', 'description' => 'Movimientos y saldos.', 'icon' => 'fas fa-cash-register', 'route' => route('admin.reportes.caja-general'), 'permission' => 'reportes.caja_general'],
            ['title' => 'Reporte de solidaridad', 'description' => 'Fondo solidario.', 'icon' => 'fas fa-hands-helping', 'route' => route('admin.reportes.solidaridad'), 'permission' => 'reportes.solidaridad'],
            ['title' => 'Reporte de actividades', 'description' => 'Utilidades por actividad.', 'icon' => 'fas fa-calendar-check', 'route' => route('admin.reportes.actividades'), 'permission' => 'reportes.actividades'],
            ['title' => 'Reporte de utilidades', 'description' => 'Utilidades por socio.', 'icon' => 'fas fa-chart-pie', 'route' => route('admin.reportes.utilidades-socio'), 'permission' => 'reportes.utilidades_socio'],
            ['title' => 'Historial por socio', 'description' => 'Aportes, prestamos, cobros y utilidades.', 'icon' => 'fas fa-address-card', 'route' => route('admin.reportes.historial-socio'), 'permission' => 'reportes.historial_socio'],
            ['title' => 'Historial crediticio', 'description' => 'Puntaje, atrasos y recomendaciones.', 'icon' => 'fas fa-chart-line', 'route' => route('admin.reportes.historial-crediticio'), 'permission' => 'credit-history.report'],
        ];
    }

    private function reportPermissions(): array
    {
        return [
            'activeMembers' => 'reportes.socios_vigentes',
            'retiredMembers' => 'reportes.socios_retirados',
            'sharesByMember' => 'reportes.acciones_socio',
            'sharesMonthly' => 'reportes.acciones_mensual',
            'sharesAnnual' => 'reportes.acciones_anual',
            'majorityMember' => 'reportes.socio_mayoritario',
            'sharesGeneral' => 'reportes.acciones_general',
            'activeLoans' => 'reportes.prestamos_activos',
            'paidLoans' => 'reportes.prestamos_pagados',
            'overdueLoans' => 'reportes.prestamos_vencidos',
            'memberHistory' => 'reportes.historial_socio',
            'creditHistory' => 'credit-history.report',
            'dailyPayments' => 'reportes.cobros_diarios',
            'monthlyPayments' => 'reportes.cobros_mensuales',
            'cashGeneral' => 'reportes.caja_general',
            'solidarityReport' => 'reportes.solidaridad',
            'activitiesReport' => 'reportes.actividades',
            'activityDetail' => 'reportes.actividades',
            'profitsByMember' => 'reportes.utilidades_socio',
        ];
    }

    private function applyDateRange(Builder|Relation $query, array $filters, string $column): Builder|Relation
    {
        return $query
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate($column, '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate($column, '<=', $date));
    }

    private function memberSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($query) use ($search) {
            $query->where('full_name', 'like', "%{$search}%")
                ->orWhere('dni', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        });
    }

    private function overdueInstallments(Collection $installments): Collection
    {
        return $installments
            ->where('due_date', '<', today())
            ->whereNotIn('status', ['pagado', 'anulado', 'refinanciado'])
            ->filter(fn ($installment) => (float) $installment->remaining_amount > 0);
    }

    private function maxShareMember(int $year, ?int $month = null): string
    {
        $query = MemberShare::query()->where('status', 'registrado')->whereYear('date', $year);
        if ($month) {
            $query->whereMonth('date', $month);
        }

        $row = $query->selectRaw('member_id, SUM(amount) as total_amount')->groupBy('member_id')->orderByDesc('total_amount')->with('member:id,full_name')->first();

        return $row?->member?->full_name ?? '-';
    }

    private function filterLabels(array $filters): array
    {
        return collect($filters)->filter(fn ($value) => filled($value))->mapWithKeys(function ($value, $key) {
            $label = match ($key) {
                'date_from' => 'Fecha desde',
                'date_to' => 'Fecha hasta',
                'date' => 'Fecha',
                'member_id' => 'Socio',
                'activity_id' => 'Actividad',
                'payment_method' => 'Metodo pago',
                'civil_status' => 'Estado civil',
                'year' => 'Anio',
                'month' => 'Mes',
                default => ucfirst(str_replace('_', ' ', $key)),
            };

            if ($key === 'member_id') {
                $value = Member::find($value)?->full_name ?? $value;
            }

            if ($key === 'activity_id') {
                $value = Activity::find($value)?->name ?? $value;
            }

            if ($key === 'month') {
                $value = $this->months()[(int) $value] ?? $value;
            }

            return [$label => $this->label((string) $value)];
        })->all();
    }

    private function months(): array
    {
        return [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    }

    private function paymentMethods(): array
    {
        return ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin', 'transferencia' => 'Transferencia', 'otro' => 'Otro'];
    }

    private function date(mixed $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
    }

    private function money(mixed $amount): string
    {
        return 'S/ ' . number_format((float) $amount, 2);
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) $value, 4);
    }

    private function badge(?string $value): string
    {
        $class = match ($value) {
            'vigente', 'registrado', 'pagado', 'cerrada', 'cerrado', 'ingreso', 'excelente' => 'success',
            'desembolsado', 'aprobado', 'refinanciado', 'bueno' => 'info',
            'anulado', 'retirado', 'egreso', 'vencido', 'malo' => 'danger',
            'pendiente', 'calculado', 'regular', 'riesgo' => 'warning',
            default => 'secondary',
        };

        return '<span class="badge badge-' . $class . '">' . e($this->label($value ?: '-')) . '</span>';
    }

    private function paymentFlags(LoanPayment $payment): string
    {
        $badges = [];
        if ($payment->is_historical) {
            $badges[] = '<span class="badge badge-info">Histórico</span>';
        }
        if (! $payment->affects_cash) {
            $badges[] = '<span class="badge badge-secondary">No afecta caja</span>';
        }
        if ($payment->affects_profit) {
            $badges[] = '<span class="badge badge-success">Afecta utilidades</span>';
        }
        if ($payment->affects_credit_history) {
            $badges[] = '<span class="badge badge-primary">Afecta historial</span>';
        }
        if ($payment->profit_treatment === 'historical_closed') {
            $badges[] = '<span class="badge badge-warning">Período cerrado históricamente</span>';
        } elseif ($payment->profit_treatment === 'externally_distributed') {
            $badges[] = '<span class="badge badge-warning">Distribuido fuera del sistema</span>';
        }

        return implode(' ', $badges) ?: '<span class="badge badge-light">Normal</span>';
    }

    private function label(?string $value): string
    {
        return ucfirst(str_replace('_', ' ', (string) $value));
    }

    private function moneyFromRows(Collection $rows, string $key): string
    {
        return $this->money($rows->sum(fn ($row) => $this->numeric($row[$key] ?? 0)));
    }

    private function quantityFromRows(Collection $rows, string $key): string
    {
        return $this->quantity($rows->sum(fn ($row) => $this->numeric($row[$key] ?? 0)));
    }

    private function numeric(mixed $value): float
    {
        return (float) preg_replace('/[^0-9\.\-]/', '', (string) $value);
    }

    private function plainValue(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
