var divLoading = document.getElementById('divLoading');
let tableClosures;
let currentClosure = null;
let closureVoucherObjectUrl = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableClosures = $('#tableClosures').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: window.memberClosureRoutes.list, data: d => { d.date_from = $('#closure_filter_date_from').val(); d.date_to = $('#closure_filter_date_to').val(); d.member_id = $('#closure_filter_member').val(); d.status = $('#closure_filter_status').val(); d.settlement_type = $('#closure_filter_settlement').val(); } },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code' },
            { data: 'closure_date', name: 'closure_date' },
            { data: 'member_name', orderable: false, searchable: false },
            { data: 'member_dni', orderable: false, searchable: false },
            { data: 'total_in_favor', name: 'total_in_favor' },
            { data: 'total_against', name: 'total_against' },
            { data: 'final_balance', name: 'final_balance' },
            { data: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadClosureSummary();
    tableClosures.on('draw', function () {
        loadClosureSummary();
        initializeClosureTooltips();
    });
    $('#closure_filter_date_from, #closure_filter_date_to, #closure_filter_member, #closure_filter_status, #closure_filter_settlement').on('change input', debounce(() => tableClosures.ajax.reload(), 300));
    $('#btnClearClosureFilters').on('click', () => { $('#closure_filter_date_from, #closure_filter_date_to, #closure_filter_member, #closure_filter_status, #closure_filter_settlement').val(''); tableClosures.ajax.reload(); });
    $('#btnNewClosure').on('click', openNewClosure);
    $('#btnCalculateClosure').on('click', calculateClosure);
    $('#closureMemberId').on('change', calculateClosure);
    $('#closureForm [name="utility_mode"], #closureForm [name="retirement_date"]').on('change', calculateClosure);
    $('#closureForm').on('submit', submitClosureForm);
    $('#closureCloseForm').on('submit', submitCloseForm);
    $('#closureCloseForm [name="payment_method"]').on('change', updateReferenceRequirement);
    $('#closureVoucher').on('change', updateVoucherPreview);
    $(document).on('click', '.showClosure', function () { loadClosureDetail($(this).data('id')); });
    $(document).on('click', '.editClosure', function () { loadClosureForm($(this).data('id')); });
    $(document).on('click', '.closeClosure', function () { openCloseModal($(this).data('id')); });
    $(document).on('click', '.annulClosure', function () { annulClosure($(this).data('id')); });
});

function initializeClosureTooltips() {
    $('[data-toggle="tooltip"]').tooltip({ container: 'body', trigger: 'hover focus' });
}

function openNewClosure() {
    resetClosureForm();
    fetchNextClosureCode();
    $('#closureModal').modal('show');
}

function resetClosureForm() {
    $('#closureForm')[0].reset();
    $('#closureForm').removeAttr('data-id');
    clearErrors('#closureForm', '#closure-error-messages');
    $('#closureModalLabel').text('Nuevo cierre de cuenta');
    $('#closureSaveText').text('Guardar calculo');
    $('#closureCalculateText').text('Calcular');
    $('#closureMemberSelectGroup').removeClass('d-none');
    $('#closureMemberReadonlyGroup, #closurePendingBanner').addClass('d-none');
    $('#closureMemberId').prop('disabled', false).prop('required', true);
    $('#closureForm [name="code"]').val(window.memberClosureRoutes.nextCodeValue || 'CIE-000001');
    $('#closureForm [name="closure_date"], #closureForm [name="retirement_date"]').val(new Date().toISOString().slice(0, 10));
    $('#closureStatusLabel').val('Calculado');
    fillCalculation({});
}

function fetchNextClosureCode() {
    $.get(window.memberClosureRoutes.nextCode, r => $('#closureForm [name="code"]').val(r.code || 'CIE-000001'));
}

function calculateClosure() {
    clearErrors('#closureForm', '#closure-error-messages');
    if ($('#closureForm').attr('data-id')) {
        $('#closureForm').trigger('submit');
        return;
    }
    const memberId = $('#closureMemberId').val();
    if (!memberId) {
        fillCalculation({});
        return;
    }
    $.post(window.memberClosureRoutes.calculate, { member_id: memberId, retirement_date: $('#closureForm [name="retirement_date"]').val(), utility_mode: $('#closureForm [name="utility_mode"]').val() }, fillCalculation).fail(xhr => {
        if (xhr.status === 422) showErrors('#closureForm', '#closure-error-messages', xhr.responseJSON.errors || {});
        else showActionError(xhr);
    });
}

