var divLoading = document.getElementById('divLoading');
let tableMember;
let childIndex = 0;
let enrollmentVoucherObjectUrl = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    tableMember = $('#tableMember').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.memberRoutes.list,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex' },
            { data: 'photo', name: 'photo', orderable: false, searchable: false },
            { data: 'code', name: 'code', defaultContent: '-' },
            { data: 'dni', name: 'dni' },
            { data: 'full_name', name: 'full_name' },
            { data: 'phone', name: 'phone', defaultContent: '-' },
            { data: 'member_type', name: 'member_type', orderable: false, searchable: false },
            { data: 'civil_status', name: 'civil_status' },
            { data: 'admission_date', name: 'admission_date' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });

    initGuarantorSelect();

    $('#memberModal').on('show.bs.modal', function (event) {
        if (event.relatedTarget && !$(event.relatedTarget).hasClass('editMember')) {
            resetMemberForm();
            fetchNextMemberCode();
        }
    });

    $('#memberModal').on('hidden.bs.modal', function () {
        resetMemberForm();
    });

    $('#memberModal').on('shown.bs.modal', function () {
        if (!$('#memberForm').attr('data-id')) {
            $('[name="dni"]').trigger('focus');
        }
    });

    $('#btnAddChild').on('click', function () {
        addChildRow();
    });
    $('#btnAddBeneficiary').on('click', function () {
        addBeneficiaryRow();
    });

    $('#btnSearchDni').on('click', function () {
        searchDni();
    });

    $('#memberForm [name="status"]').on('change', function () {
        updateLeftStatus($(this).val());
    });

    $('#memberForm [name="admission_date"], #memberForm [name="member_type_selected"]').on('change', updateMemberType);
    $('#memberForm [name="birth_date"]').on('input change', updateMinorWarning);
    $('#memberForm [name="enrollment_payment_method"]').on('change', function () {
        updateEnrollmentReferenceRequirement();
    });
    $('#enrollment_voucher').on('change', previewEnrollmentVoucher);
    $(document).on('click', '#btnRemoveEnrollmentVoucher', clearSelectedEnrollmentVoucher);

    $('#btnNewExternalGuarantor').on('click', function () {
        resetGuarantorForm();
        $('#externalGuarantorModal').modal('show');
    });

    $('#btnSearchGuarantorDni').on('click', function () {
        searchGuarantorDni();
    });

    $('#externalGuarantorForm').on('submit', function (e) {
        e.preventDefault();
        storeExternalGuarantor(this);
    });

    $(document).on('click', '.removeChild', function () {
        $(this).closest('.member-child-row').remove();
        updateChildrenEmptyState();
    });
    $(document).on('click', '.removeBeneficiary', function () {
        $(this).closest('.member-beneficiary-row').remove();
        updateBeneficiarySummary();
    });
    $(document).on('input change', '.beneficiary-percentage, .beneficiary-birth-date', updateBeneficiarySummary);

    $('#memberForm').on('submit', function (e) {
        e.preventDefault();
        clearValidationErrors();
        divLoading.style.display = 'flex';

        const form = this;
        const id = $(form).attr('data-id');
        const formData = new FormData(form);
        let url = window.memberRoutes.store;

        if (id) {
            url = `${window.memberRoutes.base}/${id}`;
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                divLoading.style.display = 'none';
                $('#memberModal').modal('hide');
                tableMember.ajax.reload(null, false);
                toast(response.message, 'success');
            },
            error: function (xhr) {
                divLoading.style.display = 'none';
                if (xhr.status === 422) {
                    showValidationErrors(xhr.responseJSON.errors);
                    return;
                }

                Swal.fire('Error', 'No se pudo registrar el socio.', 'error');
            }
        });
    });

    $(document).on('click', '.editMember', function () {
        const id = $(this).data('id');
        divLoading.style.display = 'flex';

        $.get(`${window.memberRoutes.base}/${id}/edit`, function (member) {
            divLoading.style.display = 'none';
            fillMemberForm(member);
            $('#memberModalLabel').text('Editar socio');
            $('#memberModal').modal('show');
        }).fail(function () {
            divLoading.style.display = 'none';
            Swal.fire('Error', 'No se encontro el socio solicitado.', 'error');
        });
    });

    $(document).on('click', '.showMember', function () {
        const id = $(this).data('id');
        divLoading.style.display = 'flex';

        $.get(`${window.memberRoutes.base}/${id}`, function (member) {
            divLoading.style.display = 'none';
            fillDetailModal(member);
            $('#memberDetailModal').modal('show');
        }).fail(function () {
            divLoading.style.display = 'none';
            Swal.fire('Error', 'No se encontro el socio solicitado.', 'error');
        });
    });

    $(document).on('click', '.deleteMember', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Estas seguro?',
            text: 'Esta accion eliminara el registro del listado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: `${window.memberRoutes.base}/${id}`,
                type: 'DELETE',
                success: function (response) {
                    tableMember.ajax.reload(null, false);
                    toast(response.message, 'success');
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'No se pudo eliminar el socio.';
                    Swal.fire('Error', message, 'error');
                }
            });
        });
    });
});

