@extends('layouts.app')

@section('subtitle', 'Configuración de mora')

@section('header')
<div class="container-fluid"><div class="row mb-2"><div class="col-sm-7"><h1><i class="fas fa-clock"></i> Configuración de mora
@can('mora.create')<button class="btn btn-app bg-dark" type="button" id="btnNewLateFee"><i class="fas fa-plus-circle"></i> Nueva configuración</button>@endcan
</h1></div><div class="col-sm-5"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fas fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Configuración de mora</li></ol></div></div></div>
@stop

@section('content_body')
<div class="cash-summary-grid mb-3">
    <div class="cash-summary-card primary"><span>Configuraciones registradas</span><strong id="lateFeeSummaryTotal">0</strong></div>
    <div class="cash-summary-card"><span>Configuración activa</span><strong id="lateFeeSummaryActive">-</strong></div>
    <div class="cash-summary-card"><span>Días de gracia actuales</span><strong id="lateFeeSummaryGrace">-</strong></div>
    <div class="cash-summary-card"><span>Tipo de mora actual</span><strong id="lateFeeSummaryType">-</strong></div>
</div>
<div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
<table id="tableLateFee" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg"><thead><tr>
<th>#</th><th>Código</th><th>Nombre</th><th>Días de gracia</th><th>Tipo</th><th>Valor</th><th>Máximo</th><th>Automático</th><th>Exoneración</th><th>Estado</th><th>Auditoría</th><th></th>
</tr></thead></table></div></div></div>
@include('admin.late-fees.partials.modal')
@include('admin.late-fees.partials.detail-modal')
@stop

@push('js')
<script>window.lateFeeRoutes={list:"{{ route('admin.mora.list') }}",summary:"{{ route('admin.mora.summary') }}",store:"{{ route('admin.mora.store') }}",base:"{{ url('admin/mora') }}"};</script>
@vite(['resources/js/pages/late-fee.js'])
@endpush
