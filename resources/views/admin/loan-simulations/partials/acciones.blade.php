<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de simulacion">
    @can('admin.simulaciones.show')
        <button type="button" class="btn btn-light border showLoanSimulation" data-id="{{ $simulation->id }}" title="Ver cronograma">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.simulaciones.edit')
        @if ($simulation->status === 'simulada')
            <button type="button" class="btn btn-light border editLoanSimulation" data-id="{{ $simulation->id }}" title="Editar simulacion">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn btn-light border text-secondary effectLoanSimulation" data-id="{{ $simulation->id }}" title="Dejar sin efecto">
                <i class="fas fa-times-circle"></i>
            </button>
        @endif
    @endcan

    @can('admin.prestamos.create')
        @if ($simulation->status === 'simulada')
            <a href="{{ route('admin.prestamos.index', ['simulation_id' => $simulation->id]) }}" class="btn btn-light border text-success" title="Convertir a prestamo">
                <i class="fas fa-exchange-alt"></i>
            </a>
        @elseif ($simulation->status === 'convertida' && $simulation->converted_loan_id)
            <a href="{{ route('admin.prestamos.index', ['loan_id' => $simulation->converted_loan_id]) }}" class="btn btn-light border text-info" title="Ver prestamo generado">
                <i class="fas fa-file-invoice-dollar"></i>
            </a>
        @endif
    @endcan

    @can('admin.simulaciones.print')
        <a href="{{ route('admin.loan-simulations.print', $simulation) }}" class="btn btn-light border" target="_blank" rel="noopener" title="Imprimir cronograma">
            <i class="fas fa-print"></i>
        </a>
    @endcan

    @can('admin.simulaciones.anular')
        @if ($simulation->status === 'simulada')
            <button type="button" class="btn btn-light border text-danger annulLoanSimulation" data-id="{{ $simulation->id }}" title="Anular simulacion">
                <i class="fas fa-ban"></i>
            </button>
        @endif
    @endcan
</div>
