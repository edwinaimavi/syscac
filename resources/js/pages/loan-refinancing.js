var divLoading = document.getElementById('divLoading');
let tableRefinancing;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableRefinancing = $('#tableRefinancing').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.refinancingRoutes.list,
            data: function (data) {
                data.date_from = $('#ref_filter_date_from').val();
                data.date_to = $('#ref_filter_date_to').val();
                data.member_id = $('#ref_filter_member_id').val();
                data.status = $('#ref_filter_status').val();
                data.has_overdue = $('#ref_filter_has_overdue').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code' },
            { data: 'refinancing_date', name: 'refinancing_date' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'member_dni', name: 'member.dni' },
            { data: 'original_loan_number', name: 'originalLoan.loan_number' },
            { data: 'new_loan_number', name: 'newLoan.loan_number' },
            { data: 'previous_balance', name: 'previous_balance' },
            { data: 'overdue_installments', orderable: false, searchable: false },
            { data: 'new_amount', name: 'new_amount' },
            { data: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadRefSummary();
    tableRefinancing.on('draw', loadRefSummary);
    $('#ref_filter_date_from, #ref_filter_date_to, #ref_filter_member_id, #ref_filter_status, #ref_filter_has_overdue').on('change', function () { tableRefinancing.ajax.reload(); });
    $('#btnClearRefFilters').on('click', function () { $('#ref_filter_date_from, #ref_filter_date_to, #ref_filter_member_id, #ref_filter_status, #ref_filter_has_overdue').val(''); tableRefinancing.ajax.reload(); });

    $('#btnNewRefinancing').on('click', function () { resetRefForm(); $('#refinancingModal').modal('show'); });
    $('#refMemberId').on('change', loadLoansByMember);
    $('#refLoanId').on('change', loadLoanBalance);
    $('[name="additional_amount"], [name="interest_rate"], [name="term_months"], [name="start_date"], [name="first_payment_date"]').on('input change', debounce(calculateRefinancing, 350));

    $('#refinancingForm').on('submit', function (event) {
        event.preventDefault();
        clearRefErrors();
        setLoading(true);
        $.ajax({
            url: window.refinancingRoutes.store,
            type: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                setLoading(false);
                $('#refinancingModal').modal('hide');
                tableRefinancing.ajax.reload(null, false);
                loadRefSummary();
                toast(response.message, 'success');
            },
            error: handleRefAjaxError
        });
    });

    $(document).on('click', '.showRefinancing', function () {
        setLoading(true);
        $.get(`${window.refinancingRoutes.base}/${$(this).data('id')}`, function (ref) {
            setLoading(false);
            fillRefDetail(ref);
            $('#refDetailModal').modal('show');
        }).fail(showActionError);
    });

    $(document).on('click', '.scheduleRefinancing', function () {
        setLoading(true);
        $.get(`${window.refinancingRoutes.base}/${$(this).data('id')}/cronograma`, function (response) {
            setLoading(false);
            fillRefSchedule(response.refinancing, response.installments || []);
            $('#refScheduleModal').modal('show');
        }).fail(showActionError);
    });

    $(document).on('click', '.annulRefinancing', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Anular refinanciamiento', text: 'Solo se permite si el nuevo prestamo no tiene cobros registrados.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Si, anular', cancelButtonText: 'Cancelar' }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(`${window.refinancingRoutes.base}/${id}/anular`, function (response) {
                tableRefinancing.ajax.reload(null, false);
                loadRefSummary();
                toast(response.message, 'success');
            }).fail(showActionError);
        });
    });
});

function resetRefForm() {
    $('#refinancingForm')[0].reset();
    clearRefErrors();
    $('[name="code"]').val(window.refinancingRoutes.nextCodeValue || 'REF-000001');
    $('[name="refinancing_date"], [name="start_date"]').val(new Date().toISOString().slice(0, 10));
    $('#refLoanId').html('<option value="">Seleccione socio</option>');
    setLoanBox({});
    updateSummary({});
    $.get(window.refinancingRoutes.nextCode, function (response) { $('[name="code"]').val(response.code || 'REF-000001'); });
}

function loadLoansByMember() {
    const memberId = $('#refMemberId').val();
    $('#refLoanId').html('<option value="">Seleccione</option>');
    if (!memberId) return;
    $.get(`${window.refinancingRoutes.memberLoansBase}/${memberId}/prestamos`, function (loans) {
        $('#refLoanId').html('<option value="">Seleccione</option>' + loans.map((loan) => `<option value="${loan.id}">${escapeHtml(loan.option_label || `${loan.loan_number} - Saldo: ${loan.current_balance_formatted}`)}</option>`).join(''));
    });
}

function loadLoanBalance() {
    const loanId = $('#refLoanId').val();
    if (!loanId) return;
    $.get(`${window.refinancingRoutes.loanBalanceBase}/${loanId}/saldo`, function (response) {
        setLoanBox(response.loan || {});
        calculateRefinancing();
    }).fail(showActionError);
}

function calculateRefinancing() {
    if (!$('#refLoanId').val() || !$('[name="interest_rate"]').val() || !$('[name="term_months"]').val() || !$('[name="start_date"]').val()) return;
    $.post(window.refinancingRoutes.calculate, $('#refinancingForm').serialize(), function (response) {
        $('#refNewAmountText').val(`S/ ${Number(response.new_amount || 0).toFixed(2)}`);
        updateSummary(response.summary_formatted || {});
    }).fail(function (xhr) {
        if (xhr.status === 422 && xhr.responseJSON?.errors) showRefErrors(xhr.responseJSON.errors);
    });
}

