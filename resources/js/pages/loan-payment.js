var divLoading = document.getElementById('divLoading');
let tableLoanPayment;
let currentPaymentLoan = {};
let paymentVoucherPreviewUrl = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableLoanPayment = $('#tableLoanPayment').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.loanPaymentRoutes.list,
            data: function (data) {
                data.date_from = $('#payment_filter_date_from').val();
                data.date_to = $('#payment_filter_date_to').val();
                data.member_id = $('#payment_filter_member_id').val();
                data.payment_type = $('#payment_filter_type').val();
                data.status = $('#payment_filter_status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'payment_number', name: 'payment_number' },
            { data: 'payment_date', name: 'payment_date' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'member_dni', name: 'member.dni' },
            { data: 'loan_number', name: 'loan.loan_number' },
            { data: 'payment_type', name: 'payment_type' },
            { data: 'payment_method', name: 'payment_method' },
            { data: 'amount', name: 'amount' },
            { data: 'historical', name: 'is_historical', orderable: false, searchable: false },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadPaymentSummary();
    tableLoanPayment.on('draw', loadPaymentSummary);
    openPaymentFromQueryString();

    $('#btnNewLoanPayment').on('click', function () {
        resetPaymentForm();
        $('#loanPaymentModal').modal('show');
    });

    $('#loanPaymentModal').on('hidden.bs.modal', resetPaymentForm);
    $('#payment_filter_date_from, #payment_filter_date_to, #payment_filter_member_id, #payment_filter_type, #payment_filter_status').on('change', function () { tableLoanPayment.ajax.reload(); });
    $('#btnClearPaymentFilters').on('click', function () { $('#payment_filter_date_from, #payment_filter_date_to, #payment_filter_member_id, #payment_filter_type, #payment_filter_status').val(''); tableLoanPayment.ajax.reload(); });

    $('#paymentMemberId').on('change', loadLoansByMember);
    $('#paymentLoanId').on('change', loadInstallmentsByLoan);
    $('[name="payment_type"]').on('change', updatePaymentTypeUi);
    $('[name="payment_method"]').on('change', updatePaymentReferenceRequirement);
    $(document).on('change', '.payment-installment-check', recalculatePaymentAmount);
    $('[name="amount"]').on('input', updatePaymentPreview);
    $('[name="payment_date"]').on('change', updateCreditTimingWarning);
    $('[name="payment_date"]').on('change', function(){ if($('#paymentLoanId').val()) loadInstallmentsByLoan(); });
    $('#historicalPayment').on('change', updateHistoricalPaymentUi);
    $('[name="late_fee_charged"], [name="late_fee_exonerated"]').on('input', recalculatePaymentAmount);
    $('#waiveLateFee').on('change', function(){ $('#lateFeeReasonBox').toggleClass('d-none', !this.checked); $('[name="late_fee_reason"]').prop('required', this.checked); recalculatePaymentAmount(); });
    $('#paymentVoucher').on('change', function () { renderPaymentVoucher(this.files?.[0]); });
    $(document).on('click', '#removePaymentVoucher', resetPaymentVoucher);

    $('#loanPaymentForm').on('submit', function (event) {
        event.preventDefault();
        clearPaymentErrors();
        if ($('[name="payment_type"]').val() === 'cuota') {
            const summary = selectedPaymentSummary();
            if (summary.count > 0 && summary.invalid) {
                Swal.fire('Error', 'No se pudo calcular el detalle del cobro. Revise capital, interés y mora antes de guardar.', 'error');
                return;
            }
            const historicalFee = $('#historicalPayment').is(':checked') ? Number($('[name="late_fee_charged"]').val() || 0) : summary.lateFee;
            $('[name="amount"]').val((summary.capital + summary.interest + historicalFee).toFixed(2));
        }
        setLoading(true);
        const id = $(this).attr('data-id');
        const formData = new FormData(this);
        let url = window.loanPaymentRoutes.store;
        if (id) {
            url = `${window.loanPaymentRoutes.base}/${id}`;
            formData.append('_method', 'PUT');
        }
        $.ajax({
            url, type: 'POST', data: formData, processData: false, contentType: false,
            success: function (response) {
                setLoading(false);
                $('#loanPaymentModal').modal('hide');
                tableLoanPayment.ajax.reload(null, false);
                loadPaymentSummary();
                toast(response.message, 'success');
            },
            error: handlePaymentAjaxError
        });
    });

    $(document).on('click', '.showLoanPayment', function () {
        setLoading(true);
        $.get(`${window.loanPaymentRoutes.base}/${$(this).data('id')}`, function (payment) {
            setLoading(false);
            fillPaymentDetail(payment);
            $('#loanPaymentDetailModal').modal('show');
        }).fail(showActionError);
    });

    $(document).on('click', '.editLoanPayment', function () {
        setLoading(true);
        $.get(`${window.loanPaymentRoutes.base}/${$(this).data('id')}/edit`, function (payment) {
            setLoading(false);
            resetPaymentForm();
            $('#loanPaymentForm').attr('data-id', payment.id);
            $('#loanPaymentModalLabel').text('Editar cobro');
            $('#loanPaymentSaveText').text('Actualizar cobro');
            $('[name="payment_number"]').val(payment.payment_number);
            $('[name="payment_date"]').val(payment.payment_date).prop('disabled', true);
            $('#paymentMemberId').html(`<option value="${payment.member_id}">${escapeHtml(payment.member_name || '-')}</option>`).prop('disabled', true);
            $('#paymentLoanId').html(`<option value="${payment.loan_id}">${escapeHtml(payment.loan_number || '-')}</option>`).prop('disabled', true);
            $('[name="payment_type"], [name="amount"], .payment-installment-check').prop('disabled', true);
            $('[name="payment_method"]').val(payment.payment_method).prop('disabled', true);
            $('[name="payment_reference"]').val(payment.payment_reference || '');
            $('[name="observation"]').val(payment.observation || '');
            $('#historicalPayment').prop('checked', payment.is_historical).prop('disabled', true);
            $('#historicalAffectsCash').prop('checked', payment.affects_cash).prop('disabled', true);
            $('#historicalAffectsProfit').prop('checked', payment.affects_profit).prop('disabled', true);
            $('#historicalAffectsCredit').prop('checked', payment.affects_credit_history).prop('disabled', true);
            $('[name="profit_treatment"]').val(payment.profit_treatment || 'eligible').prop('disabled', !payment.is_historical || !payment.affects_profit);
            updateHistoricalPaymentUi();
            $('#paymentInstallmentWrap').hide();
            $('#loanPaymentModal').modal('show');
        }).fail(showActionError);
    });

    $(document).on('click', '.annulLoanPayment', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Anular cobro',
            text: '¿Esta seguro de anular este cobro? Esta accion revertira el saldo del prestamo y el movimiento de caja.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(`${window.loanPaymentRoutes.base}/${id}/anular`, function (response) {
                tableLoanPayment.ajax.reload(null, false);
                loadPaymentSummary();
                toast(response.message, 'success');
            }).fail(showActionError);
        });
    });
});

