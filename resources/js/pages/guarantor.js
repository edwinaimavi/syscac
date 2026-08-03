var divLoading = document.getElementById('divLoading');
let tableGuarantors;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableGuarantors = $('#tableGuarantors').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.guarantorRoutes.list,
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'member_code', orderable: false, searchable: false },
            { data: 'member_name', orderable: false, searchable: false },
            { data: 'member_dni', orderable: false, searchable: false },
            { data: 'guarantor_name', orderable: false, searchable: false },
            { data: 'guarantor_dni', orderable: false, searchable: false },
            { data: 'guarantor_contributions', orderable: false, searchable: false },
            { data: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadGuarantorSummary();
    tableGuarantors.on('draw', loadGuarantorSummary);
    initSelect2();

    $('#btnNewGuarantor').on('click', function () {
        resetGuarantorForm();
        fetchNextCode();
        $('#guarantorModal').modal('show');
    });

    $('#guarantorForm').on('submit', submitGuarantorForm);
    $('#guarantor_photo_path').on('change', previewGuarantorImage);
    $('#guarantorForm [name="type"]').on('change', updateTypeMode);
    $('#guarantor_member_id').on('change', fillFromSelectedMember);
    $('#btnSearchGuarantorDniMain').on('click', searchDni);

    $(document).on('click', '.editGuarantor', function () { loadGuarantorForm($(this).data('id')); });
    $(document).on('click', '.showGuarantor', function () { loadGuarantorDetail($(this).data('id')); });
    $(document).on('click', '.annulGuarantor', function () { annulGuarantor($(this).data('id')); });
});

function submitGuarantorForm(event) {
    event.preventDefault();
    clearErrors();
    setLoading(true);

    const form = $('#guarantorForm');
    const id = form.attr('data-id');
    const formData = new FormData(form[0]);
    let url = window.guarantorRoutes.store;

    if (id) {
        url = `${window.guarantorRoutes.base}/${id}`;
        formData.append('_method', 'PUT');
    }

    $.ajax({
        url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            setLoading(false);
            $('#guarantorModal').modal('hide');
            tableGuarantors.ajax.reload(null, false);
            loadGuarantorSummary();
            toast(response.message, 'success');
        },
        error: function (xhr) {
            setLoading(false);
            if (xhr.status === 422) {
                showErrors(xhr.responseJSON.errors || {});
                return;
            }

            if (xhr.status === 409) {
                Swal.fire('Atencion', xhr.responseJSON?.message || 'El DNI ya esta registrado.', 'warning');
                return;
            }

            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar el aval.', 'error');
        }
    });
}

function resetGuarantorForm() {
    $('#guarantorForm')[0].reset();
    $('#guarantorForm').removeAttr('data-id');
    clearErrors();
    $('#guarantorModalLabel').text('Nuevo aval');
    $('#guarantorSaveText').text('Guardar aval');
    $('#guarantorImgPreview').attr('src', window.guarantorRoutes.defaultAvatar);
    $('#guarantorSideCode').text('Pendiente');
    $('#guarantorSideStatus').removeClass('badge-danger badge-secondary').addClass('badge-success').text('Activo');
    $('#guarantorForm [name="code"]').val(window.guarantorRoutes.nextCodeValue || 'GAR-000001');
    $('#guarantorForm [name="type"]').val('externo');
    $('#guarantorForm [name="status"]').val('activo');
    $('#guarantor_member_id').val('').trigger('change');
    updateTypeMode();
}

function fetchNextCode() {
    $.get(window.guarantorRoutes.nextCode, response => {
        $('#guarantorForm [name="code"]').val(response.code || 'GAR-000001');
        $('#guarantorSideCode').text(response.code || 'Pendiente');
    });
}

function loadGuarantorForm(id) {
    setLoading(true);
    $.get(`${window.guarantorRoutes.base}/${id}/edit`, function (guarantor) {
        setLoading(false);
        resetGuarantorForm();
        $('#guarantorForm').attr('data-id', guarantor.id);
        $('#guarantorModalLabel').text('Editar aval');
        $('#guarantorSaveText').text('Actualizar aval');
        setFormValue('code', guarantor.code);
        setFormValue('type', guarantor.type);
        $('#guarantor_member_id').val(guarantor.member_id || '').trigger('change');
        setFormValue('dni', guarantor.dni);
        setFormValue('first_name', guarantor.first_name);
        setFormValue('last_name', guarantor.last_name);
        setFormValue('phone', guarantor.phone === '-' ? '' : guarantor.phone);
        setFormValue('address', guarantor.address === '-' ? '' : guarantor.address);
        setFormValue('occupation', guarantor.occupation);
        setFormValue('relationship', guarantor.relationship);
        setFormValue('status', guarantor.status);
        setFormValue('observation', guarantor.observation);
        $('#guarantorImgPreview').attr('src', guarantor.photo_url || window.guarantorRoutes.defaultAvatar);
        $('#guarantorSideCode').text(guarantor.code || '-');
        updateSideStatus(guarantor.status);
        updateTypeMode();
        $('#guarantorModal').modal('show');
    }).fail(showActionError);
}

function loadGuarantorDetail(id) {
    setLoading(true);
    $.get(`${window.guarantorRoutes.base}/${id}`, function (guarantor) {
        setLoading(false);
        fillDetail(guarantor);
        $('#guarantorDetailModal').modal('show');
    }).fail(showActionError);
}

