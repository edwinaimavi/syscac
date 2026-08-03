@extends('layouts.app')

@section('subtitle', 'Préstamos')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-hand-holding-usd"></i> Prestamos
                    @can('admin.prestamos.create')
                        <button class="btn btn-app bg-dark" type="button" id="btnNewLoan">
                            <i class="fas fa-plus-circle"></i> Nuevo prestamo
                        </button>
                    @endcan
                </h1>
            </div>
            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-hand-holding-usd"></i> Prestamos</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Total aprobado historico</span><strong id="loanSummaryApproved">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Pendientes</span><strong id="loanSummaryPending">0</strong></div>
        <div class="cash-summary-card"><span>Desembolsados activos</span><strong id="loanSummaryDisbursed">0</strong></div>
        <div class="cash-summary-card"><span>Saldo por cobrar</span><strong id="loanSummaryReceivable">S/ 0.00</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="loan_filter_date_from" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="loan_filter_date_to" class="form-control form-control-sm"></div>
                <div class="form-group col-md-4">
                    <label class="small font-weight-bold text-secondary">Socio</label>
                    <select id="loan_filter_member_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Estado</label>
                    <select id="loan_filter_status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobado">Aprobado</option>
                        <option value="desembolsado">Desembolsado</option>
                        <option value="pagado">Pagado</option>
                        <option value="refinanciado">Refinanciado</option>
                        <option value="anulado">Anulado</option>
                    </select>
                </div>
                <div class="form-group col-md-2"><button type="button" class="btn btn-light border btn-block" id="btnClearLoanFilters"><i class="fas fa-undo"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableLoan" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th><th>Codigo</th><th>Fecha</th><th>Socio</th><th>DNI</th><th>Monto aprobado</th><th>Tasa</th><th>Plazo</th><th>Saldo</th><th>Estado</th><th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.loans.partials.modal')
    @include('admin.loans.partials.detail-modal')
    @include('admin.loans.partials.schedule-modal')
    @include('admin.loans.partials.disburse-modal')
@stop

@push('js')
    <script>
        window.loanRoutes = {
            list: "{{ route('admin.prestamos.list') }}",
            store: "{{ route('admin.prestamos.store') }}",
            base: "{{ url('admin/prestamos') }}",
            nextCode: "{{ route('admin.prestamos.next-code') }}",
            summary: "{{ route('admin.prestamos.summary') }}",
            calculate: "{{ route('admin.prestamos.calculate') }}",
            cashBalance: "{{ route('admin.prestamos.cash-balance') }}",
            pendingSimulationsBase: "{{ url('admin/prestamos/simulaciones-pendientes') }}",
            simulationBase: "{{ url('admin/prestamos/simulador') }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>
    @vite(['resources/js/pages/loan.js'])
@endpush
