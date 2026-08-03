@extends('layouts.app')

@section('subtitle', 'Caja')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-cash-register"></i> Caja

                    @can('admin.caja.create')
                        <button class="btn btn-app bg-dark" type="button" id="btnNewCashMovement">
                            <i class="fas fa-plus-circle"></i> Nuevo movimiento
                        </button>
                    @endcan
                </h1>
            </div>

            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            <a href="{{ route('home') }}">
                                <i class="fa fa-fw fa-house-user"></i> Home
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <i class="fas fa-cash-register"></i> Caja
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Saldo actual</span><strong id="cashSummaryBalance">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Total ingresos</span><strong id="cashSummaryIncome">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Total egresos</span><strong id="cashSummaryExpense">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Movimientos del mes</span><strong id="cashSummaryMonth">0</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Desde</label>
                    <input type="date" id="cash_filter_date_from" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Hasta</label>
                    <input type="date" id="cash_filter_date_to" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Tipo</label>
                    <select id="cash_filter_type" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="egreso">Egreso</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label class="small font-weight-bold text-secondary">Categoria</label>
                    <select id="cash_filter_category" class="form-control form-control-sm">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Estado</label>
                    <select id="cash_filter_status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="registrado">Registrado</option>
                        <option value="anulado">Anulado</option>
                    </select>
                </div>
                <div class="form-group col-md-1">
                    <button type="button" class="btn btn-light border btn-block" id="btnClearCashFilters" title="Limpiar filtros">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableCashMovement" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Codigo</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Concepto</th>
                            <th>Metodo pago</th>
                            <th>Monto</th>
                            <th>Saldo posterior</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.cash-movements.partials.modal')
    @include('admin.cash-movements.partials.detail-modal')
@stop

@push('js')
    <script>
        window.cashMovementRoutes = {
            list: "{{ route('admin.caja.list') }}",
            store: "{{ route('admin.caja.store') }}",
            base: "{{ url('admin/caja') }}",
            nextCode: "{{ route('admin.caja.next-code') }}",
            summary: "{{ route('admin.caja.summary') }}",
            nextCodeValue: "{{ $nextCode }}",
            incomeCategories: @json($incomeCategories),
            expenseCategories: @json($expenseCategories)
        };
    </script>

    @vite(['resources/js/pages/cash-movement.js'])
@endpush