function resetMemberForm() {
    const form = $('#memberForm');
    form[0].reset();
    form.removeAttr('data-id');
    form.find('[name="reentry_from_member_id"]').val('');
    form.find('[name="reentry_confirmed"]').val('0');
    $('#memberModalLabel').text('Nuevo socio');
    $('#memberImgPreview').attr('src', window.memberRoutes.defaultAvatar);
    $('#childrenContainer').empty();
    $('#beneficiariesContainer').empty();
    updateChildrenEmptyState();
    updateBeneficiarySummary();
    $('#left_member_code').text('Pendiente');
    $('#left_member_status').removeClass('badge-danger badge-secondary').addClass('badge-success').text('Vigente');
    $('#memberSaveText').text('Guardar socio');
    $('#guarantor_option').val('').trigger('change');
    $('[name="member_type_selected"]').val('nuevo');
    $('[name="enrollment_date"]').val(new Date().toISOString().slice(0, 10));
    $('#memberEnrollmentStatus').text('');
    $('#enrollmentVoucherFileName').text('Sin archivo seleccionado.');
    $('#enrollmentVoucherPreview').empty();
    $('#enrollmentCurrentVoucherPreview').empty();
    $('#enrollmentUploadTitle').text('Subir voucher o comprobante');
    $('#enrollmentCurrentVoucher').addClass('d-none');
    $('#enrollmentNoCurrentVoucher').removeClass('d-none');
    revokeEnrollmentVoucherObjectUrl();
    updateMemberType();
    updateMinorWarning();
    childIndex = 0;
    clearValidationErrors();
}

function fetchNextMemberCode() {
    $.get(window.memberRoutes.nextCode, function (response) {
        const code = response.code || '';
        $('[name="code"]').val(code);
        $('#left_member_code').text(code || 'Pendiente');
    }).fail(function (xhr) {
        const message = xhr.responseJSON?.message || 'El codigo del socio no pudo generarse correctamente.';
        $('[name="code"]').val('');
        $('#left_member_code').text('Pendiente');
        Swal.fire('Error', message, 'error');
    });
}

function fillMemberForm(member) {
    resetMemberForm();
    const form = $('#memberForm');
    form.attr('data-id', member.id);

    setValue('code', member.code);
    setValue('dni', member.dni);
    setValue('first_name', member.first_name);
    setValue('last_name', member.last_name);
    setValue('birth_date', member.birth_date);
    setValue('admission_date', member.admission_date);
    setValue('member_type_selected', member.member_type || 'nuevo');
    setValue('retirement_date', member.retirement_date);
    setValue('phone', member.phone);
    setValue('current_job', member.current_job);
    setValue('address', member.address);
    setValue('status', member.status);
    setValue('civil_status', member.civil_status);
    setValue('spouse_name', member.spouse_name);
    if (member.guarantor_option && member.guarantor) {
        const guarantorLabel = `${member.guarantor.code || 'SOCIO'} - ${member.guarantor.dni || '-'} - ${member.guarantor.full_name || '-'}`;
        selectGuarantorOption(member.guarantor_option, guarantorLabel, 'socio');
    } else {
        $('#guarantor_option').val('').trigger('change');
    }
    setValue('observation', member.observation);
    fillEnrollmentForm(member.enrollment);
    updateMemberType();
    updateMinorWarning();

    $('#memberImgPreview').attr('src', member.photo_url || window.memberRoutes.defaultAvatar);
    $('#left_member_code').text(member.code || 'Sin codigo');
    $('#memberSaveText').text('Actualizar socio');
    updateLeftStatus(member.status);

    if (member.relatives && member.relatives.length) {
        member.relatives.forEach((relative) => addChildRow(relative));
    }
    if (member.beneficiaries && member.beneficiaries.length) {
        member.beneficiaries.forEach((beneficiary) => addBeneficiaryRow(beneficiary));
    }

    updateChildrenEmptyState();
}

function updateMemberType() {
    const admission = $('#memberForm [name="admission_date"]').val();
    const selected = $('#memberForm [name="member_type_selected"]').val() || 'nuevo';
    let calculated = 'nuevo';
    if (admission) {
        const anniversary = new Date(`${admission}T00:00:00`);
        anniversary.setFullYear(anniversary.getFullYear() + 1);
        calculated = anniversary <= new Date() ? 'antiguo' : 'nuevo';
    }
    $('#memberTypeCalculated').text(calculated === 'antiguo' ? 'Antiguo' : 'Nuevo');
    const applies = calculated === 'nuevo';
    $('#memberEnrollmentSection').toggleClass('d-none', !applies);
    const fields = $('#memberEnrollmentSection').find('input, select, textarea');
    fields.prop('disabled', !applies);
    $('#memberForm [name="enrollment_date"], #memberForm [name="enrollment_amount"], #memberForm [name="enrollment_payment_method"]').prop('required', applies);
    if (!applies) {
        fields.prop('required', false);
    } else {
        updateEnrollmentReferenceRequirement();
    }
    const differs = selected !== calculated;
    const message = calculated === 'antiguo'
        ? 'Por la fecha de ingreso, este socio sera considerado antiguo.'
        : 'Por la fecha de ingreso, este socio sera considerado nuevo.';
    $('#memberTypeWarning').toggleClass('d-none', !differs).text(differs ? message : '');
}

function updateMinorWarning() {
    const birthDate = $('#memberForm [name="birth_date"]').val();
    const age = birthDate ? calculateAge(birthDate) : null;
    $('#memberMinorWarning').toggleClass('d-none', age === null || age >= 18);
}

function updateEnrollmentReferenceRequirement() {
    const sectionEnabled = !$('#memberEnrollmentSection').hasClass('d-none');
    const method = $('#memberForm [name="enrollment_payment_method"]').val();
    $('#memberForm [name="enrollment_payment_reference"]').prop('required', sectionEnabled && ['yape', 'plin', 'transferencia'].includes(method));
}

