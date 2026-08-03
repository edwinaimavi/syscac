<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de recibo">
    @can('admin.recibos.show')
        <button type="button" class="btn btn-light border showReceipt" data-id="{{ $receipt->id }}" title="Ver"><i class="fas fa-eye"></i></button>
    @endcan
    @can('admin.recibos.print')
        <a href="{{ route('admin.recibos.print', $receipt) }}" target="_blank" class="btn btn-light border" title="Imprimir"><i class="fas fa-print"></i></a>
    @endcan
    @can('admin.recibos.pdf')
        <a href="{{ route('admin.recibos.pdf', $receipt) }}" target="_blank" class="btn btn-light border" title="PDF"><i class="fas fa-file-pdf"></i></a>
    @endcan
    @can('admin.recibos.voucher')
        @if ($receipt->voucher_path)
            <a href="{{ route('admin.recibos.voucher', $receipt) }}" target="_blank" class="btn btn-light border" title="Comprobante"><i class="fas fa-paperclip"></i></a>
        @endif
    @endcan
    @can('admin.recibos.delete')
        @if (! $receipt->related_type && $receipt->status !== 'anulado')
            <button type="button" class="btn btn-light border text-danger annulReceipt" data-id="{{ $receipt->id }}" title="Anular recibo"><i class="fas fa-ban"></i></button>
        @endif
    @endcan
</div>