function setLoanBox(loan) {
    $('#refLoanCode').text(loan.loan_number || '-');
    $('#refLoanApproved').text(loan.approved_amount_formatted || 'S/ 0.00');
    $('#refLoanBalance').text(loan.current_balance_formatted || 'S/ 0.00');
    $('#refLoanPaidTotal').text(loan.total_paid_formatted || 'S/ 0.00');
    $('#refLoanPendingTotal').text(loan.total_pending_formatted || 'S/ 0.00');
    $('#refLoanPaidInstallments').text(loan.paid_installments || 0);
    $('#refLoanPendingInstallments').text(loan.pending_installments || 0);
    $('#refLoanOverdueInstallments').text(loan.overdue_installments || 0);
    $('#refLoanOldestOverdue').text(loan.oldest_overdue_date || '-');
    $('#refLoanStatus').text(loan.status_label || '-');
    $('#refPreviousBalanceText').val(loan.current_balance_formatted || 'S/ 0.00');
    $('#refNewAmountText').val(loan.current_balance_formatted || 'S/ 0.00');
    $('#refOverdueAlert').toggleClass('d-none', !loan.has_overdue);
}

function updateSummary(summary) {
    $('#refFixedPrincipal').text(summary.fixed_principal || 'S/ 0.00');
    $('#refTotalInterest').text(summary.total_interest || 'S/ 0.00');
    $('#refTotalPayment').text(summary.total_payment || 'S/ 0.00');
    $('#refFirstLast').text(`${summary.first_installment || 'S/ 0.00'} / ${summary.last_installment || 'S/ 0.00'}`);
}

function fillRefDetail(ref) {
    $('#detailRefCode').text(ref.code || '-');
    $('#detailRefMember').text(ref.member_name || '-');
    $('#detailRefStatus').html(statusBadge(ref.status, ref.status_label));
    $('#detailRefOriginal').text(ref.original_loan_number || '-');
    $('#detailRefNew').text(ref.new_loan_number || '-');
    $('#detailRefPrevious').text(ref.previous_balance_formatted || '-');
    $('#detailRefAmount').text(ref.new_amount_formatted || '-');
    $('#detailRefDate').text(ref.refinancing_date_formatted || '-');
    $('#detailRefDni').text(ref.member_dni || '-');
    $('#detailRefAdditional').text(ref.additional_amount_formatted || '-');
    $('#detailRefRate').text(ref.interest_rate_formatted || '-');
    $('#detailRefTerm').text(ref.term_months_formatted || '-');
    $('#detailRefInterest').text(ref.total_interest_formatted || '-');
    $('#detailRefTotal').text(ref.total_amount_formatted || '-');
    $('#detailRefUser').text(ref.created_by_name || '-');
    $('#detailRefCreated').text(ref.created_at || '-');
    $('#detailRefReason').text(ref.reason || '-');
    $('#detailRefObservation').text(ref.observation || '-');
    const rows = (ref.closed_installments || []).map((row) => `<tr><td>${escapeHtml(row.id)}</td><td>S/ ${Number(row.paid_amount || 0).toFixed(2)}</td><td>S/ ${Number(row.remaining_amount || 0).toFixed(2)}</td><td>${escapeHtml(row.status)}</td></tr>`).join('');
    $('#detailRefClosedRows').html(rows || '<tr><td colspan="4">Sin cuotas cerradas.</td></tr>');
}

function fillRefSchedule(ref, installments) {
    $('#scheduleRefCode').text(ref.code || '-');
    $('#scheduleRefLoan').text(ref.new_loan_number || '-');
    $('#scheduleRefStatus').html(statusBadge(ref.status, ref.status_label));
    $('#refScheduleRows').html(installments.map((row) => `<tr><td>${escapeHtml(row.installment_number)}</td><td>${escapeHtml(row.due_date)}</td><td>${escapeHtml(row.opening_balance)}</td><td>${escapeHtml(row.principal_amount)}</td><td>${escapeHtml(row.interest_amount)}</td><td>${escapeHtml(row.installment_amount)}</td><td>${escapeHtml(row.closing_balance)}</td><td>${escapeHtml(row.status_label)}</td></tr>`).join('') || '<tr><td colspan="8">Sin cuotas.</td></tr>');
}

function loadRefSummary() {
    $.get(window.refinancingRoutes.summary, function (summary) {
        $('#refSummaryTotal').text(`S/ ${summary.total || '0.00'}`);
        $('#refSummaryRegistered').text(summary.registered || '0');
        $('#refSummaryLoans').text(summary.refinanced_loans || '0');
        $('#refSummaryMonth').text(`S/ ${summary.month || '0.00'}`);
    });
}

function handleRefAjaxError(xhr) { setLoading(false); if (xhr.status === 422 && xhr.responseJSON?.errors) return showRefErrors(xhr.responseJSON.errors); showActionError(xhr); }
function showRefErrors(errors) { clearRefErrors(); let list = '<ul class="mb-0">'; $.each(errors, function (key, messages) { list += `<li>${messages[0]}</li>`; $(`#refinancingForm [name="${key}"]`).addClass('is-invalid'); }); list += '</ul>'; $('#ref-error-messages').removeClass('d-none').html(list); }
function clearRefErrors() { $('#ref-error-messages').addClass('d-none').empty(); $('#refinancingForm .is-invalid').removeClass('is-invalid'); }
function statusBadge(status, label) { return `<span class="badge badge-${status === 'anulado' ? 'danger' : 'success'}">${escapeHtml(label || '-')}</span>`; }
function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function toast(message, icon) { Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
function showActionError(xhr) { setLoading(false); Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error'); }
function debounce(callback, wait) { let timeout; return function () { clearTimeout(timeout); timeout = setTimeout(callback, wait); }; }
function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