function fillEnrollmentForm(enrollment) {
    if (!enrollment) {
        $('#memberEnrollmentStatus').text('Inscripcion pendiente');
        return;
    }
    setValue('enrollment_date', enrollment.enrollment_date);
    setValue('enrollment_amount', enrollment.amount || '50.00');
    setValue('enrollment_payment_method', enrollment.payment_method);
    setValue('enrollment_payment_reference', enrollment.payment_reference);
    setValue('enrollment_observation', enrollment.observation);
    $('#memberEnrollmentStatus').text('Inscripcion registrada');
    if (enrollment.voucher_url) {
        $('#enrollmentCurrentVoucher').removeClass('d-none');
        $('#enrollmentNoCurrentVoucher').addClass('d-none');
        $('#enrollmentCurrentVoucherView').attr('href', enrollment.voucher_view_url || enrollment.voucher_url);
        $('#enrollmentCurrentVoucherDownload').attr('href', enrollment.voucher_url);
        $('#enrollmentUploadTitle').text('Cambiar voucher o comprobante');
        $('#enrollmentCurrentVoucherPreview').html(renderVoucherPreview({
            url: enrollment.voucher_public_url,
            name: enrollment.voucher_file_name,
            mime: enrollment.voucher_mime_type,
            extension: enrollment.voucher_extension,
            compact: true
        }));
    }
    updateEnrollmentReferenceRequirement();
}

function previewEnrollmentVoucher(event) {
    const file = event.target.files[0];
    $('#enrollmentVoucherPreview').empty();
    revokeEnrollmentVoucherObjectUrl();
    if (!file) return;
    enrollmentVoucherObjectUrl = URL.createObjectURL(file);
    $('#enrollmentCurrentVoucher, #enrollmentNoCurrentVoucher').addClass('d-none');
    if (file.type === 'application/pdf') {
        $('#enrollmentVoucherPreview').html(renderVoucherPreview({ url: enrollmentVoucherObjectUrl, downloadUrl: enrollmentVoucherObjectUrl, name: file.name, mime: file.type, removable: true }));
        return;
    }
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (loadEvent) {
            $('#enrollmentVoucherPreview').html(renderVoucherPreview({ url: loadEvent.target.result, downloadUrl: enrollmentVoucherObjectUrl, name: file.name, mime: file.type, removable: true }));
        };
        reader.readAsDataURL(file);
    }
}

function clearSelectedEnrollmentVoucher() {
    $('#enrollment_voucher').val('');
    $('#enrollmentVoucherPreview').empty();
    revokeEnrollmentVoucherObjectUrl();
    if ($('#enrollmentCurrentVoucherView').attr('href')) $('#enrollmentCurrentVoucher').removeClass('d-none');
    else $('#enrollmentNoCurrentVoucher').removeClass('d-none');
}

function revokeEnrollmentVoucherObjectUrl() {
    if (enrollmentVoucherObjectUrl) URL.revokeObjectURL(enrollmentVoucherObjectUrl);
    enrollmentVoucherObjectUrl = null;
}

function addChildRow(relative = {}) {
    const index = childIndex++;
    const row = `
        <div class="member-child-row">
            <div class="form-row align-items-end">
                <div class="form-group col-md-5 mb-md-0">
                    <label>Nombre del hijo</label>
                    <input type="text" class="form-control form-control-sm" name="relatives[${index}][name]" value="${escapeHtml(relative.name || '')}" placeholder="Nombre del hijo">
                    <input type="hidden" name="relatives[${index}][relationship]" value="hijo">
                </div>
                <div class="form-group col-md-3 mb-md-0">
                    <label>Fecha nacimiento</label>
                    <input type="date" class="form-control form-control-sm" name="relatives[${index}][birth_date]" value="${escapeHtml(relative.birth_date || '')}">
                </div>
                <div class="form-group col-md-3 mb-md-0">
                    <label>Observacion</label>
                    <input type="text" class="form-control form-control-sm" name="relatives[${index}][observation]" value="${escapeHtml(relative.observation || '')}" placeholder="Opcional">
                </div>
                <div class="form-group col-md-1 mb-0 text-right">
                    <button type="button" class="btn member-remove-child-btn removeChild" title="Quitar hijo">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>`;

    $('#childrenContainer').append(row);
    updateChildrenEmptyState();
}

let beneficiaryIndex = 0;
function addBeneficiaryRow(beneficiary = {}) {
    const index = beneficiaryIndex++;
    const relationship = beneficiary.relationship || 'otro';
    const option = (value, label) => `<option value="${value}"${relationship === value ? ' selected' : ''}>${label}</option>`;
    $('#beneficiariesContainer').append(`
        <div class="member-child-row member-beneficiary-row">
            <input type="hidden" name="beneficiaries[${index}][id]" value="${escapeHtml(beneficiary.id || '')}">
            <div class="form-row">
                <div class="form-group col-md-4"><label>Nombres y apellidos *</label><input class="form-control form-control-sm" name="beneficiaries[${index}][full_name]" value="${escapeHtml(beneficiary.full_name || '')}" required></div>
                <div class="form-group col-md-2"><label>DNI</label><input class="form-control form-control-sm" name="beneficiaries[${index}][dni]" value="${escapeHtml(beneficiary.dni || '')}" maxlength="8" inputmode="numeric"></div>
                <div class="form-group col-md-3"><label>Parentesco *</label><select class="form-control form-control-sm" name="beneficiaries[${index}][relationship]" required>${option('conyuge','Esposa / Cónyuge')}${option('hijo','Hijo(a)')}${option('padre','Padre')}${option('madre','Madre')}${option('hermano','Hermano(a)')}${option('otro','Otro')}</select></div>
                <div class="form-group col-md-2"><label>Porcentaje *</label><div class="input-group input-group-sm"><input type="number" min="0.01" max="100" step="0.01" class="form-control beneficiary-percentage" name="beneficiaries[${index}][percentage]" value="${escapeHtml(beneficiary.percentage || '')}" required><div class="input-group-append"><span class="input-group-text">%</span></div></div></div>
                <div class="form-group col-md-1 d-flex align-items-end"><button type="button" class="btn member-remove-child-btn removeBeneficiary mb-1"><i class="fas fa-times"></i></button></div>
                <div class="form-group col-md-3"><label>Teléfono</label><input class="form-control form-control-sm" name="beneficiaries[${index}][phone]" value="${escapeHtml(beneficiary.phone || '')}"></div>
                <div class="form-group col-md-4"><label>Dirección</label><input class="form-control form-control-sm" name="beneficiaries[${index}][address]" value="${escapeHtml(beneficiary.address || '')}"></div>
                <div class="form-group col-md-2"><label>Fecha nacimiento</label><input type="date" class="form-control form-control-sm beneficiary-birth-date" name="beneficiaries[${index}][birth_date]" value="${escapeHtml(beneficiary.birth_date || '')}"></div>
                <div class="form-group col-md-3"><label>Observación</label><input class="form-control form-control-sm" name="beneficiaries[${index}][observation]" value="${escapeHtml(beneficiary.observation || '')}"></div>
                <div class="col-12 beneficiary-minor-warning alert alert-warning py-1 d-none">Beneficiario menor de edad. Validar representante legal al momento del pago.</div>
            </div>
        </div>`);
    updateBeneficiarySummary();
}

