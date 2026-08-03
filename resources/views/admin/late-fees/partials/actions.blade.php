<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de configuración de mora">
    @can('mora.view')
        <button
            type="button"
            class="btn btn-light border showLateFee"
            data-id="{{ $setting->id }}"
            title="Ver detalle"
        >
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('mora.edit')
        <button
            type="button"
            class="btn btn-light border editLateFee"
            data-id="{{ $setting->id }}"
            title="Editar"
        >
            <i class="fas fa-edit"></i>
        </button>
    @endcan

    @can('mora.activate')
        <button
            type="button"
            class="btn btn-light border toggleLateFee {{ $setting->is_active ? 'text-warning' : 'text-success' }}"
            data-id="{{ $setting->id }}"
            data-active="{{ $setting->is_active ? 1 : 0 }}"
            title="{{ $setting->is_active ? 'Desactivar' : 'Activar' }}"
        >
            <i class="fas fa-{{ $setting->is_active ? 'toggle-off' : 'toggle-on' }}"></i>
        </button>
    @endcan

    @can('mora.delete')
        @if (! $setting->is_active)
            <button
                type="button"
                class="btn btn-light border text-danger deleteLateFee"
                data-id="{{ $setting->id }}"
                title="Eliminar"
            >
                <i class="fas fa-trash-alt"></i>
            </button>
        @endif
    @endcan
</div>
