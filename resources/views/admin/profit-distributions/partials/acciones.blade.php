<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de utilidades">
    @can('admin.utilidades.show')<button type="button" class="btn btn-light border showProfit" data-id="{{ $distribution->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>@endcan
    @can('admin.utilidades.edit')@if($distribution->status === 'calculado')<button type="button" class="btn btn-light border editProfit" data-id="{{ $distribution->id }}" title="Editar"><i class="fas fa-edit"></i></button>@endif@endcan
    @can('admin.utilidades.approve')@if($distribution->status === 'calculado')<button type="button" class="btn btn-light border approveProfit" data-id="{{ $distribution->id }}" title="Aprobar"><i class="fas fa-check"></i></button>@endif@endcan
    @can('admin.utilidades.report')<a href="{{ route('admin.utilidades.report', $distribution) }}" target="_blank" class="btn btn-light border" title="Reporte"><i class="fas fa-file-alt"></i></a>@endcan
    @can('admin.utilidades.anular')@if($distribution->status !== 'anulado')<button type="button" class="btn btn-light border text-danger annulProfit" data-id="{{ $distribution->id }}" title="Anular"><i class="fas fa-ban"></i></button>@endif@endcan
</div>