function updateBeneficiarySummary() {
    const rows = $('#beneficiariesContainer .member-beneficiary-row');
    let total = 0;
    rows.each(function () {
        total += parseFloat($(this).find('.beneficiary-percentage').val()) || 0;
        const birthDate = $(this).find('.beneficiary-birth-date').val();
        const age = birthDate ? calculateAge(birthDate) : null;
        $(this).find('.beneficiary-minor-warning').toggleClass('d-none', age === null || age >= 18);
    });
    total = Math.round(total * 100) / 100;
    const pending = Math.max(0, Math.round((100 - total) * 100) / 100);
    const summary = $('#beneficiarySummary').removeClass('alert-success alert-warning alert-danger');
    let message = 'Agregue beneficiarios para definir la distribución.';
    if (rows.length && total === 100) {
        summary.addClass('alert-success'); message = 'Distribución completa: 100% asignado.';
    } else if (total > 100) {
        summary.addClass('alert-danger'); message = `La suma de beneficiarios no puede superar el 100%. Excede ${(total - 100).toFixed(2)}%.`;
    } else {
        summary.addClass('alert-warning');
        if (rows.length) message = `La suma de beneficiarios debe completar el 100%. Falta asignar ${pending.toFixed(2)}%.`;
    }
    $('#beneficiaryTotal').text(`${total.toFixed(2)}%`);
    $('#beneficiaryPending').text(`${pending.toFixed(2)}%`);
    $('#beneficiaryMessage').text(message);
    $('#beneficiariesEmptyState').toggleClass('d-none', rows.length > 0);
}

function fillDetailModal(member) {
    const reentryMessages = [];
    if (member.reentry_from) reentryMessages.push(`Reingreso de socio anterior: ${escapeHtml(member.reentry_from.code)}`);
    if (member.subsequent_reentries?.length) reentryMessages.push(`Este socio tuvo reingreso posterior: ${member.subsequent_reentries.map(item => escapeHtml(item.code)).join(', ')}`);
    if (member.withdrawal_pending) reentryMessages.push('Cierre pendiente: socio en proceso de retiro.');
    $('#detailReentryNotice').toggleClass('d-none', !reentryMessages.length).html(reentryMessages.join('<br>'));
    $('#detailPhoto').attr('src', member.photo_url || window.memberRoutes.defaultAvatar);
    $('#detailFullName').text(member.full_name || '-');
    $('#detailFirstName').text(member.first_name || '-');
    $('#detailLastName').text(member.last_name || '-');
    $('#detailStatusBadge').html(statusBadge(member.status, member.status_label));
    $('#detailCode').text(member.code || 'Sin codigo');
    $('#detailDni').text(member.dni || '-');
    $('#detailAge').text(member.age !== null ? `${member.age} años` : '-');
    $('#detailMinorWarning').toggleClass('d-none', !member.is_minor);
    $('#detailPhone').text(member.phone || '-');
    $('#detailBirthDate').text(member.birth_date_formatted || '-');
    $('#detailAdmissionDate').text(member.admission_date_formatted || '-');
    $('#detailMembershipTime').text(member.membership_time || '-');
    $('#detailMemberTypeSelected').text(formatMemberType(member.member_type_selected));
    $('#detailMemberTypeCalculated').text(formatMemberType(member.member_type_calculated));
    $('#detailMemberStatus').text(member.status_label || '-');
    $('#detailRetirementDate').text(member.retirement_date_formatted || '-');
    $('#detailCurrentJob').text(member.current_job || '-');
    $('#detailAddress').text(member.address || '-');
    $('#detailCivilStatus').text(formatStatus(member.civil_status));
    $('#detailSpouse').text(member.spouse_name || '-');
    fillGuarantorDetail(member.guarantor);
    fillFinancialSummary(member.financial_summary);
    fillCreditHistory(member.credit_history);
    $('#detailCreditEventsButton').toggleClass('d-none', !member.credit_history_url).data('url', member.credit_history_url || '');
    $('#detailCreditRecalculateButton').toggleClass('d-none', !member.credit_history_recalculate_url).data('url', member.credit_history_recalculate_url || '');
    $('#detailCreditEventsWrap').addClass('d-none');
    fillEnrollmentDetail(member);
    fillAccountClosureDetail(member.account_closure);
    $('#detailCreatedAt').text(member.created_at || '-');
    $('#detailCreatedBy').text(member.created_by_name || 'No registrado');
    $('#detailUpdatedAt').text(member.updated_at || '-');
    $('#detailUpdatedBy').text(member.updated_by_name || 'No registrado');
    $('#detailObservation').text(member.observation || '-');

    if (member.relatives && member.relatives.length) {
        const children = member.relatives.map((relative) => {
            const date = relative.birth_date_formatted || 'Sin fecha';
            const age = relative.birth_date ? calculateAge(relative.birth_date) : null;
            const ageText = age !== null ? ` - ${age} años` : '';

            return `
                <div class="member-detail-child">
                    <strong>${escapeHtml(relative.name || 'Sin nombre')}</strong>
                    <span>${escapeHtml(date)}${ageText}</span>
                </div>`;
        }).join('');
        $('#detailChildren').html(children);
    } else {
        $('#detailChildren').html('<div class="member-detail-empty">No se registraron hijos.</div>');
    }

    if (member.beneficiaries && member.beneficiaries.length) {
        const rows = member.beneficiaries.map(item => `<tr><td>${escapeHtml(item.full_name)}</td><td>${escapeHtml(item.dni || '-')}</td><td>${escapeHtml(item.relationship_label || item.relationship)}</td><td>${escapeHtml(item.phone || '-')}</td><td><strong>${Number(item.percentage).toFixed(2)}%</strong>${item.is_minor ? '<small class="d-block text-warning">Menor de edad</small>' : ''}</td><td>${escapeHtml(item.observation || '-')}</td></tr>`).join('');
        $('#detailBeneficiaries').html(`<table class="table table-sm table-hover mb-0"><thead><tr><th>Nombre</th><th>DNI</th><th>Parentesco</th><th>Teléfono</th><th>Porcentaje</th><th>Observación</th></tr></thead><tbody>${rows}</tbody></table>`);
    } else {
        $('#detailBeneficiaries').html('<div class="member-detail-empty">No se registraron beneficiarios.</div>');
    }
}

