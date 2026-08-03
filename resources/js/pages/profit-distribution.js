var divLoading = document.getElementById('divLoading');
let tableProfit;
let currentProfit = null;
let currentPayDetail = null;
let profitAvailability = 0;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
    tableProfit = $('#tableProfit').DataTable({
        processing: true, serverSide: true,
        ajax: { url: window.profitRoutes.list, data: d => { d.period_year = $('#profit_filter_year').val(); d.period_month = $('#profit_filter_month').val(); d.status = $('#profit_filter_status').val(); d.date_from = $('#profit_filter_date_from').val(); d.date_to = $('#profit_filter_date_to').val(); } },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false }, { data: 'code', name: 'code' }, { data: 'period', orderable: false, searchable: false }, { data: 'start_date', name: 'start_date' }, { data: 'end_date', name: 'end_date' }, { data: 'total_profit', name: 'total_profit' }, { data: 'total_shares', name: 'total_shares' }, { data: 'profit_per_share', name: 'profit_per_share' }, { data: 'status', orderable: false, searchable: false }, { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true, language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });
    loadProfitSummary(); tableProfit.on('draw', loadProfitSummary);
    $('#profit_filter_year, #profit_filter_month, #profit_filter_status, #profit_filter_date_from, #profit_filter_date_to').on('change input', debounce(() => tableProfit.ajax.reload(), 300));
    $('#btnClearProfitFilters').on('click', () => { $('#profit_filter_year, #profit_filter_month, #profit_filter_status, #profit_filter_date_from, #profit_filter_date_to').val(''); tableProfit.ajax.reload(); });
    $('#btnNewProfit').on('click', () => { resetProfitForm(); fetchNextProfitCode(); loadProfitAvailability(); $('#profitModal').modal('show'); });
    $('#btnCalculateProfit').on('click', calculateProfitPreview);
    $('#profitForm [name="period_year"], #profitForm [name="period_month"]').on('input change', function () { syncProfitPeriodDates(); invalidateProfitCalculation(); loadProfitAvailability($('#profitForm').attr('data-id') || null); });
    $('#profitForm [name="start_date"], #profitForm [name="end_date"]').on('change', function () { invalidateProfitCalculation(); loadProfitAvailability($('#profitForm').attr('data-id') || null); });
    $('#profitForm [name="total_profit"]').on('input change', function () { updateProfitFinancialCards(); invalidateProfitCalculation(); });
    $('#profitForm').on('submit', submitProfitForm);
    $('#profitPayForm').on('submit', submitProfitPayForm);
    $('#profitPayForm [name="payment_method"]').on('change', updatePayReferenceRequirement);
    $('#profitPayVoucher').on('change', function () { const f = this.files && this.files.length ? this.files[0] : null; $('#profitPayVoucherName').text(f ? f.name : 'JPG, PNG, WEBP o PDF - max. 4 MB'); });
    $('#btnManageProfitSources').on('click', openProfitSources);
    $('#btnRefreshProfit').on('click', () => loadProfitAvailability($('#profitForm').attr('data-id') || null));
    $('#btnViewProfitSources').on('click', showProfitPaymentSources);
    $('#profitSourceForm').on('submit', submitProfitSource);
    $(document).on('click', '.annulProfitSource', function () { annulProfitSource($(this).data('id')); });
    $(document).on('click', '.showProfit', function () { loadProfitDetail($(this).data('id')); });
    $(document).on('click', '.editProfit', function () { loadProfitForm($(this).data('id')); });
    $(document).on('click', '.approveProfit', function () { approveProfit($(this).data('id')); });
    $(document).on('click', '.annulProfit', function () { annulProfit($(this).data('id')); });
    $(document).on('click', '.payProfitDetail', function () { openPayModal($(this).data('id')); });
});