function openPaymentFromQueryString() {
    const paymentId = new URLSearchParams(window.location.search).get('payment_id');
    if (!paymentId) return;
    setLoading(true);
    $.get(`${window.loanPaymentRoutes.base}/${paymentId}`, function (payment) {
        setLoading(false); fillPaymentDetail(payment); $('#loanPaymentDetailModal').modal('show');
    }).fail(showActionError);
}

function resetPaymentForm() {
    const form = $('#loanPaymentForm');
    form[0].reset();
    form.removeAttr('data-id');
    clearPaymentErrors();
    $('#loanPaymentModalLabel').text('Nuevo cobro');
    $('#loanPaymentSaveText').text('Guardar cobro');
    $('[name="payment_number"]').val(window.loanPaymentRoutes.nextCodeValue || 'COB-000001');
    $('[name="payment_date"]').val(new Date().toISOString().slice(0, 10)).prop('disabled', false);
    $('#historicalPayment').prop('checked', false).prop('disabled', false);
    $('#historicalAffectsCash').prop('checked', false).prop('disabled', false);
    $('#historicalAffectsProfit, #historicalAffectsCredit').prop('checked', true).prop('disabled', false);
    $('[name="late_fee_charged"], [name="late_fee_exonerated"]').val('0');
    $('[name="late_fee_override_reason"]').val('');
    $('[name="profit_treatment"]').val('eligible').prop('disabled', false);
    updateHistoricalPaymentUi();
    $('#paymentMemberId').prop('disabled', false);
    $('#paymentLoanId').html('<option value="">Seleccione socio</option>').prop('disabled', false);
    $('[name="payment_type"], [name="amount"], [name="payment_method"]').prop('disabled', false);
    $('#paymentInstallmentRows').html('<tr><td colspan="9">Seleccione un prestamo.</td></tr>');
    $('#paymentInstallmentWrap').show();
    resetPaymentVoucher();
    setLoanBox({});
    updatePaymentTypeUi();
    updatePaymentReferenceRequirement();
    $.get(window.loanPaymentRoutes.nextCode, function (response) { $('[name="payment_number"]').val(response.code || 'COB-000001'); });
}

