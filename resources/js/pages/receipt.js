var divLoading = document.getElementById('divLoading');
let tableReceipt;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableReceipt = $('#tableReceipt').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.receiptRoutes.list,
            data: function (data) {
                data.date_from = $('#receipt_filter_date_from').val();
                data.date_to = $('#receipt_filter_date_to').val();
                data.type = $('#receipt_filter_type').val();
                data.member_id = $('#receipt_filter_member_id').val();
                data.payment_method = $('#receipt_filter_payment_method').val();
                data.status = $('#receipt_filter_status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'receipt_number', name: 'receipt_number' },
            { data: 'receipt_date', name: 'receipt_date' },
            { data: 'type', name: 'type' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'concept_reference', name: 'payment_reference', orderable: false, searchable: false },
            { data: 'amount', name: 'amount' },
            { data: 'payment_method', name: 'payment_method' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadReceiptSummary();
    tableReceipt.on('draw', loadReceiptSummary);
    $('#receipt_filter_date_from, #receipt_filter_date_to, #receipt_filter_type, #receipt_filter_member_id, #receipt_filter_payment_method, #receipt_filter_status').on('change', function () { tableReceipt.ajax.reload(); });
    $('#btnClearReceiptFilters').on('click', function () { $('#receipt_filter_date_from, #receipt_filter_date_to, #receipt_filter_type, #receipt_filter_member_id, #receipt_filter_payment_method, #receipt_filter_status').val(''); tableReceipt.ajax.reload(); });

    $(document).on('click', '.showReceipt', function () {
        setLoading(true);
        $.get(`${window.receiptRoutes.base}/${$(this).data('id')}`, function (receipt) {
            setLoading(false);
            fillReceiptDetail(receipt);
            $('#receiptDetailModal').modal('show');
        }).fail(function (xhr) {
            setLoading(false);
            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo cargar el recibo.', 'error');
        });
    });

    $(document).on('click', '.annulReceipt', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Anular recibo',
            text: 'Solo se anulara el recibo independiente. Si pertenece a otro modulo, anule el registro de origen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: `${window.receiptRoutes.base}/${id}`,
                type: 'DELETE',
                success: function (response) {
                    tableReceipt.ajax.reload(null, false);
                    loadReceiptSummary();
                    Swal.fire({ title: response.message, icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo anular el recibo.', 'error');
                }
            });
        });
    });
});

function fillReceiptDetail(receipt) {
    $('#detailReceiptNumber').text(receipt.receipt_number || '-');
    $('#detailReceiptType').text(receipt.type_label || '-');
    $('#detailReceiptStatus').html(`<span class="badge badge-${receipt.status === 'anulado' ? 'danger' : 'success'}">${escapeHtml(receipt.status_label || '-')}</span>`);
    $('#detailReceiptDate').text(receipt.receipt_date || '-');
    $('#detailReceiptMember').text(receipt.member_name || '-');
    $('#detailReceiptAmount').text(receipt.amount_formatted || 'S/ 0.00');
    $('#detailReceiptDni').text(receipt.member_dni || '-');
    $('#detailReceiptMemberCode').text(receipt.member_code || '-');
    $('#detailReceiptMethod').text(receipt.payment_method_label || '-');
    $('#detailReceiptReference').text(receipt.payment_reference || receipt.concept_reference || '-');
    $('#detailReceiptOrigin').text(receipt.origin_label && receipt.origin_id ? `${receipt.origin_label} #${receipt.origin_id}` : '-');
    $('#detailReceiptUser').text(receipt.created_by_name || '-');
    $('#detailReceiptObservation').text(receipt.observation || '-');
    const originRows = (receipt.origin_details || []).map((row) => `<div><span>${escapeHtml(row.label)}</span><strong>${escapeHtml(row.value)}</strong></div>`).join('');
    $('#detailReceiptOriginRows').html(originRows || '<div><span>Relacion</span><strong>Sin datos relacionados</strong></div>');
    updateLink('#detailReceiptPrint', receipt.print_url, 'Imprimir', 'Imprimir');
    updateLink('#detailReceiptPdf', receipt.pdf_url, 'PDF', 'PDF');
    updateLink('#detailReceiptVoucher', receipt.voucher_url, 'Ver comprobante', 'Sin comprobante');
}

function loadReceiptSummary() {
    $.get(window.receiptRoutes.summary, function (summary) {
        $('#receiptSummaryIssued').text(summary.issued || '0');
        $('#receiptSummaryTotal').text(`S/ ${summary.total || '0.00'}`);
        $('#receiptSummaryMonth').text(summary.month || '0');
        $('#receiptSummaryAnnulled').text(summary.annulled || '0');
    });
}

function updateLink(selector, url, text, emptyText) {
    const link = $(selector);
    link.contents().filter(function () { return this.nodeType === 3; }).remove();
    if (url) link.attr('href', url).removeClass('disabled').append(` ${escapeHtml(text)}`);
    else link.attr('href', '#').addClass('disabled').append(` ${escapeHtml(emptyText)}`);
}

function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