function fillCreditHistory(history) {
    history = history || {};
    const badge = { verde: 'success', azul: 'primary', amarillo: 'warning', naranja: 'warning', rojo: 'danger' }[history.color] || 'secondary';
    $('#detailCreditStatus').attr('class', `badge badge-${badge} px-3 py-2`).text(history.label || 'Sin calcular');
    $('#detailCreditScore').text(history.score !== undefined ? `${history.score} / 100` : '-');
    $('#detailCreditLoans').text(`${history.total_loans || 0} / ${history.paid_loans || 0}`);
    $('#detailCreditOnTime').text(history.on_time || 0);
    $('#detailCreditLate').text(`${history.mild_late || 0} / ${history.serious_late || 0}`);
    $('#detailCreditMaxLate').text(`${history.max_days_late || 0} días`);
    $('#detailCreditOverdue').text(`${history.active_overdue_amount_formatted || 'S/ 0.00'} (${history.active_overdue_installments || 0} cuotas)`);
    $('#detailCreditRecommendation').text(history.recommendation || 'No aplica');
    $('#detailCreditCalculatedAt').text(history.calculated_at ? `Actualizado: ${history.calculated_at}` : 'Pendiente de cálculo');
}

$(document).on('click', '#detailCreditEventsButton', function () {
    const url = $(this).data('url');
    if (!url) return;
    const wrap = $('#detailCreditEventsWrap');
    if (!wrap.hasClass('d-none')) return wrap.addClass('d-none');
    $('#detailCreditEventsRows').html('<tr><td colspan="7">Cargando historial...</td></tr>'); wrap.removeClass('d-none');
    $.get(url, function (response) {
        const rows = (response.events || []).map(event => `<tr><td>${escapeHtml(String(event.type || '').replaceAll('_', ' '))}</td><td>${escapeHtml(event.loan || '-')} / ${escapeHtml(event.installment || '-')}</td><td>${escapeHtml(event.due_date || 'No aplica')}</td><td>${escapeHtml(event.payment_date || 'Pendiente')}</td><td>${escapeHtml(event.registered_at || 'No aplica')}<small class="d-block text-muted">${escapeHtml(event.registered_by || 'Sistema')}</small></td><td>${escapeHtml(event.days_late || 0)}</td><td>${escapeHtml(event.amount || 'S/ 0.00')}</td></tr>`).join('');
        $('#detailCreditEventsRows').html(rows || '<tr><td colspan="7">Sin eventos crediticios registrados.</td></tr>');
    }).fail(() => $('#detailCreditEventsRows').html('<tr><td colspan="7" class="text-danger">No se pudo cargar el historial.</td></tr>'));
});

$(document).on('click', '#detailCreditRecalculateButton', function () {
    const button = $(this), url = button.data('url'); if (!url) return;
    button.prop('disabled', true);
    $.post(url, {}, function (response) { fillCreditHistory(response.summary); $('#detailCreditEventsWrap').addClass('d-none'); toast(response.message, 'success'); })
        .fail(showActionError).always(() => button.prop('disabled', false));
});

function fillAccountClosureDetail(closure) {
    $('#detailAccountClosureCard').toggleClass('d-none', !closure);
    if (!closure) return;
    $('#detailAccountClosureDate').text(closure.retirement_date || '-');
    $('#detailAccountClosureCode').text(closure.code || '-');
    $('#detailAccountClosureBalance').text(closure.final_balance_formatted || 'S/ 0.00');
    $('#detailAccountClosureMethod').text(closure.payment_method_label || '-');
    $('#detailAccountClosureConstancy').attr('href', closure.constancy_url || '#');
    $('#detailAccountClosureReceipt').toggleClass('d-none', !closure.receipt_url).attr('href', closure.receipt_url || '#');
}

