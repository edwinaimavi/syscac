@php
    $isPending = in_array($closure->status, ['calculado', 'pendiente_regularizacion'], true);
    $isPendingRegularization = $closure->status === 'pendiente_regularizacion' || ($isPending && (float) $closure->final_balance < 0);
    $isConfirmed = $closure->status === 'cerrado';
    $isAnnulled = $closure->status === 'anulado';
    $canConfirm = $isPending && (float) $closure->final_balance >= 0;
@endphp

<div class="closure-actions" role="group" aria-label="Acciones del cierre {{ $closure->code }}">
    @can('retiros.show')
        <button type="button" class="btn btn-sm btn-outline-primary showClosure" data-id="{{ $closure->id }}" data-toggle="tooltip" title="Ver detalle" aria-label="Ver detalle">
            <i class="fas fa-eye" aria-hidden="true"></i>
        </button>
    @endcan

    @if($isPending)
        @can('retiros.edit')
            <button type="button" class="btn btn-sm {{ $isPendingRegularization ? 'btn-warning' : 'btn-outline-secondary' }} editClosure" data-id="{{ $closure->id }}" data-toggle="tooltip" title="{{ $isPendingRegularization ? 'Editar / Recalcular cierre' : 'Editar cálculo' }}" aria-label="{{ $isPendingRegularization ? 'Editar / Recalcular cierre' : 'Editar cálculo' }}">
                <i class="fas {{ $isPendingRegularization ? 'fa-sync-alt' : 'fa-edit' }}" aria-hidden="true"></i>
            </button>
        @endcan
        @can('retiros.close')
          @if($canConfirm)
            <button type="button" class="btn btn-sm btn-success closeClosure" data-id="{{ $closure->id }}" data-toggle="tooltip" title="Confirmar retiro" aria-label="Confirmar retiro">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
            </button>
          @endif
        @endcan
    @endif

    @can('retiros.report')
        <a href="{{ route('admin.retiros-socios.pdf', $closure) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger" data-toggle="tooltip" title="{{ $isConfirmed ? 'Constancia PDF' : 'PDF preliminar' }}" aria-label="{{ $isConfirmed ? 'Constancia PDF' : 'PDF preliminar' }}">
            <i class="fas fa-file-pdf" aria-hidden="true"></i>
        </a>
    @endcan

    @if($isConfirmed && $closure->receipt_id)
        @can('retiros.receipt')
            <a href="{{ route('admin.retiros-socios.receipt', $closure) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info" data-toggle="tooltip" title="Ver recibo" aria-label="Ver recibo">
                <i class="fas fa-receipt" aria-hidden="true"></i>
            </a>
        @endcan
    @endif

    @if($isConfirmed && $closure->voucher_path)
        @can('retiros.voucher')
            <a href="{{ route('admin.retiros-socios.voucher.view', $closure) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark" data-toggle="tooltip" title="Ver comprobante" aria-label="Ver comprobante">
                <i class="fas fa-paperclip" aria-hidden="true"></i>
            </a>
        @endcan
    @endif

    @if($isPending)
        @can('retiros.anular')
            <button type="button" class="btn btn-sm btn-outline-danger annulClosure" data-id="{{ $closure->id }}" data-toggle="tooltip" title="Anular solicitud" aria-label="Anular solicitud">
                <i class="fas fa-ban" aria-hidden="true"></i>
            </button>
        @endcan
    @endif

</div>