function fillCalculation(payload) {
    const m = payload.member || {};
    const s = payload.summary || {};
    $('#closureMemberCode').text(m.code || '-');
    $('#closureMemberDni').text(m.dni || '-');
    $('#closureMemberName').text(m.full_name || '-');
    $('#closureMemberAdmission').text(m.admission_date_formatted || '-');
    $('#closureMemberTime').text(m.membership_time || '-');
    $('#closureMemberStatus').text(m.status_label || '-');
    $('#closureCalcShares').text(s.total_shares || '0');
    $('#closureCalcContributions').text(s.total_contributions_formatted || 'S/ 0.00');
    $('#closureCalcLoansCount').text(s.active_loans_count || '0');
    $('#closureCalcLoans').text(s.pending_loans_amount_formatted || 'S/ 0.00');
    $('#closureCalcUtilities').text(s.pending_utilities_amount_formatted || 'S/ 0.00');
    $('#closureCalcUtilitiesNote').text(s.utilities_note || 'Sin utilidades pendientes. Las utilidades se calculan desde el mes siguiente del aporte y segun los cierres de utilidad registrados.');
    $('#closureUtilityStatus').text(s.utility_status_label || 'No calculada').attr('class', `badge badge-${s.utility_status === 'liquidada' ? 'success' : (s.utility_status === 'provisional' ? 'info' : 'warning')}`);
    $('#closureUtilityActions').text(Number(s.utility_actions_considered || 0).toFixed(4));
    $('#closureUtilityMonths').text(s.utility_productive_months || 0);
    $('#closureUtilityActionMonth').text(Number(s.utility_action_month || 0).toFixed(4));
    $('#closureUtilityAvailable').text(s.utility_available_formatted || 'S/ 0.00');
    $('#closureUtilityEstimated').text(s.utility_estimated_formatted || 'S/ 0.00');
    $('#closureUtilityPaidNow').text(s.utility_paid_now_formatted || 'S/ 0.00');
    $('#closureUtilityPending').text(s.utility_pending_annual_formatted || 'S/ 0.00');
    $('#closureUtilityNote').text(s.utility_note || 'Seleccione un socio y calcule.');
    $('#closureCalcFavor').text(s.total_in_favor_formatted || 'S/ 0.00');
    $('#closureCalcAgainst').text(s.total_against_formatted || 'S/ 0.00');
    $('#closureCalcFinal').text(s.final_balance_formatted || 'S/ 0.00');
    $('#closureCalcRows').html(rowsHtml(payload.details || []));
}

function rowsHtml(rows) {
    return rows.map(row => {
        const utilityInfo = row.item_type === 'utilidad_pendiente'
            ? `<div class="closure-utility-meta"><span>Periodo: <strong>${escapeHtml(row.utility_period || '-')}</strong></span><span>Meses considerados: <strong>${escapeHtml(row.utility_months || '-')}</strong></span><span>Acción-mes: <strong>${escapeHtml(row.utility_shares || '0')}</strong></span><span>Utilidad por acción-mes: <strong>${escapeHtml(row.utility_profit_per_share_formatted || 'S/ 0.00000000')}</strong></span><span>Utilidad pendiente: <strong>${escapeHtml(row.amount_formatted)}</strong></span></div>`
            : '';
        const origin = `<strong>${escapeHtml(row.origin_label || 'Registro interno')}</strong>${row.origin_code ? `<small class="d-block text-muted">${escapeHtml(row.origin_code)}</small>` : ''}`;
        return `<tr><td>${escapeHtml(row.item_type_label)}</td><td>${escapeHtml(row.description)}${utilityInfo}</td><td>${escapeHtml(row.favor_amount_formatted)}</td><td>${escapeHtml(row.against_amount_formatted)}</td><td>${origin}</td></tr>`;
    }).join('') || '<tr><td colspan="5">Sin conceptos.</td></tr>';
}

