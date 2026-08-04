@extends('layouts.app')

@section('subtitle', 'Dashboard')
@section('plugins.Chartjs', true)

@section('header')
<div class="container-fluid dashboard-header">
    <div class="row mb-2">
        <div class="col-sm-7">
            <h1><i class="fas fa-chart-line"></i><span>Dashboard<small>Resumen general de caja, créditos, socios y utilidades.</small></span></h1>
        </div>
        <div class="col-sm-5 dashboard-header-right">
            <div class="dashboard-date"><i class="far fa-calendar-alt"></i><span>{{ now()->locale('es')->translatedFormat('d \d\e F, Y') }}<small>{{ $period['label'] }} · {{ $period['start']->format('d/m') }}—{{ $period['end']->format('d/m/Y') }}</small></span></div>
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li><li class="breadcrumb-item active">Dashboard</li></ol>
        </div>
    </div>
</div>
@stop

@section('content_body')
<div class="dashboard-page">
    <section class="dashboard-toolbar">
        <div class="toolbar-copy"><span class="section-kicker">Vista general</span><strong>Indicadores principales</strong></div>
        <form class="period-filter" method="GET" action="{{ route('home') }}">
            <label for="dashboardPeriod"><i class="far fa-calendar"></i> Periodo</label>
            <select class="form-control form-control-sm" name="period" id="dashboardPeriod">
                @foreach(['today'=>'Hoy','month'=>'Este mes','year'=>'Este año','fiscal'=>'Periodo de utilidad','custom'=>'Rango personalizado'] as $key=>$label)<option value="{{ $key }}" @selected($period['key']===$key)>{{ $label }}</option>@endforeach
            </select>
            <div class="custom-dates {{ $period['key']==='custom' ? 'is-visible' : '' }}" id="customDates"><input class="form-control form-control-sm" type="date" name="from" value="{{ request('from',$period['start']->toDateString()) }}"><input class="form-control form-control-sm" type="date" name="to" value="{{ request('to',$period['end']->toDateString()) }}"></div>
            <button class="btn btn-sm btn-sys-primary" aria-label="Aplicar periodo"><i class="fas fa-check"></i><span>Aplicar</span></button>
        </form>
    </section>

    <div class="metric-grid">
        @foreach($cards as $card) @can($card['permission'])
        <article class="metric-card tone-{{ $card['tone'] }}"><div class="metric-icon"><i class="{{ $card['icon'] }}"></i></div><div class="metric-content"><span>{{ $card['title'] }}</span><strong>{{ !empty($card['money']) ? 'S/ '.number_format($card['value'],2) : number_format($card['value']) }}</strong><small>{{ $card['detail'] }}</small></div></article>
        @endcan @endforeach
    </div>

    <div class="dashboard-grid dashboard-grid-main">
        <section class="dashboard-card alerts-card">
            <div class="card-heading"><div><span class="section-kicker">Seguimiento</span><h2>Alertas y pendientes</h2></div><span class="heading-icon"><i class="far fa-bell"></i></span></div>
            <div class="alerts-list">@forelse($alerts as $alert)<div class="alert-row"><span class="alert-icon"><i class="{{ $alert['icon'] }}"></i></span><div class="alert-copy"><strong>{{ $alert['label'] }}</strong><small>Requiere seguimiento</small></div><b>{{ $alert['count'] }}</b><span class="priority priority-{{ $alert['priority'] }}">{{ $alert['priority'] }}</span><a href="{{ route($alert['route']) }}" class="mini-link">Ver <i class="fas fa-arrow-right"></i></a></div>@empty<div class="empty-state compact"><span class="empty-icon success"><i class="fas fa-check"></i></span><div><strong>No hay alertas pendientes</strong><small>Todo se encuentra al día por el momento.</small></div></div>@endforelse</div>
        </section>
        <section class="dashboard-card quick-card">
            <div class="card-heading"><div><span class="section-kicker">Operaciones</span><h2>Accesos rápidos</h2></div><span class="heading-icon"><i class="fas fa-bolt"></i></span></div>
            <div class="quick-grid">@foreach($quickActions as $action) @can($action['permission'])<a href="{{ route($action['route']) }}"><span><i class="{{ $action['icon'] }}"></i></span><b>{{ $action['label'] }}</b><i class="fas fa-chevron-right"></i></a>@endcan @endforeach</div>
        </section>
    </div>

    <section class="dashboard-section">
        <div class="section-heading"><div><span class="section-kicker">Tendencias</span><h2>Resumen financiero</h2><p>Comportamiento de los últimos seis meses.</p></div><span class="section-period"><i class="far fa-calendar-alt"></i> 6 meses</span></div>
        <div class="chart-grid">
            @foreach([['cashChart','Flujo de caja','Ingresos vs egresos'],['loansChart','Cartera','Préstamos por estado'],['profitChart','Rentabilidad','Utilidades generadas'],['sharesChart','Aportes','Acciones registradas']] as [$id,$eyebrow,$title])
            <article class="dashboard-card chart-card"><div class="chart-heading"><div><span>{{ $eyebrow }}</span><h3>{{ $title }}</h3></div><i class="fas fa-ellipsis-h"></i></div><div class="chart-wrap"><canvas id="{{ $id }}"></canvas><div class="chart-empty"><span class="empty-icon"><i class="far fa-chart-bar"></i></span><strong>Sin datos suficientes</strong><small>Los datos aparecerán cuando existan registros para graficar.</small></div></div></article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-section recent-section">
        <div class="section-heading"><div><span class="section-kicker">Operación diaria</span><h2>Actividad reciente</h2><p>Últimos movimientos registrados en el sistema.</p></div></div>
        <div class="tables-grid">
            @can('admin.caja.index') @include('partials.dashboard-table',['title'=>'Últimos movimientos de caja','subtitle'=>'Ingresos y egresos recientes','icon'=>'fas fa-cash-register','route'=>route('admin.caja.index'),'button'=>'Ver caja','headers'=>['Fecha','Tipo','Categoría','Concepto','Monto','Estado'],'rows'=>$cashMovements,'kind'=>'cash']) @endcan
            @can('admin.prestamos.index') @include('partials.dashboard-table',['title'=>'Próximas cuotas por vencer','subtitle'=>'Compromisos de pago cercanos','icon'=>'fas fa-calendar-day','route'=>route('admin.cuotas.index'),'button'=>'Ver cronograma','headers'=>['Socio','Préstamo','Cuota','Vencimiento','Monto','Estado'],'rows'=>$upcomingInstallments,'kind'=>'installment']) @endcan
            @can('admin.cobros.index') @include('partials.dashboard-table',['title'=>'Últimos cobros','subtitle'=>'Pagos recibidos recientemente','icon'=>'fas fa-receipt','route'=>route('admin.cobros.index'),'button'=>'Ver cobros','headers'=>['Fecha','Socio','Préstamo','Monto','Método','Estado'],'rows'=>$latestPayments,'kind'=>'payment']) @endcan
        </div>
    </section>
</div>
@stop

@push('css')<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">@endpush
@push('js')<script>window.dashboardCharts=@json($charts);</script><script src="{{ asset('js/dashboard.js') }}"></script>@endpush
