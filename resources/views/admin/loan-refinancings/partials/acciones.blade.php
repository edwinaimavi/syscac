<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de refinanciamiento">
    @can('admin.refinanciamientos.show')
        <button type="button" class="btn btn-light border showRefinancing" data-id="{{ $refinancing->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>
    @endcan
    @can('admin.refinanciamientos.schedule')
        <button type="button" class="btn btn-light border scheduleRefinancing" data-id="{{ $refinancing->id }}" title="Ver cronograma"><i class="fas fa-calendar-alt"></i></button>
    @endcan
    @can('admin.refinanciamientos.print')
        <a href="{{ route('admin.refinanciamientos.print', $refinancing) }}" target="_blank" class="btn btn-light border" title="Constancia"><i class="fas fa-print"></i></a>
    @endcan
    @can('admin.refinanciamientos.pdf')
        <a href="{{ route('admin.refinanciamientos.pdf', $refinancing) }}" target="_blank" class="btn btn-light border" title="PDF"><i class="fas fa-file-pdf"></i></a>
    @endcan
    @can('admin.refinanciamientos.anular')
        @if ($refinancing->status !== 'anulado')
            <button type="button" class="btn btn-light border text-danger annulRefinancing" data-id="{{ $refinancing->id }}" title="Anular"><i class="fas fa-ban"></i></button>
        @endif
    @endcan
</div>
