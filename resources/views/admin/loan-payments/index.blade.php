@extends('layouts.app')

@section('subtitle', 'Cobros')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-cash-register"></i> Cobros
                    @can('admin.cobros.create')
                        <button class="btn btn-app bg-dark" type="button" id="btnNewLoanPayment"><i class="fas fa-plus-circle"></i> Nuevo cobro</button>
                    @endcan
                </h1>
            </div>
            <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Cobros</li></ol></div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Total cobrado</span><strong id="paymentSummaryTotal">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Cobros del mes</span><strong id="paymentSummaryMonth">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Cobros de hoy</span><strong id="paymentSummaryToday">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Prestamos con saldo</span><strong id="paymentSummaryLoans">0</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="payment_filter_date_from" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="payment_filter_date_to" class="form-control form-control-sm"></div>
        <div class="form-group col-md-3"><label class="small font-weight-bold text-secondary">Socio</label><select id="payment_filter_member_id" class="form-control form-control-sm"><option value="">Todos</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Tipo</label><select id="payment_filter_type" class="form-control form-control-sm"><option value="">Todos</option><option value="cuota">Cuota</option><option value="parcial">Pago parcial</option><option value="adelanto_cuotas">Adelanto de cuotas</option><option value="abono_capital">Abono a capital</option><option value="liquidacion">Liquidacion</option></select></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Estado</label><select id="payment_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="registrado">Registrado</option><option value="anulado">Anulado</option></select></div>
        <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearPaymentFilters"><i class="fas fa-undo"></i></button></div>
    </div></div></div>

    <div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
        <table id="tableLoanPayment" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
            <thead><tr><th>#</th><th>Codigo</th><th>Fecha</th><th>Socio</th><th>DNI</th><th>Prestamo</th><th>Tipo pago</th><th>Metodo</th><th>Monto</th><th>Registro</th><th>Estado</th><th></th></tr></thead>
        </table>
    </div></div></div>

    @include('admin.loan-payments.partials.modal')
    @include('admin.loan-payments.partials.detail-modal')
@stop

@push('js')
    <script>
        window.loanPaymentRoutes = {
            list: "{{ route('admin.cobros.list') }}",
            store: "{{ route('admin.cobros.store') }}",
            base: "{{ url('admin/cobros') }}",
            nextCode: "{{ route('admin.cobros.next-code') }}",
            summary: "{{ route('admin.cobros.summary') }}",
            memberLoansBase: "{{ url('admin/cobros/socio') }}",
            loanInstallmentsBase: "{{ url('admin/cobros/prestamo') }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>
    @vite(['resources/js/pages/loan-payment.js'])
@endpush