function loadLoansByMember() {
    const memberId = $('#paymentMemberId').val();
    $('#paymentLoanId').html('<option value="">Seleccione</option>');
    if (!memberId) return;
    $.get(`${window.loanPaymentRoutes.memberLoansBase}/${memberId}/prestamos`, function (loans) {
        const options = loans.map((loan) => `<option value="${loan.id}">${escapeHtml(loan.loan_number)} - ${escapeHtml(loan.current_balance_formatted)}</option>`).join('');
        $('#paymentLoanId').html(`<option value="">Seleccione</option>${options}`);
    });
}

function loadInstallmentsByLoan() {
    const loanId = $('#paymentLoanId').val();
    if (!loanId) return;
    $.get(`${window.loanPaymentRoutes.loanInstallmentsBase}/${loanId}/cuotas`, {payment_date: $('[name="payment_date"]').val()}, function (response) {
        setLoanBox(response.loan || {});
        const rows = (response.installments || []).map((row) => `
            <tr>
                <td><input type="checkbox" class="payment-installment-check" name="installment_ids[]" value="${row.id}" data-due-date="${escapeHtml(row.due_date_iso || '')}" data-pending="${row.remaining_amount}" data-capital="${row.principal_pending}" data-principal="${row.principal_pending}" data-interest="${row.interest_pending}" data-late-fee="${row.late_fee}" data-total="${row.total_due}" data-future="${row.is_future ? 1 : 0}" data-allow-waiver="${row.allow_waiver ? 1 : 0}" data-grace-days="${row.grace_days || 0}" data-late-days="${row.late_days || 0}"></td>
                <td>${escapeHtml(row.installment_number)}</td><td>${escapeHtml(row.due_date)}</td><td>${escapeHtml(row.principal_amount)}</td><td>${escapeHtml(row.interest_amount)}</td><td>${escapeHtml(row.remaining_amount_formatted)}</td><td>${escapeHtml(row.late_days)}</td><td>${escapeHtml(row.late_fee_formatted)}</td><td>S/ ${escapeHtml(row.total_due)}</td><td>${escapeHtml(row.status_label)}</td>
            </tr>
        `).join('');
        $('#paymentInstallmentRows').html(rows || '<tr><td colspan="10">Sin cuotas pendientes.</td></tr>');
        updatePaymentTypeUi();
    });
}

function updatePaymentTypeUi() {
    const type = $('[name="payment_type"]').val();
    const balance = parseFloat($('#paymentLoanBalance').data('balance')) || 0;
    const manualAmount = ['parcial', 'abono_capital'].includes(type);
    $('[name="amount"]').prop('readonly', !manualAmount).toggleClass('payment-amount-calculated', !manualAmount);
    $('#paymentInstallmentWrap').toggle(!['abono_capital', 'liquidacion'].includes(type));
    $('.payment-installment-check').prop('disabled', type === 'abono_capital' || type === 'liquidacion');
    if (type === 'adelanto_cuotas') {
        $('.payment-installment-check').each(function () { $(this).prop('checked', false).prop('disabled', $(this).data('future') !== 1); });
    }
    if (type === 'liquidacion') $('[name="amount"]').val(Number(currentPaymentLoan.liquidation_amount || 0).toFixed(2));
    if (type === 'parcial') { $('.payment-installment-check').prop('checked', false); $('[name="amount"]').val(''); }
    if (type === 'abono_capital') $('[name="amount"]').val('');
    recalculatePaymentAmount();
}

