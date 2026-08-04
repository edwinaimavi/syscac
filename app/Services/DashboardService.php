<?php

namespace App\Services;

use App\Models\AdministrativeFundMovement;
use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanSimulation;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberShare;
use App\Models\ProfitDistribution;
use App\Models\SolidarityMovement;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardService
{
    public function resolvePeriod(Request $request): array
    {
        $key = $request->string('period')->toString() ?: 'month';
        $today = today();
        [$start, $end, $label] = match ($key) {
            'today' => [$today->copy(), $today->copy(), 'Hoy'],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear(), 'Este año'],
            'fiscal' => $this->fiscalPeriod($today),
            'custom' => [Carbon::parse($request->date('from') ?: $today->copy()->startOfMonth()), Carbon::parse($request->date('to') ?: $today), 'Rango personalizado'],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'Este mes'],
        };
        if ($start->gt($end)) [$start, $end] = [$end, $start];
        return ['key' => $key, 'start' => $start->startOfDay(), 'end' => $end->endOfDay(), 'label' => $label];
    }

    private function fiscalPeriod(Carbon $date): array
    {
        $month = (int) config('syscac.utility_fiscal_start_month', 3);
        $start = Carbon::create($date->month >= $month ? $date->year : $date->year - 1, $month, 1);
        return [$start, $start->copy()->addYear()->subDay(), 'Periodo de utilidad'];
    }

    public function build(array $period): array
    {
        return [
            'cards' => $this->getSummaryCards($period),
            'alerts' => $this->getAlerts(),
            'cashMovements' => $this->getCashMovements(),
            'upcomingInstallments' => $this->getUpcomingInstallments(),
            'latestPayments' => $this->getLatestPayments(),
            'charts' => $this->getChartsData(),
            'quickActions' => $this->getQuickActions(),
        ];
    }

    public function getSummaryCards(array $period): array
    {
        $payments = LoanPayment::where('status', 'registrado')->whereBetween('payment_date', [$period['start'], $period['end']]);
        $cashBalance = (float) CashMovement::where('status', 'registrado')->selectRaw("COALESCE(SUM(CASE WHEN type='ingreso' THEN amount ELSE -amount END),0) total")->value('total');
        $solidarity = (float) SolidarityMovement::where('status', 'registrado')->selectRaw("COALESCE(SUM(CASE WHEN type='ingreso' THEN amount ELSE -amount END),0) total")->value('total');
        $admin = (float) AdministrativeFundMovement::where('status', 'registrado')->selectRaw("COALESCE(SUM(CASE WHEN type='ingreso' THEN amount ELSE -amount END),0) total")->value('total');

        return [
            ['title'=>'Caja actual','value'=>$cashBalance,'money'=>true,'detail'=>'Saldo disponible','icon'=>'fas fa-cash-register','tone'=>'teal','permission'=>'admin.caja.index'],
            ['title'=>'Socios vigentes','value'=>Member::where('status','vigente')->count(),'detail'=>'Miembros activos','icon'=>'fas fa-users','tone'=>'blue','permission'=>'admin.socios.index'],
            ['title'=>'Capital en acciones','value'=>(float) MemberShare::where('status','registrado')->sum('share_capital_amount'),'money'=>true,'detail'=>'Aportes válidos','icon'=>'fas fa-coins','tone'=>'gold','permission'=>'admin.acciones.index'],
            ['title'=>'Préstamos activos','value'=>Loan::whereIn('status',['aprobado','desembolsado'])->count(),'detail'=>'Aprobados y desembolsados','icon'=>'fas fa-hand-holding-usd','tone'=>'violet','permission'=>'admin.prestamos.index'],
            ['title'=>'Saldo por cobrar','value'=>(float) Loan::whereIn('status',['aprobado','desembolsado'])->sum('current_balance'),'money'=>true,'detail'=>'Capital pendiente','icon'=>'fas fa-wallet','tone'=>'purple','permission'=>'admin.prestamos.index'],
            ['title'=>'Utilidad generada','value'=>(float) (clone $payments)->where('affects_profit',true)->selectRaw('COALESCE(SUM(interest_amount + late_fee_paid),0) total')->value('total'),'money'=>true,'detail'=>'Intereses y moras del periodo','icon'=>'fas fa-chart-line','tone'=>'green','permission'=>'admin.utilidades.index'],
            ['title'=>'Fondo solidario','value'=>$solidarity,'money'=>true,'detail'=>'Saldo del fondo','icon'=>'fas fa-hands-helping','tone'=>'rose','permission'=>'admin.solidaridad.index'],
            ['title'=>'Fondo administrativo','value'=>$admin,'money'=>true,'detail'=>'Saldo del fondo','icon'=>'fas fa-building','tone'=>'slate','permission'=>'admin.fondo-administrativo.index'],
        ];
    }

    public function getAlerts(): array
    {
        $items = [
            ['label'=>'Cuotas vencidas','count'=>LoanInstallment::whereDate('due_date','<',today())->whereIn('status',['pendiente','parcial','vencido'])->count(),'icon'=>'fas fa-clock','priority'=>'alta','permission'=>'admin.prestamos.index','route'=>'admin.cuotas.index'],
            ['label'=>'Préstamos pendientes de aprobación','count'=>Loan::where('status','pendiente')->count(),'icon'=>'fas fa-file-signature','priority'=>'alta','permission'=>'admin.prestamos.index','route'=>'admin.prestamos.index'],
            ['label'=>'Simulaciones pendientes','count'=>LoanSimulation::where('status','simulada')->count(),'icon'=>'fas fa-calculator','priority'=>'media','permission'=>'admin.simulaciones.index','route'=>'admin.loan-simulations.index'],
            ['label'=>'Socios en proceso de retiro','count'=>MemberAccountClosure::whereIn('status',['calculado','pendiente_regularizacion','en_proceso'])->count(),'icon'=>'fas fa-user-minus','priority'=>'media','permission'=>'retiros.index','route'=>'admin.retiros-socios.index'],
            ['label'=>'Utilidades pendientes de aprobación o pago','count'=>ProfitDistribution::whereIn('status',['calculado','aprobado'])->count(),'icon'=>'fas fa-chart-pie','priority'=>'media','permission'=>'admin.utilidades.index','route'=>'admin.utilidades.index'],
        ];
        return collect($items)->filter(fn($item)=>$item['count'] > 0 && Gate::allows($item['permission']))->values()->all();
    }

    public function getCashMovements() { return Gate::allows('admin.caja.index') ? CashMovement::where('status','registrado')->latest('movement_date')->latest('id')->limit(5)->get() : collect(); }
    public function getUpcomingInstallments() { return Gate::allows('admin.prestamos.index') ? LoanInstallment::with('loan.member')->whereDate('due_date','>=',today())->whereIn('status',['pendiente','parcial'])->orderBy('due_date')->limit(5)->get() : collect(); }
    public function getLatestPayments() { return Gate::allows('admin.cobros.index') ? LoanPayment::with(['member','loan'])->where('status','registrado')->latest('payment_date')->latest('id')->limit(5)->get() : collect(); }

    public function getChartsData(): array
    {
        $months = collect(CarbonPeriod::create(today()->subMonths(5)->startOfMonth(), '1 month', today()->startOfMonth()));
        $labels = $months->map(fn($m)=>$m->locale('es')->translatedFormat('M y'))->values();
        $cash = fn(string $type) => $months->map(fn($m)=>(float)CashMovement::where('status','registrado')->where('type',$type)->whereYear('movement_date',$m->year)->whereMonth('movement_date',$m->month)->sum('amount'))->values();
        $profit = fn(string $column) => $months->map(fn($m)=>(float)LoanPayment::where('status','registrado')->where('affects_profit',true)->whereYear('payment_date',$m->year)->whereMonth('payment_date',$m->month)->sum($column))->values();
        return [
            'labels'=>$labels,
            'cash'=>['income'=>$cash('ingreso'),'expense'=>$cash('egreso')],
            'profit'=>['interest'=>$profit('interest_amount'),'late'=>$profit('late_fee_paid')],
            'shares'=>$months->map(fn($m)=>(float)MemberShare::where('status','registrado')->whereYear('date',$m->year)->whereMonth('date',$m->month)->sum('shares_quantity'))->values(),
            'loans'=>collect(['pendiente','aprobado','desembolsado','pagado','anulado'])->mapWithKeys(fn($s)=>[$s=>Loan::where('status',$s)->count()]),
        ];
    }

    public function getQuickActions(): array
    {
        return [
            ['label'=>'Nuevo socio','icon'=>'fas fa-user-plus','route'=>'admin.socios.index','permission'=>'admin.socios.create'],
            ['label'=>'Registrar aporte','icon'=>'fas fa-coins','route'=>'admin.acciones.index','permission'=>'admin.acciones.create'],
            ['label'=>'Nueva simulación','icon'=>'fas fa-calculator','route'=>'admin.loan-simulations.index','permission'=>'admin.simulaciones.create'],
            ['label'=>'Nuevo préstamo','icon'=>'fas fa-hand-holding-usd','route'=>'admin.prestamos.index','permission'=>'admin.prestamos.create'],
            ['label'=>'Nuevo cobro','icon'=>'fas fa-receipt','route'=>'admin.cobros.index','permission'=>'admin.cobros.create'],
            ['label'=>'Movimiento de caja','icon'=>'fas fa-cash-register','route'=>'admin.caja.index','permission'=>'admin.caja.create'],
            ['label'=>'Calcular utilidades','icon'=>'fas fa-chart-pie','route'=>'admin.utilidades.index','permission'=>'admin.utilidades.create'],
            ['label'=>'Reportes','icon'=>'fas fa-chart-bar','route'=>'admin.reportes.index','permission'=>'reportes.index'],
        ];
    }
}
