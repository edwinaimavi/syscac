var divLoading = document.getElementById('divLoading');
let tableActivities;
let currentActivity = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableActivities = $('#tableActivities').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.activityRoutes.list,
            data: function (data) {
                data.date_from = $('#activity_filter_date_from').val();
                data.date_to = $('#activity_filter_date_to').val();
                data.status = $('#activity_filter_status').val();
                data.name = $('#activity_filter_name').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code', defaultContent: '-' },
            { data: 'name', name: 'name' },
            { data: 'activity_date', name: 'activity_date' },
            { data: 'total_income', name: 'total_income' },
            { data: 'total_expense', name: 'total_expense' },
            { data: 'profit', orderable: false, searchable: false },
            { data: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadActivitySummary();
    tableActivities.on('draw', loadActivitySummary);
    $('#activity_filter_date_from, #activity_filter_date_to, #activity_filter_status').on('change', () => tableActivities.ajax.reload());
    $('#activity_filter_name').on('input', debounce(() => tableActivities.ajax.reload(), 350));
    $('#btnClearActivityFilters').on('click', function () { $('#activity_filter_date_from, #activity_filter_date_to, #activity_filter_status, #activity_filter_name').val(''); tableActivities.ajax.reload(); });
    $('#btnNewActivity').on('click', function () { resetActivityForm(); fetchNextActivityCode(); $('#activityModal').modal('show'); });
    $('#activityForm').on('submit', submitActivityForm);
    $('#activityMovementForm').on('submit', submitActivityMovementForm);
    $('[name="payment_method"]').on('change', updateMovementReferenceRequirement);
    $('#activityMovementForm [name="type"], #activityMovementForm [name="amount"]').on('input change', updateMovementSide);
    $('#activityMovementVoucher').on('change', function () { const file = this.files && this.files.length ? this.files[0] : null; $('#activityMovementVoucherName').text(file ? file.name : 'JPG, PNG, WEBP o PDF - max. 4 MB'); });
    $('#btnNewActivityMovement').on('click', () => openMovementModal());
    $('#btnCloseActivityFromDetail').on('click', function () { if (currentActivity) closeActivity(currentActivity.id); });

    $(document).on('click', '.editActivity', function () { loadActivityForm($(this).data('id')); });
    $(document).on('click', '.showActivity', function () { loadActivityDetail($(this).data('id')); });
    $(document).on('click', '.closeActivity', function () { closeActivity($(this).data('id')); });
    $(document).on('click', '.annulActivity', function () { annulActivity($(this).data('id')); });
    $(document).on('click', '.showActivityMovement', function () { showActivityMovement($(this).data('id')); });
    $(document).on('click', '.editActivityMovement', function () { editActivityMovement($(this).data('id')); });
    $(document).on('click', '.annulActivityMovement', function () { annulActivityMovement($(this).data('id')); });
});

