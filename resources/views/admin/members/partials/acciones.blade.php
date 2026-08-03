<div class="d-flex justify-content-center align-items-center gap-2">
    @can('admin.socios.show')
        <button type="button" class="btn btn-outline-primary btn-xs showMember" title="Ver detalle" data-id="{{ $member->id }}">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.socios.edit')
        @if($member->status === 'vigente' && ! $member->retirement_date)
        <button type="button" class="btn btn-outline-info btn-xs editMember" title="Editar socio" data-id="{{ $member->id }}">
            <i class="fas fa-pen"></i>
        </button>
        @endif
    @endcan

    @if($closure)
        @can('retiros.report')
            <a href="{{ route('admin.retiros-socios.pdf', $closure) }}" target="_blank" class="btn btn-outline-secondary btn-xs" title="Ver constancia de retiro"><i class="fas fa-file-pdf"></i></a>
        @endcan
    @endif

    @can('admin.socios.delete')
        @if($member->status === 'vigente' && ! $hasMovements)
            <button type="button" class="btn btn-outline-danger btn-xs deleteMember" title="Eliminar socio" data-id="{{ $member->id }}"><i class="fas fa-trash"></i></button>
        @elseif(in_array($member->status, ['retirado', 'no_vigente'], true))
            <span title="No se puede eliminar porque el socio tiene historial y cierre de cuenta confirmado."><button type="button" class="btn btn-outline-secondary btn-xs" disabled aria-label="Eliminar socio deshabilitado"><i class="fas fa-trash"></i></button></span>
        @else
            <span title="No se puede eliminar porque tiene movimientos registrados."><button type="button" class="btn btn-outline-secondary btn-xs" disabled><i class="fas fa-trash"></i></button></span>
        @endif
    @endcan
</div>
