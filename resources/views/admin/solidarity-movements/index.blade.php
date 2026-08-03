@extends('layouts.app')

@section('subtitle', 'Solidaridad')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-hands-helping"></i> Solidaridad

                    @can('admin.solidaridad.create')
                        <button class="btn btn-app bg-dark" type="button" id="btnNewSolidarity">
                            <i class="fas fa-plus-circle"></i> Nuevo movimiento
                        </button>
                    @endcan
                </h1>
            </div>

            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                    <li class="breadcrumb-item active">Solidaridad</li>
                </ol>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Saldo fondo solidario</span><strong id="solidaritySummaryBalance">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Total ingresos</span><strong id="solidaritySummaryIncome">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Total egresos</span><strong id="solidaritySummaryExpense">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Movimientos del mes</span><strong id="solidaritySummaryMonth">0</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="solidarity_filter_date_from" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="solidarity_filter_date_to" class="form-control form-control-sm"></div>
                <div class="form-group col-md-1"><label class="small font-weight-bold text-secondary">Tipo</label><select id="solidarity_filter_type" class="form-control form-control-sm"><option value="">Todos</option><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold text-secondary">Socio</label><select id="solidarity_filter_member_id" class="form-control form-control-sm"><option value="">Todos</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
                <div class="form-group col-md-1"><label class="small font-weight-bold text-secondary">Estado</label><select id="solidarity_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="registrado">Registrado</option><option value="anulado">Anulado</option></select></div>
                <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Metodo pago</label><select id="solidarity_filter_payment_method" class="form-control form-control-sm"><option value="">Todos</option>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearSolidarityFilters" title="Limpiar filtros"><i class="fas fa-undo"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableSolidarity" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Codigo</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Socio</th>
                            <th>Concepto</th>
                            <th>Metodo pago</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.solidarity-movements.partials.modal')
    @include('admin.solidarity-movements.partials.detail-modal')
@stop

@push('js')
    <script>
        window.solidarityRoutes = {
            list: "{{ route('admin.solidaridad.list') }}",
            store: "{{ route('admin.solidaridad.store') }}",
            base: "{{ url('admin/solidaridad') }}",
            nextCode: "{{ route('admin.solidaridad.next-code') }}",
            summary: "{{ route('admin.solidaridad.summary') }}",
            nextCodeValue: "{{ $nextCode }}",
            paymentMethods: @json($paymentMethods)
        };
    </script>

    @vite(['resources/js/pages/solidarity-movement.js'])
@endpush