function fillEnrollmentDetail(member) {
    const enrollment = member.enrollment;
    const isOld = member.member_type_calculated === 'antiguo';
    $('#detailEnrollmentNotApplicable').toggleClass('d-none', !isOld);
    $('#detailEnrollmentMissing').toggleClass('d-none', isOld || !!enrollment);
    $('#detailEnrollmentData').toggleClass('d-none', isOld || !enrollment);
    if (isOld || !enrollment) return;
    $('#detailEnrollmentCode').text(enrollment.code || '-');
    $('#detailEnrollmentDate').text(enrollment.enrollment_date_formatted || '-');
    $('#detailEnrollmentAmount').text(enrollment.amount_formatted || 'S/ 50.00');
    $('#detailEnrollmentMethod').text(enrollment.payment_method_label || '-');
    $('#detailEnrollmentReference').text(enrollment.payment_reference || '-');
    $('#detailEnrollmentStatus').text(enrollment.status_label || '-');
    $('#detailEnrollmentObservation').text(enrollment.observation || '-');
    $('#detailEnrollmentCash').text(enrollment.cash_code ? `${enrollment.cash_code} - ${enrollment.cash_status || '-'}` : '-');
    $('#detailEnrollmentCashBalance').text(enrollment.cash_balance_after || '-');
    $('#detailEnrollmentReceipt').toggleClass('d-none', !enrollment.receipt_url).attr('href', enrollment.receipt_url || '#');

    if (enrollment.voucher_missing) {
        $('#detailEnrollmentVoucher').html('<div class="member-voucher-empty warning"><i class="fas fa-exclamation-triangle"></i><span>Comprobante no encontrado.</span></div>');
    } else if (!enrollment.voucher_url) {
        $('#detailEnrollmentVoucher').html('<div class="member-voucher-empty"><i class="fas fa-paperclip"></i><span>Sin comprobante registrado.</span></div>');
    } else {
        $('#detailEnrollmentVoucher').html(renderVoucherPreview({
            url: enrollment.voucher_public_url,
            viewUrl: enrollment.voucher_public_url,
            downloadUrl: enrollment.voucher_url,
            name: enrollment.voucher_file_name,
            mime: enrollment.voucher_mime_type,
            extension: enrollment.voucher_extension
        }));
    }
}

function renderVoucherPreview(options = {}) {
    const url = options.url || '';
    const downloadUrl = options.downloadUrl || url;
    const viewUrl = options.viewUrl || url;
    const name = options.name || 'Voucher de inscripcion';
    const mime = String(options.mime || '').toLowerCase();
    const extension = String(options.extension || name.split('.').pop() || '').toLowerCase();
    const isPdf = mime === 'application/pdf' || extension === 'pdf';
    const isImage = mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'webp'].includes(extension) || String(url).startsWith('data:image/');

    if (!url) return '<div class="member-voucher-empty"><i class="fas fa-paperclip"></i><span>No existe voucher registrado.</span></div>';
    if (options.compact) {
        return isPdf
            ? `<div class="member-enrollment-pdf"><i class="fas fa-file-pdf"></i><span>Comprobante PDF</span></div>`
            : `<img src="${escapeHtml(url)}" alt="Vista previa de ${escapeHtml(name)}" loading="eager">`;
    }

    const preview = isPdf
        ? `<div class="member-voucher-pdf"><i class="fas fa-file-pdf"></i><div><strong>Comprobante PDF</strong><span>${escapeHtml(name)}</span></div></div>`
        : (isImage
            ? `<div class="member-selected-voucher-image"><img src="${escapeHtml(url)}" alt="Vista previa de ${escapeHtml(name)}" loading="eager"><span>${escapeHtml(name)}</span></div>`
            : '<div class="member-voucher-empty"><i class="fas fa-file"></i><span>Formato de comprobante no reconocido.</span></div>');
    const viewLabel = isPdf ? 'Ver PDF' : 'Ver comprobante';
    const removeButton = options.removable ? '<button type="button" class="btn btn-outline-danger btn-sm" id="btnRemoveEnrollmentVoucher"><i class="fas fa-times"></i> Quitar</button>' : '';
    const actions = `<div class="member-voucher-actions"><a class="btn btn-light border btn-sm" target="_blank" rel="noopener" href="${escapeHtml(viewUrl)}"><i class="fas fa-eye"></i> ${viewLabel}</a><a class="btn btn-light border btn-sm" download href="${escapeHtml(downloadUrl)}"><i class="fas fa-download"></i> Descargar</a>${removeButton}</div>`;
    return `<div class="member-selected-voucher">${preview}${actions}</div>`;
}

function formatMemberType(type) {
    return type === 'antiguo' ? 'Antiguo' : (type === 'nuevo' ? 'Nuevo' : 'No registrado');
}

function initGuarantorSelect() {
    const select = $('#guarantor_option');
    if (!select.length || !$.fn.select2) return;

    select.empty().append(new Option('', '', false, false));

    select.select2({
        width: '100%',
        theme: 'bootstrap4',
        dropdownParent: $('#memberModal'),
        placeholder: 'Seleccione aval o garante',
        allowClear: true,
        ajax: {
            url: window.memberRoutes.guarantorSelect,
            dataType: 'json',
            delay: 200,
            cache: false,
            data: params => ({
                q: params.term || '',
                exclude_member_id: $('#memberForm').attr('data-id') || ''
            }),
            processResults: response => ({ results: response.results || [] })
        },
        language: {
            noResults: () => 'No hay socios vigentes aptos para ser aval/garante.',
            searching: () => 'Buscando socios aptos...'
        }
    });
}

function resetGuarantorForm() {
    const form = $('#externalGuarantorForm');
    form[0].reset();
    $('#guarantor-error-messages').addClass('d-none').empty();
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('.invalid-feedback').remove();
}