function closureDetailRowsHtml(rows) {
    return rows.map(row => {
        const utilityInfo = row.item_type === 'utilidad_pendiente'
            ? `<div class="closure-utility-meta"><span>Periodo: <strong>${escapeHtml(row.utility_period || 'No aplica')}</strong></span><span>Meses: <strong>${escapeHtml(row.utility_months || 'No aplica')}</strong></span><span>Acción-mes: <strong>${escapeHtml(row.utility_shares || '0')}</strong></span><span>Utilidad por acción-mes: <strong>${escapeHtml(row.utility_profit_per_share_formatted || 'S/ 0.00000000')}</strong></span></div>`
            : '';
        return `<tr><td><strong>${escapeHtml(row.item_type_label || 'Otros')}</strong></td><td>${escapeHtml(row.description || 'No aplica')}${utilityInfo}</td><td class="text-right text-success">${escapeHtml(row.favor_amount_formatted || 'No aplica')}</td><td class="text-right text-danger">${escapeHtml(row.against_amount_formatted || 'No aplica')}</td><td>${escapeHtml(row.origin_label || 'Otros conceptos')}</td><td>${escapeHtml(row.origin_code || 'No aplica')}</td></tr>`;
    }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">Sin conceptos registrados.</td></tr>';
}

function submitClosureForm(e) {
    e.preventDefault();
    clearErrors('#closureForm', '#closure-error-messages');
    setLoading(true);
    const form = $('#closureForm');
    const id = form.attr('data-id');
    let data = form.serializeArray();
    let url = window.memberClosureRoutes.store;
    if (id) {
        url = `${window.memberClosureRoutes.base}/${id}`;
        data.push({ name: '_method', value: 'PUT' });
    }
    $.post(url, $.param(data), r => {
        setLoading(false);
        $('#closureModal').modal('hide');
        tableClosures.ajax.reload(null, false);
        loadClosureSummary();
        toast(r.message, 'success');
    }).fail(xhr => {
        setLoading(false);
        if (xhr.status === 422) showErrors('#closureForm', '#closure-error-messages', xhr.responseJSON.errors || {});
        else showActionError(xhr);
    });
}

function loadClosureForm(id) {
    $.get(`${window.memberClosureRoutes.base}/${id}/edit`, d => {
        resetClosureForm();
        $('#closureForm').attr('data-id', d.id);
        $('#closureModalLabel').text(d.status === 'pendiente_regularizacion' ? 'Editar / Recalcular cierre pendiente' : 'Editar cierre de cuenta');
        $('#closureSaveText').text('Guardar cambios y recalcular');
        $('#closureCalculateText').text('Recalcular');
        setValue('#closureForm', 'code', d.code);
        setValue('#closureForm', 'closure_date', d.closure_date);
        setValue('#closureForm', 'retirement_date', d.retirement_date);
        setValue('#closureForm', 'reason', d.reason);
        setValue('#closureForm', 'observation', d.observation);
        setValue('#closureForm', 'utility_mode', d.utility_mode || 'pending');
        $('#closureMemberSelectGroup').addClass('d-none');
        $('#closureMemberReadonlyGroup').removeClass('d-none');
        $('#closureMemberId').prop('disabled', true).prop('required', false).val('');
        $('#closureMemberReadonly').text(`${d.member?.code || '-'} · ${d.member?.dni || '-'} · ${d.member?.full_name || '-'}`);
        $('#closureStatusLabel').val(d.status_label || '-');
        const pending = d.status === 'pendiente_regularizacion' || d.confirmation_scenario === 'saldo_en_contra';
        $('#closurePendingBanner').toggleClass('d-none', !pending);
        $('#closurePendingBalance').text(d.final_balance_formatted || 'S/ 0.00');
        fillCalculation({ member: d.member, summary: d, details: d.details });
        $('#closureModal').modal('show');
    }).fail(showActionError);
}

function loadClosureDetail(id, show = true) {
    setLoading(true);
    $.get(`${window.memberClosureRoutes.base}/${id}`, d => {
        setLoading(false);
        currentClosure = d;
        fillClosureDetail(d);
        if (show) $('#closureDetailModal').modal('show');
    }).fail(showActionError);
}

function fillClosureDetailLegacy(d) {
    $('#detailClosureCode').text(d.code || '-');
    $('#detailClosureStatus').html(statusBadge(d.status, d.status_label));
    $('#detailClosureMember').text(d.member?.full_name || '-');
    $('#detailClosureDni').text(d.member?.dni || '-');
    $('#detailClosureMemberCode').text(d.member?.code || '-');
    $('#detailClosureAdmission').text(d.member?.admission_date_formatted || '-');
    $('#detailClosureRetirement').text(d.retirement_date_formatted || '-');
    $('#detailClosureDate').text(d.closure_date_formatted || '-');
    $('#detailClosureShares').text(d.total_shares || '0');
    $('#detailClosureContributions').text(d.total_contributions_formatted || 'S/ 0.00');
    $('#detailClosureLoans').text(d.pending_loans_amount_formatted || 'S/ 0.00');
    $('#detailClosureUtilities').text(d.pending_utilities_amount_formatted || 'S/ 0.00');
    $('#detailClosureUtilitiesNote').text(d.utilities_note || 'Sin utilidades pendientes. Las utilidades se calculan desde el mes siguiente del aporte y segun los cierres de utilidad registrados.');
    fillClosureUtilityDetail(d);
    $('#detailClosureFavor').text(d.total_in_favor_formatted || 'S/ 0.00');
    $('#detailClosureAgainst').text(d.total_against_formatted || 'S/ 0.00');
    $('#detailClosureFinal').text(d.final_balance_formatted || 'S/ 0.00');
    $('#detailClosureSettlement').text(d.settlement_label || '-');
    $('#detailClosurePayment').text(d.payment_method_label || '-');
    $('#detailClosureReference').text(d.payment_reference || '-');
    $('#detailClosureCreatedBy').text(d.created_by_name || '-');
    $('#detailClosureCreatedAt').text(d.created_at || '-');
    $('#detailClosureClosedBy').text(d.closed_by_name || '-');
    $('#detailClosureClosedAt').text(d.closed_at || '-');
    $('#detailClosureAnnulledBy').text(d.annulled_by_name || '-');
    $('#detailClosureAnnulledAt').text(d.annulled_at || '-');
    $('#detailClosureAnnulmentReason').text(d.annulment_reason || '-');
    $('#detailClosureReason').text(d.reason || '-');
    $('#detailClosureObservation').text(d.observation || '-');
    $('#detailClosureRows').html(rowsHtml(d.details || []));
    $('#detailClosureReport').attr('href', d.report_url || '#');
    $('#detailClosurePdf').attr('href', d.pdf_url || '#').html(d.status !== 'cerrado' ? '<i class="fas fa-file-pdf"></i> Cálculo preliminar' : '<i class="fas fa-file-pdf"></i> Constancia PDF');
    toggleLink('#detailClosureReceipt', d.receipt_url);
    toggleLink('#detailClosureVoucher', d.voucher_download_url);
}

function fillClosureDetail(d) {
    const noApply = 'No aplica';
    $('#detailClosureCode').text(d.code || noApply);
    $('#detailClosureStatus').html(statusBadge(d.status, d.status_label));
    $('#detailClosureMember').text(d.member?.full_name || noApply);
    $('#detailClosureDni').text(d.member?.dni || noApply);
    $('#detailClosureMemberCode').text(d.member?.code || noApply);
    $('#detailClosureAdmission').text(d.member?.admission_date_formatted || noApply);
    $('#detailClosureRetirement').text(d.retirement_date_formatted || noApply);
    $('#detailClosureDate').text(d.closure_date_formatted || noApply);
    $('#detailClosureShares').text(d.total_shares || '0');
    $('#detailClosureContributions').text(d.total_contributions_formatted || 'S/ 0.00');
    $('#detailClosureLoans').text(d.pending_loans_amount_formatted || 'S/ 0.00');
    $('#detailClosureUtilities').text(d.pending_utilities_amount_formatted || 'S/ 0.00');
    $('#detailClosureUtilitiesNote').text(d.utilities_note || 'Sin utilidades pendientes.');
    fillClosureUtilityDetail(d);
    $('#detailClosureFavor').text(d.total_in_favor_formatted || 'S/ 0.00');
    $('#detailClosureAgainst').text(d.total_against_formatted || 'S/ 0.00');
    $('#detailClosureFinal').text(d.final_balance_formatted || 'S/ 0.00');
    $('#detailClosureSettlement').text(d.settlement_label || noApply);
    $('#detailClosurePayment').text(d.payment_method_label && d.payment_method_label !== '-' ? d.payment_method_label : noApply);
    $('#detailClosureReference').text(d.payment_method === 'efectivo' ? 'Referencia: No aplica' : `Referencia: ${d.payment_reference || noApply}`);
    $('#detailClosureCreatedBy').text(d.calculated_by_name || noApply);
    $('#detailClosureCreatedAt').text(d.calculated_at || noApply);
    $('#detailClosureClosedBy').text(d.confirmed_by_name || noApply);
    $('#detailClosureClosedAt').text(d.confirmed_at || noApply);
    $('#detailClosureReason').text(d.reason || noApply);
    $('#detailClosureObservation').text(d.observation || noApply);
    $('#detailClosureRows').html(closureDetailRowsHtml(d.details || []));

    const alertClass = d.status === 'anulado' ? 'alert-danger' : (d.confirmation_scenario === 'saldo_en_contra' ? 'alert-warning' : (d.status === 'cerrado' ? 'alert-success' : 'alert-info'));
    const alertIcon = d.status === 'cerrado' ? 'fa-check-circle' : (d.status === 'anulado' ? 'fa-ban' : 'fa-info-circle');
    $('#detailClosureAlert').removeClass('alert-danger alert-warning alert-success alert-info').addClass(alertClass).html(`<i class="fas ${alertIcon} mr-1"></i> ${escapeHtml(d.status_message || noApply)}`);
    $('#detailClosureFinalCard').removeClass('favor contra zero').addClass(d.result_tone || 'zero');

    $('#detailClosureReceiptState').text(d.receipt_generated ? 'Sí, generado' : noApply);
    $('#detailClosureReceiptNumber').text(d.receipt_number || noApply);
    $('#detailClosureCashState').text(d.cash_movement_generated ? 'Sí, generado' : noApply);
    $('#detailClosureCashNumber').text(d.cash_movement?.number || noApply);
    $('#detailClosureVoucherState').text(d.voucher_exists ? 'Comprobante registrado' : 'Sin comprobante registrado');

    const movement = d.cash_movement;
    $('#detailClosureCashSection').toggleClass('d-none', !movement);
    if (movement) {
        $('#detailClosureCashType').text(movement.type_label || noApply);
        $('#detailClosureCashAmount').text(movement.amount_formatted || noApply);
        $('#detailClosureCashMethod').text(movement.payment_method_label || noApply);
        $('#detailClosureCashReference').text(movement.reference || noApply);
        $('#detailClosureCashDate').text(movement.date || noApply);
        $('#detailClosureCashStatus').text(movement.status_label || noApply);
        $('#detailClosureCashBalance').text(movement.balance_after_formatted || noApply);
        $('#detailClosureCashLink').attr('href', movement.url || '#');
    }

    const annulled = d.status === 'anulado';
    $('#detailClosureAnnulmentSection').toggleClass('d-none', !annulled);
    if (annulled) {
        $('#detailClosureAnnulledBy').text(d.annulled_by_name || noApply);
        $('#detailClosureAnnulledAt').text(d.annulled_at || noApply);
        $('#detailClosureAnnulmentReason').text(d.annulment_reason || noApply);
        $('#detailClosureReversal').text(d.reversal_movement?.number || noApply);
    }

    const $voucherPreview = $('#detailClosureVoucherPreview');
    if (d.voucher_type === 'image' && d.voucher_view_url) {
        $voucherPreview.html(`<a href="${escapeHtml(d.voucher_view_url)}" target="_blank"><img src="${escapeHtml(d.voucher_view_url)}" alt="Comprobante del cierre"><span>Vista previa del comprobante</span></a>`);
    } else if (d.voucher_type === 'pdf' && d.voucher_view_url) {
        $voucherPreview.html(`<a href="${escapeHtml(d.voucher_view_url)}" target="_blank" class="closure-pdf-preview"><i class="fas fa-file-pdf"></i><strong>Comprobante PDF</strong><span>Ver PDF</span></a>`);
    } else {
        $voucherPreview.html('<div><i class="fas fa-file-alt"></i><strong>Sin comprobante registrado</strong><span>No se adjuntó archivo al confirmar.</span></div>');
    }

    $('#detailClosureReport').attr('href', d.report_url || '#');
    $('#detailClosurePdf').attr('href', d.pdf_url || '#').html(d.status !== 'cerrado' ? '<i class="fas fa-file-pdf mr-1"></i> Cálculo preliminar PDF' : '<i class="fas fa-file-pdf mr-1"></i> Constancia PDF');
    toggleLink('#detailClosureReceipt', d.receipt_url);
    toggleLink('#detailClosureReceiptPdf', d.receipt_pdf_url);
    toggleLink('#detailClosureVoucherView', d.voucher_view_url);
    toggleLink('#detailClosureVoucherDownload', d.voucher_download_url);
}

function fillClosureUtilityDetail(d) {
    $('#detailClosureUtilityStatus').text(d.utility_status_label || 'No calculada').attr('class', `badge badge-${d.utility_status === 'liquidada' ? 'success' : (d.utility_status === 'provisional' ? 'info' : 'warning')}`);
    $('#detailClosureUtilityActions').text(d.utility_actions_considered || '0');
    $('#detailClosureUtilityMonths').text(d.utility_productive_months || '0');
    $('#detailClosureUtilityActionMonth').text(d.utility_action_month || '0');
    $('#detailClosureUtilityAvailable').text(d.utility_available_formatted || 'S/ 0.00');
    $('#detailClosureUtilityEstimated').text(d.utility_estimated_formatted || 'S/ 0.00');
    $('#detailClosureUtilityPaidNow').text(d.utility_paid_now_formatted || 'S/ 0.00');
    $('#detailClosureUtilityPending').text(d.utility_pending_annual_formatted || 'S/ 0.00');
    $('#detailClosureUtilityMode').text(d.utility_mode === 'provisional' ? 'Retiro con utilidad provisional' : 'Pendiente para cierre anual');
    $('#detailClosureUtilityNote').text(d.utility_note || 'Sin participación acumulada.');
}

function openCloseModal(id) {
    $.get(`${window.memberClosureRoutes.base}/${id}`, d => {
        currentClosure = d;
        clearErrors('#closureCloseForm', '#closure-close-error-messages');
        $('#closureCloseForm')[0].reset();
        $('#closureCloseId').val(d.id);
        const balance = parseFloat(d.final_balance || 0);
        const scenario = d.confirmation_scenario || (balance < 0 ? 'saldo_en_contra' : (balance > 0 ? 'saldo_a_favor' : 'saldo_cero'));
        const $message = $('#closureCloseMessage').removeClass('alert-light alert-success alert-info alert-danger');
        const blocked = scenario === 'saldo_en_contra';

        resetVoucherPreview();
        $('#closureClosePaymentFields').toggleClass('d-none', scenario !== 'saldo_a_favor');
        $('#closureCloseSubmit').toggleClass('d-none', blocked);
        $('#closureCloseCancel').text(blocked ? 'Cerrar' : 'Cancelar');
        $('#closureCloseForm [name="payment_method"]').prop('required', scenario === 'saldo_a_favor');

        if (blocked) {
            $message.addClass('alert-danger').html('<strong>No se puede cerrar la cuenta.</strong><br>El socio tiene una deuda pendiente mayor a sus aportes. Debe regularizar la deuda antes de retirarse.');
        } else if (scenario === 'saldo_a_favor') {
            $message.addClass('alert-success').html(`<strong>Saldo a favor: S/ ${Math.abs(balance).toFixed(2)}</strong><br>La asociación devolverá este monto al socio y se generará un egreso en Caja.`);
        } else {
            $message.addClass('alert-info').html('<strong>Saldo final: S/ 0.00</strong><br>La cuenta se cerrará sin generar movimiento de Caja.');
        }

        updateReferenceRequirement();
        $('#closureCloseModal').modal('show');
    }).fail(showActionError);
}

function submitCloseForm(e) {
    e.preventDefault();
    if (currentClosure?.confirmation_scenario === 'saldo_en_contra' || currentClosure?.can_confirm === false) {
        $('#closure-close-error-messages').removeClass('d-none').text('No se puede confirmar el retiro porque el socio mantiene saldo pendiente en contra.');
        return;
    }
    const id = $('#closureCloseId').val();
    const fd = new FormData($('#closureCloseForm')[0]);
    setLoading(true);
    $.ajax({
        url: `${window.memberClosureRoutes.base}/${id}/cerrar`,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: r => {
            setLoading(false);
            $('#closureCloseModal').modal('hide');
            tableClosures.ajax.reload(null, false);
            loadClosureSummary();
            toast(r.message, 'success');
        },
        error: xhr => {
            setLoading(false);
            if (xhr.status === 422) showErrors('#closureCloseForm', '#closure-close-error-messages', xhr.responseJSON.errors || {});
            else showActionError(xhr);
        }
    });
}

function annulClosure(id) {
    Swal.fire({ title: 'Anular cierre', text: 'Se anulará el movimiento de Caja relacionado si existe y se restaurará el socio cuando sea seguro.', input: 'textarea', inputLabel: 'Motivo de anulación', inputPlaceholder: 'Indique el motivo...', inputValidator: value => !value?.trim() ? 'El motivo es obligatorio.' : undefined, icon: 'warning', showCancelButton: true, confirmButtonText: 'Anular', cancelButtonText: 'Cancelar' }).then(r => {
        if (!r.isConfirmed) return;
        $.post(`${window.memberClosureRoutes.base}/${id}/anular`, { annulment_reason: r.value.trim() }, res => {
            tableClosures.ajax.reload(null, false);
            loadClosureSummary();
            $('#closureDetailModal').modal('hide');
            toast(res.message, 'success');
        }).fail(showActionError);
    });
}

function loadClosureSummary() {
    $.get(window.memberClosureRoutes.summary, s => {
        $('#closureSummaryRetired').text(s.retired_members || 0);
        $('#closureSummaryClosures').text(s.closures || 0);
        $('#closureSummaryReturned').text(`S/ ${s.returned_balance || '0.00'}`);
        $('#closureSummaryCollect').text(`S/ ${s.pending_collect || '0.00'}`);
    });
}

function updateReferenceRequirement() {
    const method = $('#closureCloseForm [name="payment_method"]').val();
    const required = ['yape', 'plin', 'transferencia', 'cheque'].includes(method);
    const showReference = required;
    $('#closureCloseForm [name="payment_reference"]').prop('required', required);
    $('#closureReferenceRequired').toggleClass('d-none', !required);
    $('#closureCloseReferenceGroup').toggleClass('d-none', !showReference);
    if (!showReference) $('#closureCloseForm [name="payment_reference"]').val('');
}

function updateVoucherPreview() {
    const file = this.files && this.files.length ? this.files[0] : null;
    resetVoucherPreview(false);
    $('#closureVoucherName').text(file ? file.name : 'JPG, PNG, WEBP o PDF - max. 4 MB');
    if (!file) return;

    closureVoucherObjectUrl = URL.createObjectURL(file);
    const $preview = $('#closureVoucherPreview').removeClass('d-none');
    if (file.type === 'application/pdf') {
        $preview.html(`<a class="btn btn-sm btn-outline-secondary" href="${closureVoucherObjectUrl}" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Abrir PDF</a>`);
    } else if (file.type.startsWith('image/')) {
        $preview.html(`<img src="${closureVoucherObjectUrl}" alt="Vista previa del comprobante" class="img-thumbnail" style="max-height: 180px; max-width: 100%;">`);
    }
}

function resetVoucherPreview(resetName = true) {
    if (closureVoucherObjectUrl) URL.revokeObjectURL(closureVoucherObjectUrl);
    closureVoucherObjectUrl = null;
    $('#closureVoucherPreview').addClass('d-none').empty();
    if (resetName) $('#closureVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB');
}

function toggleLink(selector, href) {
    $(selector).toggleClass('d-none', !href).attr('href', href || '#');
}

function showErrors(form, box, errors) {
    let list = '<ul class="mb-0">';
    $.each(errors, (k, m) => {
        list += `<li>${m[0]}</li>`;
        const input = $(`${form} [name="${k}"]`);
        input.addClass('is-invalid');
        input.after(`<div class="invalid-feedback d-block">${m[0]}</div>`);
    });
    list += '</ul>';
    $(box).removeClass('d-none').html(list);
}

function clearErrors(form, box) {
    $(box).addClass('d-none').empty();
    $(`${form} .is-invalid`).removeClass('is-invalid');
    $(`${form} .invalid-feedback`).remove();
}

function setValue(form, name, value) {
    $(`${form} [name="${name}"]`).val(value || '');
}

function statusBadge(status, label) {
    const normalizedLabel = (label || '').toLowerCase();
    const cls = status === 'anulado'
        ? 'status-cancelled'
        : (status === 'cerrado' ? 'status-confirmed' : (normalizedLabel.includes('regularizaci') ? 'status-pending' : 'status-calculated'));
    return `<span class="retirement-status-badge ${cls}">${escapeHtml(label || status || '-')}</span>`;
}

function showActionError(xhr) {
    setLoading(false);
    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error');
}

function setLoading(show) {
    if (divLoading) divLoading.style.display = show ? 'flex' : 'none';
}

function toast(message, icon) {
    Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
}

function debounce(cb, wait) {
    let t;
    return function () {
        clearTimeout(t);
        t = setTimeout(cb, wait);
    };
}

function escapeHtml(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
