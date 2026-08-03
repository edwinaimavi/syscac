<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de prestamo">
    @can('admin.prestamos.show')
        <button type="button" class="btn btn-light border showLoan" data-id="{{ $loan->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>
    @endcan
    @can('admin.prestamos.schedule')
        <button type="button" class="btn btn-light border scheduleLoan" data-id="{{ $loan->id }}" title="Ver cronograma"><i class="fas fa-calendar-alt"></i></button>
    @endcan
    @can('admin.prestamos.schedule_print')
        <a href="{{ route('admin.prestamos.schedule.print', $loan) }}" target="_blank" class="btn btn-light border" title="Imprimir cronograma"><i class="fas fa-print"></i></a>
    @endcan
    @can('admin.prestamos.schedule_pdf')
        <a href="{{ route('admin.prestamos.schedule.pdf', $loan) }}" target="_blank" class="btn btn-light border" title="Descargar PDF cronograma"><i class="fas fa-file-pdf"></i></a>
    @endcan
    @can('admin.prestamos.disbursement_receipt')
        @if ($loan->disbursement_receipt_id)
            <a href="{{ route('admin.prestamos.disbursement.receipt', $loan) }}" target="_blank" class="btn btn-light border" title="Recibo de desembolso"><i class="fas fa-receipt"></i></a>
        @endif
    @endcan
    @can('admin.prestamos.disbursement_voucher')
        @if ($loan->disbursement_voucher_path)
            <a href="{{ route('admin.prestamos.disbursement.voucher', $loan) }}" target="_blank" class="btn btn-light border" title="Comprobante de desembolso"><i class="fas fa-paperclip"></i></a>
        @endif
    @endcan
    @can('admin.prestamos.edit')
        @if (in_array($loan->status, ['pendiente', 'aprobado']))
            <button type="button" class="btn btn-light border editLoan" data-id="{{ $loan->id }}" title="Editar"><i class="fas fa-edit"></i></button>
        @endif
    @endcan
    @can('admin.prestamos.approve')
        @if ($loan->status === 'pendiente')
            <button type="button" class="btn btn-light border text-info approveLoan" data-id="{{ $loan->id }}" title="Aprobar"><i class="fas fa-check"></i></button>
        @endif
    @endcan
    @can('admin.prestamos.disburse')
        @if ($loan->status === 'aprobado')
            <button type="button" class="btn btn-light border text-success disburseLoan" data-id="{{ $loan->id }}" title="Desembolsar"><i class="fas fa-money-bill-wave"></i></button>
        @endif
    @endcan
    @can('admin.prestamos.annul')
        @if (in_array($loan->status, ['pendiente', 'aprobado']))
            <button type="button" class="btn btn-light border text-danger annulLoan" data-id="{{ $loan->id }}" title="Anular"><i class="fas fa-ban"></i></button>
        @endif
    @endcan
</div>