function recalculatePaymentAmount() {
    const type = $('[name="payment_type"]').val();
    if (!['cuota', 'adelanto_cuotas'].includes(type)) return updatePaymentPreview();
    const summary = selectedPaymentSummary();
    $('#historicalLateFeeCalculated').val(`S/ ${summary.originalLateFee.toFixed(2)}`);
    const historicalFee = $('#historicalPayment').is(':checked') ? Number($('[name="late_fee_charged"]').val() || 0) : summary.lateFee;
    const total = type === 'adelanto_cuotas' ? summary.capital : summary.capital + summary.interest + historicalFee;
    if (total > 0) $('[name="amount"]').val(total.toFixed(2));
    else $('[name="amount"]').val('');
    updatePaymentPreview();
    const selected=$('.payment-installment-check:checked');
    const hasFee=selected.toArray().some(el => Number($(el).data('late-fee')||0)>0);
    const canWaive=selected.toArray().every(el => Number($(el).data('allow-waiver')||0)===1);
    $('#lateFeeWaiverBox').toggleClass('d-none', !hasFee || !canWaive);
}

function updatePaymentReferenceRequirement() {
    const method = $('[name="payment_method"]').val();
    const required = ['yape', 'plin', 'transferencia', 'cheque'].includes(method);
    const visible = required || method === 'otro';
    $('#paymentReferenceGroup').toggleClass('d-none', !visible);
    $('#paymentReferenceRequired').toggleClass('d-none', !required);
    $('[name="payment_reference"]').prop('required', required);
    if (!visible) $('[name="payment_reference"]').val('');
}

function resetPaymentVoucher() {
    if (paymentVoucherPreviewUrl) URL.revokeObjectURL(paymentVoucherPreviewUrl);
    paymentVoucherPreviewUrl = null;
    $('#paymentVoucher').val('');
    $('#paymentVoucherName').text('Seleccionar comprobante');
    $('#paymentVoucherPreview').addClass('is-empty').html('<div class="loan-voucher-placeholder"><i class="far fa-file-alt"></i><strong>Sin comprobante seleccionado</strong><span>La vista previa aparecera aqui.</span></div>');
}

function renderPaymentVoucher(file) {
    if (!file) return resetPaymentVoucher();
    if (paymentVoucherPreviewUrl) URL.revokeObjectURL(paymentVoucherPreviewUrl);
    paymentVoucherPreviewUrl = URL.createObjectURL(file);
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    const visual = isPdf ? '<i class="fas fa-file-pdf loan-voucher-pdf-icon"></i>' : `<img src="${paymentVoucherPreviewUrl}" alt="Vista previa del comprobante">`;
    $('#paymentVoucherName').text(file.name);
    $('#paymentVoucherPreview').removeClass('is-empty').html(`${visual}<div class="loan-voucher-meta"><strong>${escapeHtml(file.name)}</strong><span>${(file.size / 1024 / 1024).toFixed(2)} MB</span><div><a href="${paymentVoucherPreviewUrl}" target="_blank" rel="noopener" class="btn btn-light border btn-sm"><i class="fas fa-eye mr-1"></i> Ver</a><label for="paymentVoucher" class="btn btn-light border btn-sm mb-0"><i class="fas fa-sync-alt mr-1"></i> Cambiar</label><button type="button" id="removePaymentVoucher" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt mr-1"></i> Quitar</button></div></div>`);
}

