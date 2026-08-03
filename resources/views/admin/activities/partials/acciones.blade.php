<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de actividad">
    @can('admin.actividades.show')
        <button type="button" class="btn btn-light border showActivity" data-id="{{ $activity->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>
    @endcan
    @can('admin.actividades.edit')
        @if ($activity->status !== 'anulada')
            <button type="button" class="btn btn-light border editActivity" data-id="{{ $activity->id }}" title="Editar"><i class="fas fa-edit"></i></button>
        @endif
    @endcan
    @can('admin.actividades.movements')
        <button type="button" class="btn btn-light border showActivity" data-id="{{ $activity->id }}" title="Movimientos"><i class="fas fa-exchange-alt"></i></button>
    @endcan
    @can('admin.actividades.close')
        @if ($activity->status === 'abierta')
            <button type="button" class="btn btn-light border closeActivity" data-id="{{ $activity->id }}" title="Cerrar"><i class="fas fa-lock"></i></button>
        @endif
    @endcan
    @can('admin.actividades.anular')
        @if ($activity->status !== 'anulada')
            <button type="button" class="btn btn-light border text-danger annulActivity" data-id="{{ $activity->id }}" title="Anular"><i class="fas fa-ban"></i></button>
        @endif
    @endcan
</div>
