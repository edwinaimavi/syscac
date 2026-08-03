<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de cobro">
    @can('admin.cobros.show')
        <button type="button" class="btn btn-light border showLoanPayment" data-id="{{ $payment->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>
    @endcan
    @can('admin.cobros.edit')
        @if ($payment->status !== 'anulado')
            <button type="button" class="btn btn-light border editLoanPayment" data-id="{{ $payment->id }}" title="Editar referencia/comprobante"><i class="fas fa-edit"></i></button>
        @endif
    @endcan
    @can('admin.cobros.receipt')
        @if ($payment->receipt_id)
            <a href="{{ route('admin.cobros.receipt', $payment) }}" target="_blank" class="btn btn-light border" title="Ver recibo"><i class="fas fa-receipt"></i></a>
        @endif
    @endcan
    @can('admin.cobros.receipt_pdf')
        @if ($payment->receipt_id)
            <a href="{{ route('admin.cobros.receipt.pdf', $payment) }}" target="_blank" class="btn btn-light border" title="PDF"><i class="fas fa-file-pdf"></i></a>
        @endif
    @endcan
    @can('admin.cobros.voucher')
        @if ($payment->voucher_path)
            <a href="{{ route('admin.cobros.voucher', $payment) }}" target="_blank" class="btn btn-light border" title="Ver comprobante"><i class="fas fa-paperclip"></i></a>
        @endif
    @endcan
    @can('admin.cobros.anular')
        @if ($payment->status !== 'anulado')
            <button type="button" class="btn btn-light border text-danger annulLoanPayment" data-id="{{ $payment->id }}" title="Anular"><i class="fas fa-ban"></i></button>
        @endif
    @endcan
</div>