function setLoanBox(loan) {
    currentPaymentLoan = loan || {};
    $('#paymentLoanCode').text(loan.loan_number || '-');
    $('#paymentLoanBalance').text(loan.current_balance_formatted || 'S/ 0.00').data('balance', loan.current_balance || 0);
    $('#paymentLoanPaid').text(loan.total_paid_formatted || 'S/ 0.00');
    $('#paymentLoanStatus').text(loan.status_label || '-');
    updatePaymentPreview();
}

function updatePaymentPreview() {
    const type = $('[name="payment_type"]').val();
    const amount = parseFloat($('[name="amount"]').val()) || 0;
    let capital = 0, interest = 0, lateFee = 0, exonerated = 0, count = 0, scheduleReduction = 0;
    if (type === 'liquidacion') {
        capital = Number(currentPaymentLoan.capital_pending || 0); interest = Number(currentPaymentLoan.overdue_interest || 0); exonerated = Number(currentPaymentLoan.future_interest_exonerated || 0);
        scheduleReduction = Number(currentPaymentLoan.current_balance || 0);
    } else if (type === 'abono_capital') capital = amount;
    else {
        const selected = selectedPaymentSummary();
        capital = selected.capital;
        interest = type === 'adelanto_cuotas' ? 0 : selected.interest;
        lateFee = type === 'adelanto_cuotas' ? 0 : selected.originalLateFee;
        exonerated = type === 'adelanto_cuotas' ? selected.interest : 0;
        count = type === 'adelanto_cuotas' ? selected.count : 0;
        scheduleReduction = selected.pending;
        if (type === 'parcial') {
            scheduleReduction = Math.min(selected.pending, Math.max(0, amount - selected.lateFee));
            capital = amount; interest = 0;
        }
    }
    const waivedLateFee = $('#waiveLateFee').is(':checked') ? lateFee : 0;
    const calculatedTotal = capital + interest + lateFee - waivedLateFee;
    const total = ['cuota', 'adelanto_cuotas'].includes(type) ? calculatedTotal : amount;
    if (type === 'abono_capital') scheduleReduction = capital;
    const balance = Math.max(0, Number(currentPaymentLoan.current_balance || 0) - scheduleReduction);
    $('#paymentPreviewCapital').text(`S/ ${capital.toFixed(2)}`); $('#paymentPreviewInterest').text(`S/ ${interest.toFixed(2)}`); $('#paymentPreviewLateFee').text(`S/ ${(lateFee-waivedLateFee).toFixed(2)}`); $('#paymentPreviewLateFeeWaived').text(`S/ ${waivedLateFee.toFixed(2)}`); $('#paymentPreviewExonerated').text(`S/ ${exonerated.toFixed(2)}`); $('#paymentPreviewTotal').text(`S/ ${total.toFixed(2)}`); $('#paymentPreviewBalance').text(`S/ ${balance.toFixed(2)}`); $('#paymentPreviewCount').text(count);
    $('#paymentAdvanceWarning').toggleClass('d-none', type !== 'adelanto_cuotas' || !currentPaymentLoan.has_overdue_debt);
    updateCreditTimingWarning();
}

function paymentDataNumber(element, name) {
    const value = element.getAttribute(`data-${name}`);
    if (value === null || value === '') return 0;
    const normalized = String(value).replace(/[^0-9.-]/g, '');
    return Number.isFinite(Number(normalized)) ? Number(normalized) : 0;
}

function selectedPaymentSummary() {
    const waive = $('#waiveLateFee').is(':checked');
    const summary = {capital: 0, interest: 0, pending: 0, originalLateFee: 0, lateFee: 0, waivedLateFee: 0, total: 0, count: 0, invalid: false};
    document.querySelectorAll('.payment-installment-check:checked').forEach((checkbox) => {
        const capital = paymentDataNumber(checkbox, 'capital');
        const interest = paymentDataNumber(checkbox, 'interest');
        const pending = paymentDataNumber(checkbox, 'pending');
        const lateFee = paymentDataNumber(checkbox, 'late-fee');
        summary.capital += capital;
        summary.interest += interest;
        summary.pending += pending;
        summary.originalLateFee += lateFee;
        summary.count += 1;
        if (pending > 0 && capital + interest <= 0) summary.invalid = true;
    });
    summary.waivedLateFee = waive ? summary.originalLateFee : 0;
    summary.lateFee = waive ? 0 : summary.originalLateFee;
    summary.total = summary.capital + summary.interest + summary.lateFee;
    return summary;
}

