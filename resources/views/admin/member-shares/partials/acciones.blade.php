@php
    $voucher = app(\App\Http\Controllers\Admin\MemberShareController::class)->voucherStateForView($share);
@endphp

<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de aporte">
    @can('admin.acciones.show')
        <button type="button" class="btn btn-light border showShare" data-id="{{ $share->id }}" title="Ver detalle">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.acciones.edit')
        @if ($share->status !== 'anulado')
            <button type="button" class="btn btn-light border editShare" data-id="{{ $share->id }}" title="Editar aporte">
                <i class="fas fa-edit"></i>
            </button>
        @endif
    @endcan

    @can('admin.acciones.receipt')
        <a href="{{ route('admin.acciones.receipt', $share) }}" class="btn btn-light border" target="_blank" rel="noopener" title="Ver recibo">
            <i class="fas fa-receipt"></i>
        </a>
        <a href="{{ route('admin.acciones.receipt.pdf', $share) }}" class="btn btn-light border" target="_blank" rel="noopener" title="PDF">
            <i class="fas fa-file-pdf"></i>
        </a>
    @endcan

    @can('admin.acciones.show')
        @if ($voucher['status'] === 'available')
            <a href="{{ route('admin.acciones.voucher.view', $share) }}" class="btn btn-light border" target="_blank" rel="noopener" title="Ver comprobante">
                <i class="fas fa-paperclip"></i>
            </a>
        @else
            <button type="button" class="btn btn-light border disabled" disabled title="{{ $voucher['message'] }}">
                <i class="fas fa-paperclip"></i>
            </button>
        @endif
    @endcan

    @can('admin.acciones.anular')
        @if ($share->status !== 'anulado')
            <button type="button" class="btn btn-light border text-danger annulShare" data-id="{{ $share->id }}" title="Anular aporte">
                <i class="fas fa-ban"></i>
            </button>
        @endif
    @endcan
</div>
