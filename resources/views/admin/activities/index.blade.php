@extends('layouts.app')

@section('subtitle', 'Actividades')

@section('header')
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-calendar-check"></i> Actividades @can('admin.actividades.create')<button class="btn btn-app bg-dark" type="button" id="btnNewActivity"><i class="fas fa-plus-circle"></i> Nueva actividad</button>@endcan</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Actividades</li></ol></div></div></div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Actividades abiertas</span><strong id="activitySummaryOpen">0</strong></div>
        <div class="cash-summary-card"><span>Actividades cerradas</span><strong id="activitySummaryClosed">0</strong></div>
        <div class="cash-summary-card"><span>Utilidad total</span><strong id="activitySummaryProfit">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Movimientos del mes</span><strong id="activitySummaryMonth">0</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="activity_filter_date_from" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="activity_filter_date_to" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Estado</label><select id="activity_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="abierta">Abierta</option><option value="cerrada">Cerrada</option><option value="anulada">Anulada</option></select></div>
        <div class="form-group col-md-5"><label class="small font-weight-bold text-secondary">Nombre</label><input type="text" id="activity_filter_name" class="form-control form-control-sm" placeholder="Buscar actividad"></div>
        <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearActivityFilters"><i class="fas fa-undo"></i></button></div>
    </div></div></div>

    <div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
        <table id="tableActivities" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
            <thead><tr><th>#</th><th>Codigo</th><th>Nombre</th><th>Fecha</th><th>Ingresos</th><th>Egresos</th><th>Utilidad</th><th>Estado</th><th></th></tr></thead>
        </table>
    </div></div></div>

    @include('admin.activities.partials.modal')
    @include('admin.activities.partials.detail-modal')
    @include('admin.activities.partials.movement-modal')
    @include('admin.activities.partials.movement-detail-modal')
@stop

@push('js')
    <script>
        window.activityRoutes = {
            list: "{{ route('admin.actividades.list') }}",
            store: "{{ route('admin.actividades.store') }}",
            base: "{{ url('admin/actividades') }}",
            nextCode: "{{ route('admin.actividades.next-code') }}",
            summary: "{{ route('admin.actividades.summary') }}",
            movementBase: "{{ url('admin/actividades/movimientos') }}",
            nextCodeValue: "{{ $nextCode }}",
            nextMovementCodeValue: "{{ $nextMovementCode }}"
        };
    </script>
    @vite(['resources/js/pages/activity.js'])
@endpush
