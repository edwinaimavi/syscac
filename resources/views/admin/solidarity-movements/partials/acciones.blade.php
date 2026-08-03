<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de solidaridad">
    @can('admin.solidaridad.show')
        <button type="button" class="btn btn-light border showSolidarity" data-id="{{ $movement->id }}" title="Ver detalle">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.solidaridad.edit')
        @if ($movement->status !== 'anulado' && !$movement->source_type)
            <button type="button" class="btn btn-light border editSolidarity" data-id="{{ $movement->id }}" title="Editar movimiento">
                <i class="fas fa-edit"></i>
            </button>
        @endif
    @endcan

    @can('admin.solidaridad.receipt')
        @if ($movement->receipt_id)
            <a href="{{ route('admin.solidaridad.receipt', $movement) }}" class="btn btn-light border" target="_blank" rel="noopener" title="Ver recibo">
                <i class="fas fa-receipt"></i>
            </a>
        @endif
    @endcan

    @can('admin.solidaridad.voucher')
        @if ($movement->voucher_path)
            <a href="{{ route('admin.solidaridad.voucher', $movement) }}" class="btn btn-light border" target="_blank" rel="noopener" title="Ver comprobante">
                <i class="fas fa-paperclip"></i>
            </a>
        @endif
    @endcan

    @can('admin.solidaridad.anular')
        @if ($movement->status !== 'anulado' && !$movement->source_type)
            <button type="button" class="btn btn-light border text-danger annulSolidarity" data-id="{{ $movement->id }}" title="Anular">
                <i class="fas fa-ban"></i>
            </button>
        @endif
    @endcan
</div>