function updateCreditTimingWarning() {
    const paymentDate = $('[name="payment_date"]').val();
    const selected = $('.payment-installment-check:checked').first();
    if (!paymentDate || !selected.length || ['abono_capital', 'liquidacion'].includes($('[name="payment_type"]').val())) return $('#paymentCreditWarning').addClass('d-none');
    const dueDate = selected.data('due-date');
    if (!dueDate) return $('#paymentCreditWarning').addClass('d-none');
    const days = Math.max(0, Math.round((new Date(`${paymentDate}T00:00:00`) - new Date(`${dueDate}T00:00:00`)) / 86400000));
    const graceDays = Number(selected.data('grace-days') || 0);
    const chargedDays = Number(selected.data('late-days') || 0);
    let level = 'puntual', klass = 'alert-success';
    if (chargedDays > 0 && chargedDays <= 3) { level = 'leve'; klass = 'alert-warning'; }
    else if (chargedDays > 3 && chargedDays <= 15) { level = 'moderado'; klass = 'alert-warning'; }
    else if (chargedDays > 15) { level = 'grave'; klass = 'alert-danger'; }
    const title = chargedDays > 0 ? `Pago con atraso ${level}` : (days > 0 ? 'Dentro del período de gracia' : 'Pago puntual');
    const text = `${days} día(s) desde el vencimiento, ${graceDays} día(s) de gracia y ${chargedDays} día(s) sujetos a mora.`;
    $('#paymentCreditWarning').removeClass('d-none alert-info alert-success alert-warning alert-danger').addClass(klass);
    $('#paymentCreditWarningTitle').text(title); $('#paymentCreditWarningText').text(text);
}

function fillPaymentDetail(payment) {
    $('#detailPaymentCode').text(payment.payment_number || '-');
    $('#detailPaymentMember').text(payment.member_name || '-');
    $('#detailPaymentStatus').html(statusBadge(payment.status, payment.status_label));
    $('#detailPaymentReceipt').text(payment.receipt_number || '-');
    $('#detailPaymentLoan').text(payment.loan_number || '-');
    $('#detailPaymentAmount').text(payment.amount_formatted || 'S/ 0.00');
    $('#detailPaymentCapital').text(payment.capital_amount_formatted || 'S/ 0.00');
    $('#detailPaymentInterest').text(payment.interest_amount_formatted || 'S/ 0.00');
    $('#detailPaymentLateFee').text(payment.late_fee_paid_formatted || 'S/ 0.00');
    $('#detailPaymentLateFeeWaived').text(payment.late_fee_waived_formatted || 'S/ 0.00');
    $('#detailPaymentPreviousLoanBalance').text(payment.previous_loan_balance_formatted || 'S/ 0.00');
    $('#detailPaymentNewLoanBalance').text(payment.new_loan_balance_formatted || 'S/ 0.00');
    $('#detailPaymentExonerated').text(payment.interest_exonerated_amount_formatted || 'S/ 0.00');
    $('#detailPaymentAdvancedCount').text(payment.installments_advanced_count || 0);
    $('#detailPaymentDate').text(payment.payment_date_formatted || '-');
    $('#detailPaymentRegisteredAt').text(payment.registered_at_formatted || '-');
    $('#detailPaymentHistorical').text(payment.historical_label || 'Normal').toggleClass('badge-info', payment.is_historical).toggleClass('badge-light', !payment.is_historical);
    $('#detailPaymentAffectsCash').text(payment.affects_cash ? 'Sí' : 'No');
    $('#detailPaymentAffectsProfit').text(payment.affects_profit ? 'Sí' : 'No');
    $('#detailPaymentProfitTreatment').text(payment.profit_treatment_label || '-');
    $('#detailPaymentAffectsCredit').text(payment.affects_credit_history ? 'Sí' : 'No');
    $('#detailPaymentStatusText').text(payment.status_label || '-');
    $('#detailPaymentDni').text(payment.member_dni || '-');
    $('#detailPaymentMemberCode').text(payment.member_code || '-');
    $('#detailPaymentType').text(payment.payment_type_label || '-');
    $('#detailPaymentMethod').text(payment.payment_method_label || '-');
    $('#detailPaymentReference').text(payment.payment_reference || '-');
    $('#detailPaymentUser').text(payment.created_by_name || '-');
    $('#detailPaymentObservation').text(payment.observation || '-');
    updateLink('#detailPaymentVoucher', payment.voucher_url, 'Ver comprobante', 'Sin comprobante');
    updateLink('#detailPaymentReceiptLink', payment.receipt_url, 'Ver recibo', 'Sin recibo');
    updateLink('#detailPaymentPrintLink', payment.receipt_url, 'Imprimir', 'Sin recibo');
    updateLink('#detailPaymentReceiptPdfLink', payment.receipt_pdf_url, 'PDF', 'PDF');
    const rows = (payment.details || []).map((row) => `<tr><td>${escapeHtml(row.installment_number)}</td><td>${escapeHtml(row.due_date)}</td><td>${escapeHtml(row.principal_paid)}</td><td>${escapeHtml(row.interest_paid)}</td><td>${escapeHtml(row.late_fee_days)}</td><td class="text-warning font-weight-bold">${escapeHtml(row.late_fee_paid)}</td><td>${escapeHtml(row.late_fee_waived)}</td><td class="font-weight-bold">${escapeHtml(row.amount_paid)}</td><td>${escapeHtml(row.status)}</td></tr>`).join('');
    $('#detailPaymentRows').html(rows || '<tr><td colspan="9">Sin detalle.</td></tr>');
}

