@extends('layouts.app')

@section('subtitle', 'Simulador de préstamo')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-calculator"></i> Simulador de prestamo

                    @can('admin.simulaciones.create')
                        <button class="btn btn-app bg-dark" type="button" id="btnNewLoanSimulation">
                            <i class="fas fa-plus-circle"></i> Nueva simulacion
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
                            <i class="fas fa-calculator"></i> Simulador
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Total simulado vigente</span><strong id="loanSimulationSummaryCurrent">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Total convertido</span><strong id="loanSimulationSummaryConverted">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Simulaciones registradas</span><strong id="loanSimulationSummaryRecords">0</strong></div>
        <div class="cash-summary-card"><span>Ultima simulacion</span><strong id="loanSimulationSummaryLast">-</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Desde</label>
                    <input type="date" id="loan_sim_filter_date_from" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Hasta</label>
                    <input type="date" id="loan_sim_filter_date_to" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-4">
                    <label class="small font-weight-bold text-secondary">Socio</label>
                    <select id="loan_sim_filter_member_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Estado</label>
                    <select id="loan_sim_filter_status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="simulada">Simulada</option>
                        <option value="convertida">Convertida</option>
                        <option value="sin_efecto">Sin efecto</option>
                        <option value="anulada">Anulada</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="button" class="btn btn-light border btn-block" id="btnClearLoanSimulationFilters" title="Limpiar filtros">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableLoanSimulation" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Codigo</th>
                            <th>Fecha</th>
                            <th>Socio</th>
                            <th>DNI</th>
                            <th>Monto</th>
                            <th>Tasa</th>
                            <th>Plazo</th>
                            <th>Total interes</th>
                            <th>Total a pagar</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.loan-simulations.partials.modal')
    @include('admin.loan-simulations.partials.detail-modal')
@stop

@push('js')
    <script>
        window.loanSimulationRoutes = {
            list: "{{ route('admin.loan-simulations.list') }}",
            store: "{{ route('admin.loan-simulations.store') }}",
            base: "{{ url('admin/prestamos/simulador') }}",
            nextCode: "{{ route('admin.loan-simulations.next-code') }}",
            summary: "{{ route('admin.loan-simulations.summary') }}",
            calculate: "{{ route('admin.loan-simulations.calculate') }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>

    @vite(['resources/js/pages/loan-simulation.js'])
@endpush
