@extends('layouts.app')

@section('subtitle', 'Acciones / Aportes')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-coins"></i> Acciones / Aportes

                    @can('admin.acciones.create')
                        <button class="btn btn-app bg-dark" type="button" id="btnNewShare">
                            <i class="fas fa-plus-circle"></i> Nuevo aporte
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
                            <i class="fas fa-coins"></i> Acciones / Aportes
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="share-summary-grid mb-3">
        <div class="share-summary-card">
            <span>Total capital acciones</span>
            <strong id="summaryTotalAmount">S/ 0.00</strong>
        </div>
        <div class="share-summary-card"><span>Total recibido</span><strong id="summaryTotalReceived">S/ 0.00</strong></div>
        <div class="share-summary-card"><span>Total solidaridad</span><strong id="summaryTotalSolidarity">S/ 0.00</strong></div>
        <div class="share-summary-card"><span>Total gastos administrativos</span><strong id="summaryTotalAdministrative">S/ 0.00</strong></div>
        <div class="share-summary-card">
            <span>Total acciones</span>
            <strong id="summaryTotalShares">0</strong>
        </div>
        <div class="share-summary-card">
            <span>Aportes registrados</span>
            <strong id="summaryTotalRecords">0</strong>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3">
        <div class="card-body p-3">
            <div class="form-row align-items-end">
                <div class="form-group col-md-5">
                    <label class="small font-weight-bold text-secondary">Filtrar por socio</label>
                    <select id="filter_member_id" class="form-control form-control-sm">
                        <option value="">Todos los socios</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}">
                                {{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Estado</label>
                    <select id="filter_status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="registrado">Registrado</option>
                        <option value="anulado">Anulado</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Desde</label>
                    <input type="date" id="filter_date_from" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2">
                    <label class="small font-weight-bold text-secondary">Hasta</label>
                    <input type="date" id="filter_date_to" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-1">
                    <button type="button" class="btn btn-light border btn-block" id="btnClearShareFilters" title="Limpiar filtros">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableMemberShare" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Codigo</th>
                            <th>Fecha</th>
                            <th>Socio</th>
                            <th>DNI</th>
                            <th>Total pagado</th><th>Capital acciones</th><th>Solidaridad</th><th>Gasto admin.</th>
                            <th>Valor accion</th>
                            <th>Cantidad acciones</th>
                            <th>Metodo pago</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.member-shares.partials.modal')
    @include('admin.member-shares.partials.detail-modal')
@stop

@push('js')
    <script>
        window.memberShareRoutes = {
            list: "{{ route('admin.acciones.list') }}",
            store: "{{ route('admin.acciones.store') }}",
            base: "{{ url('admin/acciones') }}",
            nextCode: "{{ route('admin.acciones.next-code') }}",
            summary: "{{ route('admin.acciones.summary') }}",
            defaultShareValue: "{{ $defaultShareValue }}",
            nextCodeValue: "{{ $nextCode }}"
        };
    </script>

    @vite(['resources/js/pages/member-share.js'])
@endpush