function updateHistoricalPaymentUi() {
    const enabled = $('#historicalPayment').is(':checked');
    $('#historicalPaymentFields').toggleClass('d-none', !enabled);
    $('#paymentDateLabel').text(enabled ? 'Fecha real de pago' : 'Fecha pago');
    if (enabled) {
        $('#waiveLateFee').prop('checked', false);
        $('#lateFeeWaiverBox').addClass('d-none');
    }
    recalculatePaymentAmount();
}

function loadPaymentSummary() {
    $.get(window.loanPaymentRoutes.summary, function (summary) {
        $('#paymentSummaryTotal').text(`S/ ${summary.total || '0.00'}`);
        $('#paymentSummaryMonth').text(`S/ ${summary.month || '0.00'}`);
        $('#paymentSummaryToday').text(`S/ ${summary.today || '0.00'}`);
        $('#paymentSummaryLoans').text(summary.loans_with_balance || '0');
    });
}

function handlePaymentAjaxError(xhr) {
    setLoading(false);
    if (xhr.status === 422 && xhr.responseJSON?.errors) {
        showPaymentErrors(xhr.responseJSON.errors);
        return;
    }
    showActionError(xhr);
}

function showPaymentErrors(errors) {
    clearPaymentErrors();
    let list = '<ul class="mb-0">';
    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`#loanPaymentForm [name="${key}"], #loanPaymentForm [name="${key}[]"]`);
        input.addClass('is-invalid');
    });
    list += '</ul>';
    $('#loan-payment-error-messages').removeClass('d-none').html(list);
}

function clearPaymentErrors() {
    $('#loan-payment-error-messages').addClass('d-none').empty();
    $('#loanPaymentForm .is-invalid').removeClass('is-invalid');
}

function updateLink(selector, url, text, emptyText) {
    const link = $(selector);
    link.contents().filter(function () { return this.nodeType === 3; }).remove();
    if (url) link.attr('href', url).removeClass('disabled').append(` ${escapeHtml(text)}`);
    else link.attr('href', '#').addClass('disabled').append(` ${escapeHtml(emptyText)}`);
}

function statusBadge(status, label) {
    return `<span class="badge badge-${status === 'anulado' ? 'danger' : 'success'}">${escapeHtml(label || '-')}</span>`;
}
function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function toast(message, icon) { Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
function showActionError(xhr) { setLoading(false); Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error'); }
function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
