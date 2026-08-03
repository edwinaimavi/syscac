@extends('layouts.app')

@section('subtitle', 'Retiro de socios')

@section('header')
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-7"><h1><i class="fas fa-user-slash"></i> Retiro y cierre de cuenta @can('retiros.create')<button class="btn btn-app bg-dark" type="button" id="btnNewClosure"><i class="fas fa-plus-circle"></i> Nuevo cierre</button>@endcan</h1></div><div class="col-sm-5"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Retiro de socios</li></ol></div></div></div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Socios retirados</span><strong id="closureSummaryRetired">0</strong></div>
        <div class="cash-summary-card"><span>Cierres registrados</span><strong id="closureSummaryClosures">0</strong></div>
        <div class="cash-summary-card"><span>Saldo devuelto</span><strong id="closureSummaryReturned">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Pendiente por cobrar</span><strong id="closureSummaryCollect">S/ 0.00</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="closure_filter_date_from" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="closure_filter_date_to" class="form-control form-control-sm"></div>
        <div class="form-group col-md-3"><label class="small font-weight-bold text-secondary">Socio</label><select id="closure_filter_member" class="form-control form-control-sm"><option value="">Todos</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->full_name }} - {{ $member->dni }}</option>@endforeach</select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Estado</label><select id="closure_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="calculado">Calculado</option><option value="pendiente_regularizacion">Pendiente de regularización</option><option value="cerrado">Confirmado</option><option value="anulado">Anulado</option></select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Resultado</label><select id="closure_filter_settlement" class="form-control form-control-sm"><option value="">Todos</option><option value="favor_socio">Favor socio</option><option value="contra_socio">Contra socio</option><option value="sin_saldo">Sin saldo</option></select></div>
        <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearClosureFilters"><i class="fas fa-undo"></i></button></div>
    </div></div></div>

    <div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
        <table id="tableClosures" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
            <thead><tr><th>#</th><th>Codigo</th><th>Fecha cierre</th><th>Socio</th><th>DNI</th><th>Total a favor</th><th>Total en contra</th><th>Saldo final</th><th>Estado</th><th></th></tr></thead>
        </table>
    </div></div></div>

    @include('admin.member-account-closures.partials.modal')
    @include('admin.member-account-closures.partials.detail-modal')
    @include('admin.member-account-closures.partials.close-modal')
@stop

@push('js')
    <script>
        window.memberClosureRoutes = {
            list: "{{ route('admin.retiros-socios.list') }}",
            store: "{{ route('admin.retiros-socios.store') }}",
            base: "{{ url('admin/retiros-socios') }}",
            nextCode: "{{ route('admin.retiros-socios.next-code') }}",
            members: "{{ route('admin.retiros-socios.members') }}",
            summary: "{{ route('admin.retiros-socios.summary') }}",
            calculate: "{{ route('admin.retiros-socios.calculate') }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>
    @vite(['resources/js/pages/member-account-closure.js'])
@endpush
