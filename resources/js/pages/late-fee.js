let tableLateFee;
const loading = () => document.getElementById('divLoading');

document.addEventListener('DOMContentLoaded', () => {
    $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});
    tableLateFee = $('#tableLateFee').DataTable({
        processing: true, serverSide: true, ajax: window.lateFeeRoutes.list, responsive: true,
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'code', name: 'id'}, {data: 'name', name: 'name'},
            {data: 'grace_days', name: 'grace_days'}, {data: 'calculation_type', name: 'calculation_type'},
            {data: 'value', name: 'value'}, {data: 'max_amount', name: 'max_amount'},
            {data: 'auto_apply', name: 'auto_apply'}, {data: 'allow_waiver', name: 'allow_waiver'},
            {data: 'is_active', name: 'is_active'}, {data: 'audit', name: 'updated_at', searchable: false},
            {data: 'actions', name: 'actions', orderable: false, searchable: false}
        ],
        language: {url: '/vendor/datatables/js/i18n/es-ES.json'}
    });
    loadSummary();

    $('#btnNewLateFee').on('click', () => { resetForm(); $('#lateFeeModal').modal('show'); });
    $('#lateFeeModal').on('hidden.bs.modal', resetForm);
    $('#lateFeeForm [name="value"], #lateFeeForm [name="calculation_type"]').on('input change', updateSideValue);
    $('#lateFeeForm [name="grace_days"]').on('input', function () { $('#lateFeeSideGrace').text(`${this.value || 0} días`); });
    $('#lateFeeForm [name="is_active"]').on('change', function () { $('#lateFeeSideStatus').text(this.value === '1' ? 'Activo' : 'Inactivo'); });

    $('#lateFeeForm').on('submit', function (event) {
        event.preventDefault(); clearErrors(); setLoading(true);
        const id = $(this).attr('data-id'); const data = new FormData(this);
        if (id) data.append('_method', 'PUT');
        $.ajax({url: id ? `${window.lateFeeRoutes.base}/${id}` : window.lateFeeRoutes.store, type: 'POST', data, processData: false, contentType: false})
            .done(response => { $('#lateFeeModal').modal('hide'); refresh(); notifyLateFee('success', response.message); })
            .fail(xhr => xhr.status === 422 ? showErrors(xhr.responseJSON.errors) : notifyLateFee('error', xhr.responseJSON?.message || 'No se pudo guardar la configuración.'))
            .always(() => setLoading(false));
    });

    $(document).on('click', '.editLateFee', function () { fetchSetting($(this).data('id'), fillForm); });
    $(document).on('click', '.showLateFee', function () { fetchSetting($(this).data('id'), fillDetail); });
    $(document).on('click', '.toggleLateFee', function () {
        const id = $(this).data('id'), activate = Number($(this).data('active')) !== 1;
        Swal.fire({title: activate ? '¿Activar configuración?' : '¿Desactivar configuración?', text: activate ? 'La configuración activa anterior se desactivará automáticamente.' : 'El sistema puede quedar sin mora automática activa.', icon: 'warning', showCancelButton: true, confirmButtonText: activate ? 'Sí, activar' : 'Sí, desactivar', cancelButtonText: 'Cancelar'})
            .then(result => { if (!result.isConfirmed) return; setLoading(true); $.post(`${window.lateFeeRoutes.base}/${id}/activar`, {active: activate ? 1 : 0}).done(r => { refresh(); notifyLateFee('success', r.message); }).fail(showRequestError).always(() => setLoading(false)); });
    });
    $(document).on('click', '.deleteLateFee', function () {
        const id = $(this).data('id');
        Swal.fire({title: '¿Eliminar configuración?', text: 'El registro dejará de aparecer en el listado.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'})
            .then(result => { if (!result.isConfirmed) return; setLoading(true); $.ajax({url: `${window.lateFeeRoutes.base}/${id}`, type: 'DELETE'}).done(r => { refresh(); notifyLateFee('success', r.message); }).fail(showRequestError).always(() => setLoading(false)); });
    });
});

