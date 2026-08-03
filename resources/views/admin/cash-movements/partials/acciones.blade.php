@php
    $cashController = app(\App\Http\Controllers\Admin\CashMovementController::class);
    $isRelated = $cashController->isRelatedForView($movement);
    $voucher = $cashController->voucherStateForView($movement);
@endphp

<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de caja">
    @can('admin.caja.show')
        <button type="button" class="btn btn-light border showCashMovement" data-id="{{ $movement->id }}" title="Ver detalle">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.caja.edit')
        @if (! $isRelated && $movement->status === 'registrado')
            <button type="button" class="btn btn-light border editCashMovement" data-id="{{ $movement->id }}" title="Editar movimiento">
                <i class="fas fa-edit"></i>
            </button>
        @endif
    @endcan

    @can('admin.caja.show')
        @if ($voucher['status'] === 'available')
            <a href="{{ route('admin.caja.voucher', $movement) }}" class="btn btn-light border" target="_blank" rel="noopener" title="Ver comprobante">
                <i class="fas fa-paperclip"></i>
            </a>
        @else
            <button type="button" class="btn btn-light border disabled" disabled title="{{ $voucher['message'] }}">
                <i class="fas fa-paperclip"></i>
            </button>
        @endif
    @endcan

    @can('admin.caja.anular')
        @if (! $isRelated && $movement->status === 'registrado')
            <button type="button" class="btn btn-light border text-danger annulCashMovement" data-id="{{ $movement->id }}" title="Anular movimiento">
                <i class="fas fa-ban"></i>
            </button>
        @endif
    @endcan
</div>