function resetProfitForm() {
    $('#profitForm')[0].reset(); $('#profitForm').removeAttr('data-id'); clearErrors('#profitForm', '#profit-error-messages');
    $('#profitModalLabel').text('Nueva distribucion de utilidades'); $('#profitSaveText').text('Guardar distribucion');
    $('#profitForm [name="code"]').val(window.profitRoutes.nextCodeValue || 'UTI-000001'); $('#profitForm [name="period_year"]').val(new Date().getFullYear()); $('#profitForm [name="status"]').val('calculado'); syncProfitPeriodDates();
    $('#profitPreviewTotal').text('S/ 0.00'); $('#profitPreviewShares').text('0'); $('#profitPreviewPerShare').text('S/ 0.00'); $('#profitPreviewMembers').text('0'); $('#profitPreviewActivities').text('S/ 0.00'); $('#profitPreviewRows').html('<tr><td colspan="10">Calcule para ver la vista previa.</td></tr>');
    profitAvailability = 0; $('#profitForm [name="distribution_id"]').val('');
    $('#profitInterestCollected, #profitLateFeesCollected, #profitPositiveAdjustments, #profitNegativeAdjustments, #profitGeneratedAmount, #profitAlreadyDistributed').text('S/ 0.00');
    $('#profitAvailableAmount, #profitDistributingAmount, #profitRemainingAmount').text('S/ 0.00'); $('#profitContributionsAmount').text('S/ 0.00 · 0 acciones');
    $('#profitNoDataWarning, #profitNoAvailabilityWarning').addClass('d-none'); $('#profitSaveButton').prop('disabled', true);
}
function fetchNextProfitCode() { $.get(window.profitRoutes.nextCode, r => $('#profitForm [name="code"]').val(r.code || 'UTI-000001')); }
function loadProfitAvailability(excludeId = null) {
    const params = { start_date: $('#profitForm [name="start_date"]').val(), end_date: $('#profitForm [name="end_date"]').val() };
    if (!params.start_date || !params.end_date) return;
    if (excludeId) params.exclude_id = excludeId;
    $.get(window.profitRoutes.availability, params, data => {
        profitAvailability = Number(data.available || 0);
        $('#profitAvailableAmount').text(data.available_formatted || formatMoney(profitAvailability));
        $('#profitContributionsAmount').text(`${data.contributions_total_formatted || 'S/ 0.00'} · ${Number(data.shares_total || 0).toFixed(2)} acciones`);
        $('#profitAvailableSources').text('Intereses y moras cobradas, menos distribuciones del mismo periodo.');
        $('#profitInterestCollected').text(data.interest_collected_formatted || 'S/ 0.00');
        $('#profitLateFeesCollected').text(data.late_fees_collected_formatted || 'S/ 0.00');
        $('#profitPositiveAdjustments').text(data.positive_adjustments_formatted || 'S/ 0.00');
        $('#profitNegativeAdjustments').text(data.negative_adjustments_formatted || 'S/ 0.00');
        $('#profitGeneratedAmount').text(data.generated_formatted || 'S/ 0.00');
        $('#profitAlreadyDistributed').text(data.distributed_formatted || 'S/ 0.00');
        if (!excludeId || !$('#profitForm [name="total_profit"]').val()) $('#profitForm [name="total_profit"]').val(profitAvailability > 0 ? profitAvailability.toFixed(2) : '');
        $('#profitNoAvailabilityWarning').toggleClass('d-none', profitAvailability > 0);
        updateProfitFinancialCards();
        if (profitAvailability <= 0) $('#profitSaveButton').prop('disabled', true);
    }).fail(showActionError);
}
function showProfitPaymentSources() {
    const params = { start_date: $('#profitForm [name="start_date"]').val(), end_date: $('#profitForm [name="end_date"]').val() };
    if (!params.start_date || !params.end_date) return;
    $('#profitSourcesDetailPeriod').text(`${params.start_date} al ${params.end_date}`);
    $('#profitSourcesDetailRows').html('<tr><td colspan="8" class="py-4 text-muted">Cargando...</td></tr>');
    $.get(window.profitRoutes.paymentSources, params, rows => {
        $('#profitSourcesDetailRows').html((rows || []).map(row => `<tr><td>${escapeHtml(row.date)}</td><td>${escapeHtml(row.member)}</td><td>${escapeHtml(row.loan)}</td><td>${escapeHtml(row.installment)}</td><td>${formatMoney(row.interest)}</td><td>${formatMoney(row.late_fee)}</td><td class="font-weight-bold text-success">${formatMoney(row.total)}</td><td><span class="badge badge-success">${escapeHtml(row.status)}</span></td></tr>`).join('') || '<tr><td colspan="8" class="py-4 text-muted">No hay intereses ni moras cobradas en este periodo.</td></tr>');
        $('#profitSourcesDetailModal').modal('show');
    }).fail(showActionError);
}
function updateProfitFinancialCards() {
    const amount = Number($('#profitForm [name="total_profit"]').val() || 0);
    const remaining = Math.max(0, profitAvailability - amount);
    $('#profitDistributingAmount').text(formatMoney(amount));
    $('#profitRemainingAmount').text(formatMoney(remaining));
    $('#profitRemainingText').text(amount > profitAvailability
        ? `El monto excede la utilidad disponible en ${formatMoney(amount - profitAvailability)}.`
        : 'Quedará disponible para una futura distribución.');
    $('#profitRemainingAmount').toggleClass('text-danger', amount > profitAvailability);
    $('#profitFinancialWarning')
        .toggleClass('d-none', !(amount > profitAvailability))
        .text(amount > profitAvailability ? 'No se puede distribuir más utilidad de la disponible. La utilidad solo se genera por intereses y moras cobradas.' : '');
}
function syncProfitPeriodDates() { const year = Number($('#profitForm [name="period_year"]').val()); if (!year || year < 2000) return; $('#profitForm [name="period_month"]').val(''); $('#profitForm [name="start_date"]').val(`${year}-03-01`); $('#profitForm [name="end_date"]').val(`${year + 1}-03-01`); }
function invalidateProfitCalculation() { $('#profitSaveButton').prop('disabled', true); $('#profitNoDataWarning').addClass('d-none'); }
function calculateProfitPreview() { clearErrors('#profitForm', '#profit-error-messages'); invalidateProfitCalculation(); $.post(window.profitRoutes.calculate, $('#profitForm').serialize(), payload => { fillPreview(payload); const hasBalance = Number(payload.availability?.available ?? profitAvailability) > 0; $('#profitSaveButton').prop('disabled', !(payload.details || []).length || !hasBalance); }).fail(xhr => { if (xhr.status === 422) { const errors = xhr.responseJSON.errors || {}; const noData = Boolean(errors.total_action_month); $('#profitNoDataWarning').toggleClass('d-none', !noData); $('#profitPreviewRows').html(`<tr><td colspan="10" class="text-center text-muted py-4">${noData ? 'No hay aportes válidos para calcular utilidades en este periodo.' : 'Revise los datos del periodo.'}</td></tr>`); showErrors('#profitForm', '#profit-error-messages', errors); } else showActionError(xhr); }); }
function fillPreview(payload) {
    const s = payload.summary || {};
    const actionMonth = Number(s.total_action_month || 0);
    const profitRate = Number(s.profit_per_action_month ?? parseFormattedNumber(s.profit_per_action_month_formatted));
    if (payload.availability) {
        profitAvailability = Number(payload.availability.available || 0);
        $('#profitAvailableAmount').text(payload.availability.available_formatted || formatMoney(profitAvailability));
        $('#profitRemainingAmount').text(payload.availability.remaining_formatted || formatMoney(profitAvailability - Number(s.total_profit || 0)));
    }
    updateProfitFinancialCards();
    $('#profitNoDataWarning').addClass('d-none'); $('#profitPreviewTotal').text(s.total_profit_formatted || 'S/ 0.00'); $('#profitPreviewShares').text(actionMonth.toFixed(2)); $('#profitPreviewPerShare').text(`S/ ${profitRate.toFixed(4)}`); $('#profitPreviewMembers').text(s.members_count || 0); $('#profitPreviewActivities').text(`S/ ${Number(s.closed_activities_profit || 0).toFixed(2)}`);
    $('#profitPreviewRows').html((payload.details || []).map(r => `<tr><td>${escapeHtml(r.member_name)}${r.member_status_warning ? `<small class="d-block text-warning">${escapeHtml(r.member_status_warning)}</small>` : ''}</td><td>${escapeHtml(r.member_dni)}</td><td>${escapeHtml(r.member_code)}</td><td>${Number(r.contributions_count || 0)}</td><td>${Number(r.actions_considered).toFixed(4)}</td><td>${Number(r.months_considered)}</td><td><strong>${Number(r.action_month).toFixed(4)}</strong></td><td>${Number(r.participation_percentage).toFixed(4)}%</td><td class="font-weight-bold text-success">${escapeHtml(r.profit_amount_formatted)}</td><td><span class="badge badge-${escapeHtml(r.member_status_tone || 'secondary')}">${escapeHtml(r.member_status_label || '-')}</span></td></tr>`).join('') || '<tr><td colspan="10">No hay aportes válidos para calcular utilidades en este periodo.</td></tr>');
}
function submitProfitForm(e) {
    e.preventDefault(); clearErrors('#profitForm', '#profit-error-messages'); setLoading(true);
    const form = $('#profitForm'); const id = form.attr('data-id'); let data = form.serializeArray(); let url = window.profitRoutes.store;
    if (id) { url = `${window.profitRoutes.base}/${id}`; data.push({ name: '_method', value: 'PUT' }); }
    $.post(url, $.param(data), r => { setLoading(false); $('#profitModal').modal('hide'); tableProfit.ajax.reload(null, false); loadProfitSummary(); toast(r.message, 'success'); }).fail(xhr => { setLoading(false); if (xhr.status === 422) showErrors('#profitForm', '#profit-error-messages', xhr.responseJSON.errors || {}); else showActionError(xhr); });
}
function loadProfitForm(id) {
    $.get(`${window.profitRoutes.base}/${id}/edit`, d => { resetProfitForm(); $('#profitForm').attr('data-id', d.id); $('#profitForm [name="distribution_id"]').val(d.id); $('#profitModalLabel').text('Editar distribución'); $('#profitSaveText').text('Actualizar distribución'); setValue('#profitForm', 'code', d.code); setValue('#profitForm', 'period_year', d.period_year); setValue('#profitForm', 'period_month', d.period_month); setValue('#profitForm', 'start_date', d.start_date); setValue('#profitForm', 'end_date', d.end_date); setValue('#profitForm', 'total_profit', d.total_profit); setValue('#profitForm', 'status', d.status); setValue('#profitForm', 'observation', d.observation); fillPreview({ summary: { total_profit_formatted: d.total_profit_formatted, total_action_month: d.total_action_month, total_action_month_formatted: d.total_action_month, profit_per_action_month_formatted: d.profit_per_action_month_formatted, members_count: d.details.length }, details: d.details }); loadProfitAvailability(d.id); $('#profitSaveButton').prop('disabled', false); $('#profitModal').modal('show'); }).fail(showActionError);
}
function loadProfitDetail(id, show = true) { setLoading(true); $.get(`${window.profitRoutes.base}/${id}`, d => { setLoading(false); currentProfit = d; fillProfitDetail(d); if (show) $('#profitDetailModal').modal('show'); }).fail(showActionError); }
function openProfitSources() {
    $('#profitSourceForm')[0].reset(); clearErrors('#profitSourceForm', '#profit-source-errors');
    $('#profitSourceForm [name="source_date"]').val(new Date().toISOString().slice(0, 10)); loadProfitSources();
    $('#profitModal').one('hidden.bs.modal', () => $('#profitSourceModal').modal('show')).modal('hide');
    $('#profitSourceModal').one('hidden.bs.modal', () => { loadProfitAvailability($('#profitForm').attr('data-id') || null); $('#profitModal').modal('show'); });
}
function loadProfitSources() {
    $.get(window.profitRoutes.sources, rows => $('#profitSourceRows').html((rows || []).map(row => `<tr><td>${escapeHtml(row.date)}</td><td>${escapeHtml(row.code)}</td><td><strong>${escapeHtml(row.reason)}</strong>${row.observation ? `<small class="d-block text-muted">${escapeHtml(row.observation)}</small>` : ''}</td><td class="font-weight-bold text-success">${escapeHtml(row.amount_formatted)}</td><td>${escapeHtml(row.created_by)}<small class="d-block text-muted">${escapeHtml(row.created_at)}</small></td><td><span class="badge badge-${row.status === 'activo' ? 'success' : 'secondary'}">${escapeHtml(row.status_label)}</span></td><td>${row.status === 'activo' && window.profitRoutes.canAnnulSource ? `<button type="button" class="btn btn-sm btn-light border text-danger annulProfitSource" data-id="${row.id}" title="Anular"><i class="fas fa-ban"></i></button>` : ''}</td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">No hay fuentes manuales registradas.</td></tr>')).fail(showActionError);
}
function submitProfitSource(e) {
    e.preventDefault(); clearErrors('#profitSourceForm', '#profit-source-errors'); setLoading(true);
    $.post(window.profitRoutes.sources, $('#profitSourceForm').serialize(), response => { setLoading(false); $('#profitSourceForm')[0].reset(); $('#profitSourceForm [name="source_date"]').val(new Date().toISOString().slice(0, 10)); loadProfitSources(); toast(response.message, 'success'); }).fail(xhr => { setLoading(false); if (xhr.status === 422) showErrors('#profitSourceForm', '#profit-source-errors', xhr.responseJSON.errors || {}); else showActionError(xhr); });
}
function annulProfitSource(id) {
    Swal.fire({ title: 'Anular fuente de utilidad', text: 'El monto dejará de formar parte de la utilidad disponible.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Anular', cancelButtonText: 'Cancelar' }).then(result => { if (!result.isConfirmed) return; $.post(`${window.profitRoutes.sources}/${id}/anular`, response => { loadProfitSources(); toast(response.message, 'success'); }).fail(showActionError); });
}
function fillProfitDetail(d) {
    $('#detailProfitCode').text(d.code || '-'); $('#detailProfitPeriod').text(d.period || '-'); $('#detailProfitStatus').html(statusBadge(d.status, d.status_label)); $('#detailProfitTotal').text(d.total_profit_formatted); $('#detailProfitShares').text(d.total_action_month); $('#detailProfitPerShare').text(d.profit_per_action_month_formatted); $('#detailProfitCalculatedAt').text(d.calculated_at || '-'); $('#detailProfitCalculatedBy').text(d.calculated_by_name || '-'); $('#detailProfitApprovedAt').text(d.approved_at || '-'); $('#detailProfitApprovedBy').text(d.approved_by_name || '-'); $('#detailProfitStart').text(d.start_date_formatted || '-'); $('#detailProfitEnd').text(d.end_date_formatted || '-'); $('#detailProfitObservation').text(d.observation || '-'); $('#detailProfitReport').attr('href', d.report_url || '#');
    $('#detailProfitRows').html((d.details || []).map(x => `<tr><td>${escapeHtml(x.member_name)}</td><td>${escapeHtml(x.member_dni)}</td><td>${escapeHtml(x.actions_considered)}</td><td>${escapeHtml(x.months_considered)}</td><td><strong>${escapeHtml(x.action_month)}</strong></td><td>${escapeHtml(x.participation_percentage)}</td><td class="font-weight-bold text-success">${escapeHtml(x.profit_amount_formatted)}</td><td>${escapeHtml(x.paid_amount_formatted)}</td><td>${escapeHtml(x.pending_amount_formatted)}</td><td>${escapeHtml(x.status_label)}</td><td><div class="btn-group btn-group-sm">${d.status === 'aprobado' && x.status !== 'pagado' ? `<button class="btn btn-light border payProfitDetail" data-id="${x.id}"><i class="fas fa-hand-holding-usd"></i></button>` : ''}${x.receipt_url ? `<a class="btn btn-light border" target="_blank" href="${x.receipt_url}"><i class="fas fa-receipt"></i></a>` : ''}${x.voucher_download_url ? `<a class="btn btn-light border" target="_blank" href="${x.voucher_download_url}"><i class="fas fa-paperclip"></i></a>` : ''}</div></td></tr>`).join('') || '<tr><td colspan="11">Sin detalles.</td></tr>');
}
function approveProfit(id) { Swal.fire({ title: 'Aprobar distribucion', icon: 'question', showCancelButton: true, confirmButtonText: 'Aprobar', cancelButtonText: 'Cancelar' }).then(r => { if (!r.isConfirmed) return; $.post(`${window.profitRoutes.base}/${id}/aprobar`, res => { tableProfit.ajax.reload(null, false); loadProfitSummary(); toast(res.message, 'success'); }).fail(showActionError); }); }
function annulProfit(id) { Swal.fire({ title: 'Anular distribucion', text: 'Solo se permite si no tiene pagos realizados.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Anular', cancelButtonText: 'Cancelar' }).then(r => { if (!r.isConfirmed) return; $.post(`${window.profitRoutes.base}/${id}/anular`, res => { tableProfit.ajax.reload(null, false); loadProfitSummary(); $('#profitDetailModal').modal('hide'); toast(res.message, 'success'); }).fail(showActionError); }); }
function openPayModal(id) { currentPayDetail = (currentProfit.details || []).find(x => x.id === id); if (!currentPayDetail) return; clearErrors('#profitPayForm', '#profit-pay-error-messages'); $('#profitPayForm')[0].reset(); $('#profitPayDetailId').val(id); $('#payProfitMember').text(currentPayDetail.member_name); $('#payProfitDni').text(currentPayDetail.member_dni); $('#payProfitShares').text(currentPayDetail.shares_quantity); $('#payProfitPercent').text(currentPayDetail.participation_percentage); $('#payProfitAmount').text(currentPayDetail.profit_amount_formatted); $('#payProfitPending').text(currentPayDetail.pending_amount_formatted); $('#profitPayVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB'); updatePayReferenceRequirement(); $('#profitPayModal').modal('show'); }
function submitProfitPayForm(e) { e.preventDefault(); const id = $('#profitPayDetailId').val(); const fd = new FormData($('#profitPayForm')[0]); setLoading(true); $.ajax({ url: `${window.profitRoutes.detailBase}/${id}/pagar`, type: 'POST', data: fd, processData: false, contentType: false, success: r => { setLoading(false); $('#profitPayModal').modal('hide'); tableProfit.ajax.reload(null, false); if (currentProfit) loadProfitDetail(currentProfit.id, false); loadProfitSummary(); toast(r.message, 'success'); }, error: xhr => { setLoading(false); if (xhr.status === 422) showErrors('#profitPayForm', '#profit-pay-error-messages', xhr.responseJSON.errors || {}); else showActionError(xhr); } }); }
function updatePayReferenceRequirement() { const m = $('#profitPayForm [name="payment_method"]').val(); const req = ['yape', 'plin', 'transferencia'].includes(m); $('#profitPayForm [name="payment_reference"]').prop('required', req); $('#profitPayReferenceRequired').toggleClass('d-none', !req); }
function loadProfitSummary() { $.get(window.profitRoutes.summary, s => { $('#profitSummaryDistributed').text(`S/ ${s.distributed || '0.00'}`); $('#profitSummaryCalculated').text(s.calculated || 0); $('#profitSummaryApproved').text(s.approved || 0); $('#profitSummaryPending').text(`S/ ${s.pending || '0.00'}`); }); }
function showErrors(form, box, errors) { let list = '<ul class="mb-0">'; $.each(errors, (k, m) => { list += `<li>${m[0]}</li>`; const input = $(`${form} [name="${k}"]`); input.addClass('is-invalid'); input.after(`<div class="invalid-feedback d-block">${m[0]}</div>`); }); list += '</ul>'; $(box).removeClass('d-none').html(list); }
function clearErrors(form, box) { $(box).addClass('d-none').empty(); $(`${form} .is-invalid`).removeClass('is-invalid'); $(`${form} .invalid-feedback`).remove(); }
function setValue(form, name, value) { $(`${form} [name="${name}"]`).val(value || ''); }
function statusBadge(status, label) { const cls = status === 'anulado' ? 'danger' : (status === 'pagado' ? 'success' : (status === 'aprobado' ? 'info' : 'secondary')); return `<span class="badge badge-${cls}">${escapeHtml(label || status || '-')}</span>`; }
function showActionError(xhr) { setLoading(false); Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error'); }
function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function toast(message, icon) { Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
function debounce(cb, wait) { let t; return function () { clearTimeout(t); t = setTimeout(cb, wait); }; }
function escapeHtml(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
function parseFormattedNumber(value) { const normalized = String(value || '').replace(/[^0-9.-]/g, ''); return Number(normalized) || 0; }
function formatMoney(value) { return `S/ ${Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; }
