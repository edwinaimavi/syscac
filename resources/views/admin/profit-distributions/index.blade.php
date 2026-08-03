@extends('layouts.app')

@section('subtitle', 'Utilidades')

@section('header')
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-chart-pie"></i> Utilidades @can('admin.utilidades.create')<button class="btn btn-app bg-dark" type="button" id="btnNewProfit"><i class="fas fa-plus-circle"></i> Nueva distribucion</button>@endcan</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Utilidades</li></ol></div></div></div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Utilidad distribuida</span><strong id="profitSummaryDistributed">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Calculadas</span><strong id="profitSummaryCalculated">0</strong></div>
        <div class="cash-summary-card"><span>Aprobadas</span><strong id="profitSummaryApproved">0</strong></div>
        <div class="cash-summary-card"><span>Pendiente de pago</span><strong id="profitSummaryPending">S/ 0.00</strong></div>
    </div>
    <div class="alert alert-info border-0 shadow-sm"><i class="fas fa-info-circle mr-1"></i> La utilidad distribuible se calcula únicamente con intereses y moras efectivamente cobradas. Los aportes, capital recuperado, solidaridad y actividades internas no forman parte de la utilidad.</div>

    <div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Anio</label><input type="number" id="profit_filter_year" class="form-control form-control-sm" min="2000"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Mes</label><select id="profit_filter_month" class="form-control form-control-sm"><option value="">Todos</option>@foreach($months as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Estado</label><select id="profit_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="calculado">Calculado</option><option value="aprobado">Aprobado</option><option value="pagado">Pagado</option><option value="anulado">Anulado</option></select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="profit_filter_date_from" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="profit_filter_date_to" class="form-control form-control-sm"></div>
        <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearProfitFilters"><i class="fas fa-undo"></i></button></div>
    </div></div></div>

    <div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
        <table id="tableProfit" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
            <thead><tr><th>#</th><th>Codigo</th><th>Periodo</th><th>Inicio</th><th>Fin</th><th>Utilidad total</th><th>Acción-mes</th><th>Utilidad/acción-mes</th><th>Estado</th><th></th></tr></thead>
        </table>
    </div></div></div>

    @include('admin.profit-distributions.partials.modal')
    @include('admin.profit-distributions.partials.detail-modal')
    @include('admin.profit-distributions.partials.pay-modal')
    <div class="modal fade" id="profitSourcesDetailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title"><i class="fas fa-list-alt mr-2"></i>Detalle de intereses y moras</h5><small class="text-muted" id="profitSourcesDetailPeriod"></small></div><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><div class="table-responsive"><table class="table table-sm table-hover text-center"><thead><tr><th>Fecha de pago</th><th>Socio</th><th>Préstamo</th><th>Cuota</th><th>Interés cobrado</th><th>Mora cobrada</th><th>Total utilidad</th><th>Estado</th></tr></thead><tbody id="profitSourcesDetailRows"></tbody></table></div></div></div></div></div>
@stop

@push('js')
    <script>
        window.profitRoutes = {
            list: "{{ route('admin.utilidades.list') }}",
            store: "{{ route('admin.utilidades.store') }}",
            base: "{{ url('admin/utilidades') }}",
            detailBase: "{{ url('admin/utilidades/detalle') }}",
            nextCode: "{{ route('admin.utilidades.next-code') }}",
            summary: "{{ route('admin.utilidades.summary') }}",
            availability: "{{ route('admin.utilidades.availability') }}",
            paymentSources: "{{ route('admin.utilidades.sources') }}",
            sources: "{{ route('admin.utilidades.sources.index') }}",
            canAnnulSource: @json(auth()->user()?->can('admin.utilidades.anular') ?? false),
            calculate: "{{ route('admin.utilidades.calculate') }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>
    @vite(['resources/js/pages/profit-distribution.js'])
@endpush