function fillDetail(guarantor) {
    $('#detailGuarantorPhoto').attr('src', guarantor.photo_url || window.guarantorRoutes.defaultAvatar);
    $('#detailGuarantorFullName').text(guarantor.full_name || '-');
    $('#detailGuarantorCode').text(guarantor.code || '-');
    $('#detailGuarantorDni').text(guarantor.dni || '-');
    $('#detailGuarantorStatusBadge').html(statusBadge(guarantor.status, guarantor.status_label));
    $('#detailGuarantorType').text(guarantor.type_label || '-');
    $('#detailGuarantorPhone').text(guarantor.phone || '-');
    $('#detailGuarantorAddress').text(guarantor.address || '-');
    $('#detailGuarantorOccupation').text(guarantor.occupation || '-');
    $('#detailGuarantorRelationship').text(guarantor.relationship || '-');
    $('#detailGuarantorObservation').text(guarantor.observation || '-');
    $('#detailGuarantorCreatedAt').text(guarantor.created_at || '-');
    $('#detailGuarantorUpdatedAt').text(guarantor.updated_at || '-');

    const rows = (guarantor.related_members || []).map(member => `
        <tr>
            <td>${escapeHtml(member.code || '-')}</td>
            <td>${escapeHtml(member.full_name || '-')}</td>
            <td>${escapeHtml(member.dni || '-')}</td>
            <td>${escapeHtml(member.status || '-')}</td>
        </tr>
    `).join('');

    $('#detailGuarantorMembers').html(rows || '<tr><td colspan="4" class="text-center text-muted">Sin socios relacionados.</td></tr>');
}

function annulGuarantor(id) {
    Swal.fire({
        title: 'Anular aval',
        text: 'El aval quedara anulado y sus relaciones activas quedaran inactivas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, anular',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post(`${window.guarantorRoutes.base}/${id}/anular`, function (response) {
            tableGuarantors.ajax.reload(null, false);
            loadGuarantorSummary();
            toast(response.message, 'success');
        }).fail(showActionError);
    });
}

function searchDni() {
    const dni = $('#guarantorForm [name="dni"]').val();
    if (!/^\d{8}$/.test(dni)) {
        Swal.fire('Atencion', 'Ingrese un DNI valido de 8 digitos.', 'warning');
        return;
    }

    $.get(`${window.guarantorRoutes.verifyDni}/${dni}`, function (response) {
        if (response.status === 'member') {
            Swal.fire('Atencion', 'Este DNI ya pertenece a un socio registrado.', 'warning');
            return;
        }

        if (response.status === 'external') {
            Swal.fire('Atencion', 'Este aval externo ya esta registrado.', 'warning');
            return;
        }

        $.get(`${window.guarantorRoutes.consultarDni}/${dni}`, function (dniResponse) {
            const person = dniResponse.data || {};
            setFormValue('first_name', person.nombres || '');
            setFormValue('last_name', `${person.apellidoPaterno || ''} ${person.apellidoMaterno || ''}`.trim());
            toast('Datos encontrados correctamente.', 'success');
        }).fail(showActionError);
    }).fail(showActionError);
}

function updateTypeMode() {
    const isMember = $('#guarantorForm [name="type"]').val() === 'socio';
    $('#guarantorMemberBox').toggleClass('d-none', !isMember);
    $('#guarantorForm [name="dni"], #guarantorForm [name="first_name"], #guarantorForm [name="last_name"]').prop('readonly', isMember);
    if (isMember) fillFromSelectedMember();
}

function fillFromSelectedMember() {
    if ($('#guarantorForm [name="type"]').val() !== 'socio') return;
    const option = $('#guarantor_member_id option:selected');
    if (!option.val()) return;
    const text = option.text().trim().split(' - ');
    setFormValue('dni', option.data('dni') || text[1] || '');
    setFormValue('first_name', option.attr('data-first-name') || '');
    setFormValue('last_name', option.attr('data-last-name') || '');
}

function previewGuarantorImage() {
    if (!this.files || !this.files.length) return;
    $('#guarantorImgPreview').attr('src', URL.createObjectURL(this.files[0]));
}

function loadGuarantorSummary() {
    $.get(window.guarantorRoutes.summary, summary => {
        $('#guarantorSummaryTotal').text(summary.total || 0);
        $('#guarantorSummaryExternal').text(summary.externals || 0);
        $('#guarantorSummaryMember').text(summary.members || 0);
        $('#guarantorSummaryActive').text(summary.active || 0);
    });
}

function initSelect2() {
    if ($.fn.select2) {
        $('#guarantor_member_id').select2({ width: '100%', theme: 'bootstrap4', dropdownParent: $('#guarantorModal') });
    }
}

function showErrors(errors) {
    let list = '<ul class="mb-0">';
    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`#guarantorForm [name="${key}"]`);
        input.addClass('is-invalid');
        input.after(`<div class="invalid-feedback d-block">${messages[0]}</div>`);
    });
    list += '</ul>';
    $('#guarantor-error-messages').removeClass('d-none').html(list);
}

function clearErrors() {
    $('#guarantor-error-messages').addClass('d-none').empty();
    $('#guarantorForm .is-invalid').removeClass('is-invalid');
    $('#guarantorForm .invalid-feedback').remove();
}

function setFormValue(name, value) { $(`#guarantorForm [name="${name}"]`).val(value || ''); }
function updateSideStatus(status) {
    const badge = $('#guarantorSideStatus');
    badge.removeClass('badge-success badge-danger badge-secondary');
    badge.addClass(status === 'anulado' ? 'badge-danger' : (status === 'inactivo' ? 'badge-secondary' : 'badge-success'));
    badge.text(status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Activo');
}
function statusBadge(status, label) { return `<span class="badge badge-${status === 'anulado' ? 'danger' : (status === 'inactivo' ? 'secondary' : 'success')}">${escapeHtml(label || status || '-')}</span>`; }
function showActionError(xhr) { setLoading(false); Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error'); }
function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function toast(message, icon) { Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
