@extends('layouts.app')
@section('subtitle','Fondo administrativo')
@section('header')
<div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-file-invoice-dollar"></i> Fondo administrativo
@can('admin.fondo-administrativo.create')<button class="btn btn-app bg-dark" id="btnNewAdministrative"><i class="fas fa-plus-circle"></i> Nuevo movimiento</button>@endcan
</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{route('home')}}">Home</a></li><li class="breadcrumb-item active">Fondo administrativo</li></ol></div></div></div>
@stop
@section('content_body')
<div class="cash-summary-grid mb-3">
<div class="cash-summary-card primary"><span>Saldo fondo administrativo</span><strong id="adminSummaryBalance">S/ 0.00</strong></div>
<div class="cash-summary-card"><span>Total ingresos</span><strong id="adminSummaryIncome">S/ 0.00</strong></div>
<div class="cash-summary-card"><span>Total egresos</span><strong id="adminSummaryExpense">S/ 0.00</strong></div>
<div class="cash-summary-card"><span>Movimientos del mes</span><strong id="adminSummaryMonth">0</strong></div></div>
<div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
@foreach(['date_from'=>'Desde','date_to'=>'Hasta'] as $id=>$label)<div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">{{$label}}</label><input type="date" id="admin_filter_{{$id}}" class="form-control form-control-sm"></div>@endforeach
<div class="form-group col-md-1"><label>Tipo</label><select id="admin_filter_type" class="form-control form-control-sm"><option value="">Todos</option><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
<div class="form-group col-md-3"><label>Socio</label><select id="admin_filter_member_id" class="form-control form-control-sm"><option value="">Todos</option>@foreach($members as $m)<option value="{{$m->id}}">{{$m->code}} - {{$m->dni}} - {{$m->full_name}}</option>@endforeach</select></div>
<div class="form-group col-md-1"><label>Estado</label><select id="admin_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="registrado">Registrado</option><option value="anulado">Anulado</option></select></div>
<div class="form-group col-md-2"><label>Método</label><select id="admin_filter_payment_method" class="form-control form-control-sm"><option value="">Todos</option>@foreach($paymentMethods as $v=>$l)<option value="{{$v}}">{{$l}}</option>@endforeach</select></div>
<div class="form-group col-md-1"><button class="btn btn-light border btn-block" id="btnClearAdministrativeFilters"><i class="fas fa-undo"></i></button></div>
</div></div></div>
<div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive"><table id="tableAdministrative" class="tableStiles table table-hover text-center mb-0"><thead><tr><th>#</th><th>Código</th><th>Fecha</th><th>Tipo</th><th>Socio</th><th>Concepto</th><th>Método</th><th>Monto</th><th>Estado</th><th></th></tr></thead></table></div></div></div>
@include('admin.administrative-fund.partials.modal')
@include('admin.administrative-fund.partials.detail-modal')
@stop
@push('js')<script>window.administrativeRoutes={list:"{{route('admin.fondo-administrativo.list')}}",store:"{{route('admin.fondo-administrativo.store')}}",base:"{{url('admin/fondo-administrativo')}}",summary:"{{route('admin.fondo-administrativo.summary')}}",nextCode:"{{route('admin.fondo-administrativo.next-code')}}",nextCodeValue:"{{$nextCode}}"}</script>@vite(['resources/js/pages/administrative-fund.js'])@endpush
