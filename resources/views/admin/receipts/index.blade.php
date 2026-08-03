@extends('layouts.app')

@section('subtitle', 'Recibos')

@section('header')
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-receipt"></i> Recibos</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fa fa-fw fa-house-user"></i> Home</a></li><li class="breadcrumb-item active">Recibos</li></ol></div></div></div>
@stop

@section('content_body')
    <div class="cash-summary-grid mb-3">
        <div class="cash-summary-card primary"><span>Recibos emitidos</span><strong id="receiptSummaryIssued">0</strong></div>
        <div class="cash-summary-card"><span>Monto total emitido</span><strong id="receiptSummaryTotal">S/ 0.00</strong></div>
        <div class="cash-summary-card"><span>Recibos del mes</span><strong id="receiptSummaryMonth">0</strong></div>
        <div class="cash-summary-card"><span>Recibos anulados</span><strong id="receiptSummaryAnnulled">0</strong></div>
    </div>

    <div class="card shadow-sm border-0 rounded-lg mb-3"><div class="card-body p-3"><div class="form-row align-items-end">
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Desde</label><input type="date" id="receipt_filter_date_from" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Hasta</label><input type="date" id="receipt_filter_date_to" class="form-control form-control-sm"></div>
        <div class="form-group col-md-2"><label class="small font-weight-bold text-secondary">Tipo</label><select id="receipt_filter_type" class="form-control form-control-sm"><option value="">Todos</option>@foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
        <div class="form-group col-md-3"><label class="small font-weight-bold text-secondary">Socio</label><select id="receipt_filter_member_id" class="form-control form-control-sm"><option value="">Todos</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
        <div class="form-group col-md-1"><label class="small font-weight-bold text-secondary">Metodo</label><select id="receipt_filter_payment_method" class="form-control form-control-sm"><option value="">Todos</option><option value="efectivo">Efectivo</option><option value="yape">Yape</option><option value="plin">Plin</option><option value="transferencia">Transferencia</option><option value="otro">Otro</option></select></div>
        <div class="form-group col-md-1"><label class="small font-weight-bold text-secondary">Estado</label><select id="receipt_filter_status" class="form-control form-control-sm"><option value="">Todos</option><option value="registrado">Registrado</option><option value="anulado">Anulado</option></select></div>
        <div class="form-group col-md-1"><button type="button" class="btn btn-light border btn-block" id="btnClearReceiptFilters"><i class="fas fa-undo"></i></button></div>
    </div></div></div>

    <div class="card shadow-sm border-0 rounded-lg"><div class="card-body p-3"><div class="table-responsive">
        <table id="tableReceipt" class="tableStiles table table-hover align-middle mb-0 text-center shadow-sm rounded-lg">
            <thead><tr><th>#</th><th>Numero</th><th>Fecha</th><th>Tipo</th><th>Socio</th><th>Concepto / Referencia</th><th>Monto</th><th>Metodo pago</th><th>Estado</th><th></th></tr></thead>
        </table>
    </div></div></div>

    @include('admin.receipts.partials.detail-modal')
@stop

@push('js')
    <script>
        window.receiptRoutes = {
            list: "{{ route('admin.recibos.list') }}",
            summary: "{{ route('admin.recibos.summary') }}",
            base: "{{ url('admin/recibos') }}"
        };
    </script>
    @vite(['resources/js/pages/receipt.js'])
@endpush