function storeExternalGuarantor(form) {
    const button = $(form).find('[type="submit"]');
    const formData = new FormData(form);
    $('#guarantor-error-messages').addClass('d-none').empty();
    $(form).find('.is-invalid').removeClass('is-invalid');
    $(form).find('.invalid-feedback').remove();
    button.prop('disabled', true);

    $.ajax({
        url: window.memberRoutes.guarantorStore,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            const guarantor = response.guarantor;
            selectGuarantorOption(guarantor.value, guarantor.label, guarantor.type);
            $('#externalGuarantorModal').modal('hide');
            toast(response.message, 'success');
        },
        error: function (xhr) {
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                showGuarantorValidationErrors(xhr.responseJSON.errors);
                return;
            }

            if (xhr.status === 409) {
                handleGuarantorDuplicate(xhr.responseJSON || {});
                return;
            }

            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo registrar el aval externo.', 'error');
        },
        complete: function () {
            button.prop('disabled', false);
        }
    });
}

function handleGuarantorDuplicate(response) {
    if (response.duplicate_type === 'member' && response.member) {
        Swal.fire({
            title: 'DNI registrado como socio',
            text: `${response.message} Desea usar este socio como aval?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, usar socio',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            selectGuarantorOption(response.member.value, response.member.label, 'socio');
            $('#externalGuarantorModal').modal('hide');
        });
        return;
    }

    if (response.duplicate_type === 'external' && response.guarantor) {
        Swal.fire('Atencion', response.message || 'Este aval externo ya esta registrado. Se seleccionara el registro existente.', 'warning');
        selectGuarantorOption(response.guarantor.value, response.guarantor.label, response.guarantor.type || 'externo');
        $('#externalGuarantorModal').modal('hide');
    }
}

function selectGuarantorOption(value, label, type) {
    const select = $('#guarantor_option');
    if (!select.find(`option[value="${value}"]`).length) {
        const option = new Option(label, value, true, true);
        $(option).attr('data-type', type || '');
        select.append(option);
    }

    select.val(value).trigger('change');
}

function showGuarantorValidationErrors(errors) {
    let list = '<ul class="mb-0">';
    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`#externalGuarantorForm [name="${key}"]`);
        input.addClass('is-invalid');
        placeFieldError(input, messages[0]);
    });
    list += '</ul>';

    $('#guarantor-error-messages').removeClass('d-none').html(list);
}

function fillGuarantorDetail(guarantor) {
    if (!guarantor) {
        $('#detailGuarantorEmpty').removeClass('d-none');
        $('#detailGuarantorData').addClass('d-none');
        return;
    }

    $('#detailGuarantorEmpty').addClass('d-none');
    $('#detailGuarantorData').removeClass('d-none');
    $('#detailGuarantorType').text(guarantor.type_label || '-');
    $('#detailGuarantorCode').text(guarantor.code || '-');
    $('#detailGuarantorDni').text(guarantor.dni || '-');
    $('#detailGuarantorStatus').text(guarantor.status_label || '-');
    $('#detailGuarantorName').text(guarantor.full_name || '-');
    $('#detailGuarantorPhone').text(guarantor.phone || '-');
    $('#detailGuarantorAddress').text(guarantor.address || '-');
    $('#detailGuarantorContributions').text(guarantor.total_contributions_formatted || 'S/ 0.00');
}

function fillFinancialSummary(summary) {
    summary = summary || {};
    $('#detailFinancialAmount').text(summary.total_amount || 'S/ 0.00');
    $('#detailFinancialShares').text(summary.total_shares || '0.0000');
    $('#detailFinancialContributionCount').text(summary.contribution_count ?? 0);
    $('#detailFinancialActiveLoans').text(summary.active_loans ?? 0);
    $('#detailFinancialDebt').text(summary.pending_debt || 'S/ 0.00');
    $('#detailFinancialUtilities').text(summary.pending_utilities || 'S/ 0.00');
}

function setValue(name, value) {
    $(`[name="${name}"]`).val(value || '');
}

function updateLeftStatus(status) {
    const label = status === 'no_vigente' ? 'No vigente' : (status ? formatStatus(status) : 'Vigente');
    const badge = $('#left_member_status');
    badge.removeClass('badge-success badge-danger badge-secondary');
    badge.addClass(status === 'retirado' ? 'badge-danger' : (status === 'no_vigente' ? 'badge-secondary' : 'badge-success'));
    badge.text(label);
}

function statusBadge(status, label) {
    const cls = status === 'retirado' ? 'danger' : (status === 'no_vigente' ? 'secondary' : 'success');
    return `<span class="badge badge-${cls}">${escapeHtml(label || formatStatus(status))}</span>`;
}

function formatStatus(value) {
    if (!value) return '-';
    return value.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
}

function showValidationErrors(errors) {
    let list = '<ul class="mb-0">';
    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;

        const selector = fieldSelector(key);
        const input = $(selector);
        input.addClass('is-invalid');
        placeFieldError(input, messages[0]);
    });
    list += '</ul>';

    $('#member-error-messages').removeClass('d-none').html(list);
}

function placeFieldError(input, message) {
    const feedback = `<div class="invalid-feedback d-block">${message}</div>`;

    if (input.closest('.input-group').length) {
        input.closest('.input-group').after(feedback);
        return;
    }

    input.after(feedback);
}

function fieldSelector(key) {
    if (key === 'beneficiaries') return '#beneficiarySummary';
    if (key.startsWith('beneficiaries.')) {
        const parts = key.split('.');
        return `[name="beneficiaries[${parts[1]}][${parts[2]}]"]`;
    }
    if (key.startsWith('relatives.')) {
        const parts = key.split('.');
        return `[name="relatives[${parts[1]}][${parts[2]}]"]`;
    }

    return `[name="${key}"]`;
}

