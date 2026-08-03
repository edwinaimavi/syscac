<div class="d-flex justify-content-center align-items-center gap-2">
    @can('avales.show')
        <button type="button" class="btn btn-outline-primary btn-xs showGuarantor" title="Ver detalle" data-id="{{ $guarantor->id }}">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('avales.edit')
        <button type="button" class="btn btn-outline-info btn-xs editGuarantor" title="Editar aval" data-id="{{ $guarantor->id }}">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('avales.anular')
        @if ($guarantor->status !== 'anulado')
            <button type="button" class="btn btn-outline-danger btn-xs annulGuarantor" title="Anular aval" data-id="{{ $guarantor->id }}">
                <i class="fas fa-ban"></i>
            </button>
        @endif
    @endcan
</div>