function fetchSetting(id, callback) { setLoading(true); $.get(`${window.lateFeeRoutes.base}/${id}`).done(callback).fail(showRequestError).always(() => setLoading(false)); }
function refresh() { tableLateFee.ajax.reload(null, false); loadSummary(); }
function loadSummary() { $.get(window.lateFeeRoutes.summary).done(data => { $('#lateFeeSummaryTotal').text(data.total); $('#lateFeeSummaryActive').text(data.active); $('#lateFeeSummaryGrace').text(data.grace_days); $('#lateFeeSummaryType').text(data.type); }); }
function resetForm() { const form = $('#lateFeeForm'); form[0].reset(); form.removeAttr('data-id'); form.find('[name="grace_days"]').val(0); form.find('[name="is_active"], [name="auto_apply"]').val('1'); form.find('[name="allow_waiver"]').val('0'); $('#lateFeeModalLabel').text('Nueva configuración'); $('#lateFeeSaveText').text('Guardar configuración'); $('#lateFeeCode').val('Automático'); $('#lateFeeSideCode').text('Código automático'); $('#lateFeeSideGrace').text('0 días'); $('#lateFeeSideStatus').text('Activo'); updateSideValue(); clearErrors(); }
function fillForm(data) { resetForm(); const form = $('#lateFeeForm'); form.attr('data-id', data.id); Object.entries(data).forEach(([key, value]) => form.find(`[name="${key}"]`).val(typeof value === 'boolean' ? (value ? '1' : '0') : value)); $('#lateFeeModalLabel').text('Editar configuración'); $('#lateFeeSaveText').text('Actualizar configuración'); $('#lateFeeCode').val(data.code); $('#lateFeeSideCode').text(data.code); $('#lateFeeSideGrace').text(`${data.grace_days} días`); $('#lateFeeSideStatus').text(data.is_active ? 'Activo' : 'Inactivo'); updateSideValue(); $('#lateFeeModal').modal('show'); }
function fillDetail(d) { $('#detailLateFeeCode').text(d.code); $('#detailLateFeeName').text(d.name); $('#detailLateFeeStatus').html(`<span class="badge badge-${d.is_active ? 'success' : 'secondary'} px-3 py-2">${d.is_active ? 'Activo' : 'Inactivo'}</span>`); $('#detailLateFeeGrace').text(`${d.grace_days} días`); $('#detailLateFeeType').text(d.type_label); $('#detailLateFeeValue').text(d.formatted_value); $('#detailLateFeeMax').text(d.max_amount === null ? 'Sin límite' : `S/ ${Number(d.max_amount).toFixed(2)}`); $('#detailLateFeeAuto').text(d.auto_apply ? 'Sí' : 'No'); $('#detailLateFeeWaiver').text(d.allow_waiver ? 'Sí' : 'No'); $('#detailLateFeeCreatedBy').text(d.created_by_name); $('#detailLateFeeCreatedAt').text(d.created_at_label || '-'); $('#detailLateFeeUpdatedBy').text(d.updated_by_name); $('#detailLateFeeUpdatedAt').text(d.updated_at_label || '-'); $('#detailLateFeeObservation').text(d.observation || 'Sin observación.'); $('#lateFeeDetailModal').modal('show'); }
function updateSideValue() { const value = Number($('#lateFeeForm [name="value"]').val() || 0), percent = $('#lateFeeForm [name="calculation_type"]').val() === 'percentage_daily'; $('#lateFeeSideValue').text(percent ? `${value.toFixed(4)} %` : `S/ ${value.toFixed(2)}`); }
function clearErrors() { $('#lateFeeErrors').addClass('d-none').empty(); $('#lateFeeForm .is-invalid').removeClass('is-invalid'); }
function showErrors(errors) { clearErrors(); const box = $('#lateFeeErrors').removeClass('d-none'); Object.entries(errors || {}).forEach(([name, messages]) => { box.append(`<div>${messages[0]}</div>`); $(`#lateFeeForm [name="${name}"]`).addClass('is-invalid'); }); }
function showRequestError(xhr) { notifyLateFee('error', xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'No se pudo completar la operación.'); }
function setLoading(visible) { if (loading()) loading().style.display = visible ? 'flex' : 'none'; }
function notifyLateFee(type, message) {
    if (window.toastr && typeof window.toastr[type] === 'function') { window.toastr[type](message); return; }
    if (window.Swal) {
        const icon = ['error', 'warning', 'info', 'success'].includes(type) ? type : 'success';
        window.Swal.fire({ icon, title: message, toast: type !== 'error', position: type !== 'error' ? 'top-end' : 'center', timer: type === 'error' ? undefined : 1800, timerProgressBar: type !== 'error', showConfirmButton: type === 'error' });
        return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
}