function clearValidationErrors() {
    $('#member-error-messages').addClass('d-none').empty();
    $('#memberForm .is-invalid').removeClass('is-invalid');
    $('#memberForm .invalid-feedback').remove();
}

function updateChildrenEmptyState() {
    const hasChildren = $('#childrenContainer .member-child-row').length > 0;
    $('#childrenEmptyState').toggleClass('d-none', hasChildren);
}

function calculateAge(dateValue) {
    if (!dateValue) return null;

    const birthDate = new Date(dateValue);
    if (Number.isNaN(birthDate.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    return age >= 0 ? age : null;
}

function toast(message, icon) {
    Swal.fire({
        title: message,
        icon: icon,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function searchDni() {
    const dni = $('[name="dni"]').val();

    if (!/^\d{8}$/.test(dni)) {
        Swal.fire('Atencion', 'Ingrese un DNI valido de 8 digitos.', 'warning');
        return;
    }

    const button = $('#btnSearchDni');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    toast('Consultando DNI...', 'info');

    $.get(`${window.memberRoutes.verifyDni}/${dni}`, function (verification) {
        if (verification.status === 'member') {
            Swal.fire('Atencion', verification.message || 'El DNI ya esta registrado en otro socio.', 'warning');
            return;
        }

        if (verification.status === 'reentry' && verification.member) {
            Swal.fire({
                title: 'Socio retirado anteriormente',
                text: verification.message || 'El socio ya estuvo registrado y fue retirado. Puede registrarlo nuevamente como reingreso.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Registrar como reingreso',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                fillMemberFromReentry(verification.member);
            });
            return;
        }

        if (verification.status === 'external' && verification.guarantor) {
            Swal.fire({
                title: 'Aval externo encontrado',
                text: verification.message || 'Esta persona ya esta registrada como aval externo. Desea usar sus datos para crear el socio?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, usar datos',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                fillMemberFromExternalGuarantor(verification.guarantor);
            });
            return;
        }

        fetchMemberDniData(dni);
    }).fail(function (xhr) {
        const message = xhr.responseJSON?.message || 'No se pudo verificar el DNI.';
        Swal.fire('Error', message, 'error');
    }).always(function () {
        button.prop('disabled', false).html('<i class="fas fa-search"></i>');
    });
}

function fillMemberFromReentry(member) {
    $('#memberForm [name="reentry_from_member_id"]').val(member.id || '');
    $('#memberForm [name="reentry_confirmed"]').val('1');
    $('[name="dni"]').val(member.dni || '');
    $('[name="first_name"]').val(member.first_name || '');
    $('[name="last_name"]').val(member.last_name || '');
    $('[name="birth_date"]').val(member.birth_date || '');
    $('[name="phone"]').val(member.phone || '');
    $('[name="address"]').val(member.address || '');
    if (member.photo_url) $('#memberImgPreview').attr('src', member.photo_url);
    toast('Datos precargados. Complete el nuevo ingreso y la inscripción.', 'success');
}

function fetchMemberDniData(dni) {
    $.get(`${window.memberRoutes.consultarDni}/${dni}`, function (response) {
        const person = response.data || {};
        const firstName = person.nombres || '';
        const lastName = `${person.apellidoPaterno || ''} ${person.apellidoMaterno || ''}`.trim();

        if (firstName) {
            $('[name="first_name"]').val(firstName);
        }

        if (lastName) {
            $('[name="last_name"]').val(lastName);
        }

        toast('Datos encontrados correctamente.', 'success');
    }).fail(function (xhr) {
        const message = xhr.responseJSON?.message || 'No se pudo consultar el DNI.';
        const icon = xhr.status === 404 ? 'warning' : 'error';
        Swal.fire(icon === 'warning' ? 'DNI no encontrado' : 'Error', message, icon);
    });
}

function fillMemberFromExternalGuarantor(guarantor) {
    $('[name="dni"]').val(guarantor.dni || '');
    $('[name="first_name"]').val(guarantor.first_name || '');
    $('[name="last_name"]').val(guarantor.last_name || '');
    $('[name="phone"]').val(guarantor.phone || '');
    $('[name="address"]').val(guarantor.address || '');
    $('[name="observation"]').val(guarantor.observation || '');

    if (guarantor.photo_url) {
        $('#memberImgPreview').attr('src', guarantor.photo_url);
    }
}

function searchGuarantorDni() {
    const dni = $('#externalGuarantorForm [name="dni"]').val();

    if (!/^\d{8}$/.test(dni)) {
        Swal.fire('Atencion', 'Ingrese un DNI valido de 8 digitos.', 'warning');
        return;
    }

    const button = $('#btnSearchGuarantorDni');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.get(`${window.memberRoutes.consultarDni}/${dni}`, function (response) {
        const person = response.data || {};
        const firstName = person.nombres || '';
        const lastName = `${person.apellidoPaterno || ''} ${person.apellidoMaterno || ''}`.trim();

        if (firstName) {
            $('#externalGuarantorForm [name="first_name"]').val(firstName);
        }

        if (lastName) {
            $('#externalGuarantorForm [name="last_name"]').val(lastName);
        }

        toast('Datos encontrados correctamente.', 'success');
    }).fail(function (xhr) {
        const message = xhr.responseJSON?.message || 'No se pudo consultar el DNI.';
        const icon = xhr.status === 404 ? 'warning' : 'error';
        Swal.fire(icon === 'warning' ? 'DNI no encontrado' : 'Error', message, icon);
    }).always(function () {
        button.prop('disabled', false).html('<i class="fas fa-search"></i>');
    });
}