function submitActivityForm(event) {
    event.preventDefault();
    clearActivityErrors();
    setLoading(true);
    const form = $('#activityForm');
    const id = form.attr('data-id');
    let url = window.activityRoutes.store;
    const data = form.serializeArray();
    if (id) { url = `${window.activityRoutes.base}/${id}`; data.push({ name: '_method', value: 'PUT' }); }
    $.post(url, $.param(data), function (response) {
        setLoading(false); $('#activityModal').modal('hide'); tableActivities.ajax.reload(null, false); loadActivitySummary(); toast(response.message, 'success');
    }).fail(function (xhr) { setLoading(false); if (xhr.status === 422) showActivityErrors(xhr.responseJSON.errors || {}); else Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar.', 'error'); });
}

function submitActivityMovementForm(event) {
    event.preventDefault();
    clearMovementErrors();
    setLoading(true);
    const form = $('#activityMovementForm');
    const id = form.attr('data-id');
    const activityId = $('#movementActivityId').val();
    const formData = new FormData(form[0]);
    let url = `${window.activityRoutes.base}/${activityId}/movimientos`;
    if (id) { url = `${window.activityRoutes.movementBase}/${id}`; formData.append('_method', 'PUT'); }
    $.ajax({ url, type: 'POST', data: formData, processData: false, contentType: false, success: function (response) {
        setLoading(false); $('#activityMovementModal').modal('hide'); tableActivities.ajax.reload(null, false); loadActivitySummary(); if (currentActivity) loadActivityDetail(currentActivity.id, false); toast(response.message, 'success');
    }, error: function (xhr) { setLoading(false); if (xhr.status === 422) showMovementErrors(xhr.responseJSON.errors || {}); else Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar el movimiento.', 'error'); } });
}

function resetActivityForm() {
    $('#activityForm')[0].reset(); $('#activityForm').removeAttr('data-id'); clearActivityErrors();
    $('#activityModalLabel').text('Nueva actividad'); $('#activitySaveText').text('Guardar actividad');
    $('[name="activity_date"]').val(new Date().toISOString().slice(0, 10)); $('#activityForm [name="status"]').val('abierta');
    $('#activityForm [name="code"]').val(window.activityRoutes.nextCodeValue || 'ACT-000001');
}

function fetchNextActivityCode() { $.get(window.activityRoutes.nextCode, r => $('#activityForm [name="code"]').val(r.code || 'ACT-000001')); }

function loadActivityForm(id) {
    setLoading(true);
    $.get(`${window.activityRoutes.base}/${id}/edit`, function (activity) {
        setLoading(false); resetActivityForm(); $('#activityForm').attr('data-id', activity.id); $('#activityModalLabel').text('Editar actividad'); $('#activitySaveText').text('Actualizar actividad');
        $('#activityForm [name="code"]').val(activity.code); setValue('#activityForm', 'name', activity.name); setValue('#activityForm', 'activity_date', activity.activity_date); setValue('#activityForm', 'status', activity.status); setValue('#activityForm', 'description', activity.description);
        $('#activityModal').modal('show');
    }).fail(showActionError);
}

function loadActivityDetail(id, showModal = true) {
    setLoading(true);
    $.get(`${window.activityRoutes.base}/${id}`, function (activity) {
        setLoading(false); currentActivity = activity; fillActivityDetail(activity); if (showModal) $('#activityDetailModal').modal('show');
    }).fail(showActionError);
}

function fillActivityDetail(activity) {
    $('#detailActivityCode').text(activity.code || '-'); $('#detailActivityName').text(activity.name || '-'); $('#detailActivityStatus').html(statusBadge(activity.status, activity.status_label));
    $('#detailActivityIncome').text(activity.total_income_formatted || 'S/ 0.00'); $('#detailActivityExpense').text(activity.total_expense_formatted || 'S/ 0.00');
    $('#detailActivityProfit').text(activity.profit_formatted || 'S/ 0.00').attr('class', `text-${activity.profit_class || 'secondary'}`);
    $('#detailActivityDate').text(activity.activity_date_formatted || '-'); $('#detailActivityClosedAt').text(activity.closed_at || '-'); $('#detailActivityClosedBy').text(activity.closed_by_name || '-');
    $('#detailActivityCreatedBy').text(activity.created_by_name || '-'); $('#detailActivityDescription').text(activity.description || '-');
    $('#detailActivityReport').attr('href', activity.report_url || '#'); $('#btnCloseActivityFromDetail').toggleClass('d-none', activity.status !== 'abierta');
    const rows = (activity.movements || []).map(m => `<tr><td>${escapeHtml(m.movement_date)}</td><td>${escapeHtml(m.type_label)}</td><td>${escapeHtml(m.member_name)}</td><td>${escapeHtml(m.concept)}</td><td>${escapeHtml(m.amount)}</td><td>${escapeHtml(m.status_label)}</td><td><div class="btn-group btn-group-sm"><button class="btn btn-light border showActivityMovement" data-id="${m.id}"><i class="fas fa-eye"></i></button>${activity.status === 'abierta' && m.status_label !== 'Anulado' ? `<button class="btn btn-light border editActivityMovement" data-id="${m.id}"><i class="fas fa-edit"></i></button><button class="btn btn-light border text-danger annulActivityMovement" data-id="${m.id}"><i class="fas fa-ban"></i></button>` : ''}</div></td></tr>`).join('');
    $('#detailActivityMovementRows').html(rows || '<tr><td colspan="7">Sin movimientos.</td></tr>');
}

function openMovementModal(movement = null) {
    if (!currentActivity || currentActivity.status !== 'abierta') { Swal.fire('Atencion', 'No se pueden registrar movimientos en una actividad cerrada.', 'warning'); return; }
    resetMovementForm(); $('#movementActivityId').val(currentActivity.id); $('#activityMovementSideActivity').text(currentActivity.name || '-');
    if (movement) fillMovementForm(movement); else $.get(`${window.activityRoutes.base}/${currentActivity.id}/movimientos/next-code`, r => $('#activityMovementForm [name="code"]').val(r.code || 'MOV-ACT-000001'));
    $('#activityMovementModal').modal('show');
}

function resetMovementForm() {
    $('#activityMovementForm')[0].reset(); $('#activityMovementForm').removeAttr('data-id'); clearMovementErrors();
    $('#activityMovementModalLabel').text('Nuevo movimiento'); $('#activityMovementSaveText').text('Guardar movimiento');
    $('#activityMovementForm [name="movement_date"]').val(new Date().toISOString().slice(0, 10)); $('#activityMovementForm [name="type"]').val('ingreso'); $('#activityMovementForm [name="status"]').val('registrado');
    $('#activityMovementForm [name="code"]').val(window.activityRoutes.nextMovementCodeValue || 'MOV-ACT-000001'); $('#activityMovementVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB'); $('#activityMovementCurrentVoucherBox').addClass('d-none'); updateMovementReferenceRequirement(); updateMovementSide();
}

function fillMovementForm(movement) {
    $('#activityMovementForm').attr('data-id', movement.id); $('#activityMovementModalLabel').text('Editar movimiento'); $('#activityMovementSaveText').text('Actualizar movimiento');
    $('#activityMovementForm [name="code"]').val(movement.code); setValue('#activityMovementForm', 'movement_date', movement.movement_date); setValue('#activityMovementForm', 'type', movement.type); setValue('#activityMovementForm', 'status', movement.status);
    setValue('#activityMovementForm', 'member_id', movement.member_id); setValue('#activityMovementForm', 'concept', movement.concept); setValue('#activityMovementForm', 'amount', movement.amount); setValue('#activityMovementForm', 'payment_method', movement.payment_method); setValue('#activityMovementForm', 'payment_reference', movement.payment_reference); setValue('#activityMovementForm', 'observation', movement.observation);
    if (movement.voucher_url) { $('#activityMovementCurrentVoucherBox').removeClass('d-none'); $('#activityMovementCurrentVoucherLink').attr('href', movement.voucher_url); }
    updateMovementReferenceRequirement(); updateMovementSide();
}

function showActivityMovement(id) { setLoading(true); $.get(`${window.activityRoutes.movementBase}/${id}`, function (m) { setLoading(false); fillMovementDetail(m); $('#activityMovementDetailModal').modal('show'); }).fail(showActionError); }
function editActivityMovement(id) { setLoading(true); $.get(`${window.activityRoutes.movementBase}/${id}/edit`, function (m) { setLoading(false); openMovementModal(m); }).fail(showActionError); }
function annulActivityMovement(id) { Swal.fire({ title: 'Anular movimiento', text: 'Tambien se anulara Caja y recibo relacionado.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Si, anular', cancelButtonText: 'Cancelar' }).then(r => { if (!r.isConfirmed) return; $.post(`${window.activityRoutes.movementBase}/${id}/anular`, function (response) { tableActivities.ajax.reload(null, false); if (currentActivity) loadActivityDetail(currentActivity.id, false); loadActivitySummary(); toast(response.message, 'success'); }).fail(showActionError); }); }

function fillMovementDetail(m) {
    $('#detailActivityMovementCode').text(m.code || '-'); $('#detailActivityMovementConcept').text(m.concept || '-'); $('#detailActivityMovementStatus').html(statusBadge(m.status, m.status_label));
    $('#detailActivityMovementActivity').text(m.activity_name || '-'); $('#detailActivityMovementType').text(m.type_label || '-'); $('#detailActivityMovementAmount').text(m.amount_formatted || 'S/ 0.00');
    $('#detailActivityMovementDate').text(m.movement_date_formatted || '-'); $('#detailActivityMovementMember').text(m.member_name || '-'); $('#detailActivityMovementDni').text(m.member_dni || '-'); $('#detailActivityMovementCash').text(m.cash_movement_number || '-');
    $('#detailActivityMovementPayment').text(m.payment_method_label || '-'); $('#detailActivityMovementReference').text(m.payment_reference || '-'); $('#detailActivityMovementCreatedBy').text(m.created_by_name || '-'); $('#detailActivityMovementCreatedAt').text(m.created_at || '-'); $('#detailActivityMovementObservation').text(m.observation || '-');
    setLink('#detailActivityMovementVoucherView', m.voucher_url, '<i class="fas fa-eye mr-1"></i> Ver comprobante', '<i class="fas fa-eye mr-1"></i> Sin comprobante');
    setLink('#detailActivityMovementVoucherDownload', m.voucher_download_url, '<i class="fas fa-download mr-1"></i> Descargar comprobante', '<i class="fas fa-download mr-1"></i> Descargar comprobante');
    setLink('#detailActivityMovementReceipt', m.receipt_url, `<i class="fas fa-print mr-1"></i> ${escapeHtml(m.receipt_number || 'Ver recibo')}`, '<i class="fas fa-print mr-1"></i> Sin recibo');
    setLink('#detailActivityMovementReceiptPdf', m.receipt_pdf_url, '<i class="fas fa-file-pdf mr-1"></i> PDF', '<i class="fas fa-file-pdf mr-1"></i> PDF');
}

function closeActivity(id) { Swal.fire({ title: 'Cerrar actividad', text: 'Se calcularan ingresos, egresos y utilidad. No se podran registrar nuevos movimientos.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Si, cerrar', cancelButtonText: 'Cancelar' }).then(r => { if (!r.isConfirmed) return; $.post(`${window.activityRoutes.base}/${id}/cerrar`, function (response) { tableActivities.ajax.reload(null, false); if (currentActivity?.id === id) loadActivityDetail(id, false); loadActivitySummary(); toast(response.message, 'success'); }).fail(showActionError); }); }
function annulActivity(id) { Swal.fire({ title: 'Anular actividad', text: '¿Esta seguro de anular esta actividad? Tambien se anularan sus movimientos relacionados.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Si, anular', cancelButtonText: 'Cancelar' }).then(r => { if (!r.isConfirmed) return; $.post(`${window.activityRoutes.base}/${id}/anular`, function (response) { tableActivities.ajax.reload(null, false); $('#activityDetailModal').modal('hide'); loadActivitySummary(); toast(response.message, 'success'); }).fail(showActionError); }); }
function loadActivitySummary() { $.get(window.activityRoutes.summary, s => { $('#activitySummaryOpen').text(s.open || 0); $('#activitySummaryClosed').text(s.closed || 0); $('#activitySummaryProfit').text(`S/ ${s.profit || '0.00'}`); $('#activitySummaryMonth').text(s.month_movements || 0); }); }
function updateMovementReferenceRequirement() { const method = $('#activityMovementForm [name="payment_method"]').val(); const req = ['yape', 'plin', 'transferencia'].includes(method); $('#activityMovementForm [name="payment_reference"]').prop('required', req); $('#activityMovementReferenceRequired').toggleClass('d-none', !req); }
function updateMovementSide() { const amount = parseFloat($('#activityMovementForm [name="amount"]').val()) || 0; const type = $('#activityMovementForm [name="type"]').val() || 'ingreso'; $('#activityMovementSideAmount').text(`S/ ${amount.toFixed(2)}`); $('#activityMovementSideType').text(type === 'egreso' ? 'Egreso' : 'Ingreso'); $('#activityMovementSideCode').text($('#activityMovementForm [name="code"]').val() || '-'); }
function setLink(selector, url, activeHtml, inactiveHtml) { const link = $(selector); if (url) link.removeClass('disabled').attr('href', url).html(activeHtml); else link.addClass('disabled').attr('href', '#').html(inactiveHtml); }
function showActivityErrors(errors) { showErrors('#activityForm', '#activity-error-messages', errors); }
function showMovementErrors(errors) { showErrors('#activityMovementForm', '#activity-movement-error-messages', errors); }
function showErrors(formSelector, boxSelector, errors) { let list = '<ul class="mb-0">'; $.each(errors, function (key, messages) { list += `<li>${messages[0]}</li>`; const input = $(`${formSelector} [name="${key}"]`); input.addClass('is-invalid'); input.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`); }); list += '</ul>'; $(boxSelector).removeClass('d-none').html(list); }
function clearActivityErrors() { clearErrors('#activityForm', '#activity-error-messages'); }
function clearMovementErrors() { clearErrors('#activityMovementForm', '#activity-movement-error-messages'); }
function clearErrors(formSelector, boxSelector) { $(boxSelector).addClass('d-none').empty(); $(`${formSelector} .is-invalid`).removeClass('is-invalid'); $(`${formSelector} .invalid-feedback`).remove(); }
function statusBadge(status, label) { return `<span class="badge badge-${status === 'anulada' || status === 'anulado' ? 'danger' : (status === 'cerrada' ? 'info' : 'success')}">${escapeHtml(label || status || '-')}</span>`; }
function setValue(form, name, value) { $(`${form} [name="${name}"]`).val(value || ''); }
function showActionError(xhr) { setLoading(false); Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error'); }
function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function toast(message, icon) { Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
function debounce(callback, wait) { let timeout; return function () { clearTimeout(timeout); timeout = setTimeout(callback, wait); }; }
function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
