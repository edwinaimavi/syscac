<div class="btn-group btn-group-sm">
@can('admin.fondo-administrativo.show')<button class="btn btn-light border showAdministrative" data-id="{{$movement->id}}"><i class="fas fa-eye"></i></button>@endcan
@if(!$movement->source_type && $movement->status!=='anulado')
@can('admin.fondo-administrativo.edit')<button class="btn btn-light border editAdministrative" data-id="{{$movement->id}}"><i class="fas fa-edit"></i></button>@endcan
@can('admin.fondo-administrativo.anular')<button class="btn btn-light border text-danger annulAdministrative" data-id="{{$movement->id}}"><i class="fas fa-ban"></i></button>@endcan
@endif
</div>
