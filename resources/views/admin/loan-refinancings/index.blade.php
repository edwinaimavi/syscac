@extends('layouts.app')

@section('subtitle', 'Refinanciamientos')

@section('header')
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-sync-alt"></i> Refinanciamientos @can('admin.refinanciamientos.create')<button class="btn btn-app bg-dark" type="button" id="btnNewRefinancing"><i class="fas fa-plus-circle"></i> Nuevo refinanciamiento</button>@endcan</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Refinanciamientos</li></ol></div></div></div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Total refinanciado</span><strong id="refSummaryTotal">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Registrados</span><strong id="refSummaryRegistered">0</strong></div>
        <div class="cash-summary-card"><span>Prestamos refinanciados</span><strong id="refSummaryLoans">0</strong></div>
        <div class="cash-summary-card"><span>Monto del mes</span><strong id="refSummaryMonth">S/ 0.00</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="ref_filter_date_from" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="ref_filter_date_to" class="form-control form-control-sm"></div>
        <div class="form-group col-md-4"><label class="small font-weight-bold text-secondary">Socio</label><select id="ref_filter_member_id" class="form-control form-control-sm"><option value="">Todos</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Estado</label><select id="ref_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="registrado">Registrado</option><option value="anulado">Anulado</option></select></div>
        <div class="form-group col-md-1"><label class="small font-weight-bold text-secondary">Vencidas</label><select id="ref_filter_has_overdue" class="form-control form-control-sm"><option value="">Todos</option><option value="1">Si</option></select></div>
        <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearRefFilters"><i class="fas fa-undo"></i></button></div>
    </div></div></div>

    <div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
        <table id="tableRefinancing" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
            <thead><tr><th>#</th><th>Codigo</th><th>Fecha</th><th>Socio</th><th>DNI</th><th>Prestamo original</th><th>Nuevo prestamo</th><th>Saldo anterior</th><th>Cuotas vencidas</th><th>Nuevo monto</th><th>Estado</th><th></th></tr></thead>
        </table>
    </div></div></div>

    @include('admin.loan-refinancings.partials.modal')
    @include('admin.loan-refinancings.partials.detail-modal')
    @include('admin.loan-refinancings.partials.schedule-modal')
@stop

@push('js')
    <script>
        window.refinancingRoutes = {
            list: "{{ route('admin.refinanciamientos.list') }}",
            store: "{{ route('admin.refinanciamientos.store') }}",
            base: "{{ url('admin/refinanciamientos') }}",
            nextCode: "{{ route('admin.refinanciamientos.next-code') }}",
            summary: "{{ route('admin.refinanciamientos.summary') }}",
            calculate: "{{ route('admin.refinanciamientos.calculate') }}",
            memberLoansBase: "{{ url('admin/refinanciamientos/socio') }}",
            loanBalanceBase: "{{ url('admin/refinanciamientos/prestamo') }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>
    @vite(['resources/js/pages/loan-refinancing.js'])
@endpush
