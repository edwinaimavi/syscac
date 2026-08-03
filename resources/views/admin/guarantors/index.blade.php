@extends('layouts.app')

@section('subtitle', 'Avales / Garantes')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <i class="fas fa-user-shield"></i> Avales / Garantes
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                    <li class="breadcrumb-item active">Avales / Garantes</li>
                </ol>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Avales registrados</span><strong id="guarantorSummaryTotal">0</strong></div>
        <div class="cash-summary-card"><span>Externos historicos/inactivos</span><strong id="guarantorSummaryExternal">0</strong></div>
        <div class="cash-summary-card"><span>Avales socios</span><strong id="guarantorSummaryMember">0</strong></div>
        <div class="cash-summary-card"><span>Activos</span><strong id="guarantorSummaryActive">0</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tableGuarantors" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Codigo socio</th>
                            <th>Socio respaldado</th>
                            <th>DNI socio</th>
                            <th>Garante socio</th>
                            <th>DNI garante</th>
                            <th>Aportes garante</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    @include('admin.guarantors.partials.modal')
    @include('admin.guarantors.partials.detail-modal')
@stop

@push('js')
    <script>
        window.guarantorRoutes = {
            list: "{{ route('admin.avales.list') }}",
            store: "{{ route('admin.avales.store') }}",
            base: "{{ url('admin/avales') }}",
            nextCode: "{{ route('admin.avales.next-code') }}",
            summary: "{{ route('admin.avales.summary') }}",
            verifyDni: "{{ url('admin/avales/verificar-dni') }}",
            consultarDni: "{{ url('admin/socios/consultar-dni') }}",
            nextCodeValue: "{{ $nextCode }}",
            defaultAvatar: "https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg"
        };
    </script>
    @vite(['resources/js/pages/guarantor.js'])
@endpush
