var divLoading = document.getElementById('divLoading');
let tableSolidarity;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableSolidarity = $('#tableSolidarity').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.solidarityRoutes.list,
            data: function (data) {
                data.date_from = $('#solidarity_filter_date_from').val();
                data.date_to = $('#solidarity_filter_date_to').val();
                data.type = $('#solidarity_filter_type').val();
                data.member_id = $('#solidarity_filter_member_id').val();
                data.status = $('#solidarity_filter_status').val();
                data.payment_method = $('#solidarity_filter_payment_method').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code', defaultContent: '-' },
            { data: 'movement_date', name: 'movement_date' },
            { data: 'type', name: 'type' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'concept', name: 'concept' },
            { data: 'payment_method', name: 'payment_method' },
            { data: 'amount', name: 'amount' },
            { data: 'status', orderable: false, searchable: false },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadSolidaritySummary();
    tableSolidarity.on('draw', loadSolidaritySummary);

    $('#solidarity_filter_date_from, #solidarity_filter_date_to, #solidarity_filter_type, #solidarity_filter_member_id, #solidarity_filter_status, #solidarity_filter_payment_method').on('change', function () {
        tableSolidarity.ajax.reload();
    });

    $('#btnClearSolidarityFilters').on('click', function () {
        $('#solidarity_filter_date_from, #solidarity_filter_date_to, #solidarity_filter_type, #solidarity_filter_member_id, #solidarity_filter_status, #solidarity_filter_payment_method').val('');
        tableSolidarity.ajax.reload();
    });

    $('#btnNewSolidarity').on('click', function () {
        resetSolidarityForm();
        fetchNextSolidarityCode();
        $('#solidarityModal').modal('show');
    });

    $('#solidarityModal').on('hidden.bs.modal', resetSolidarityForm);
    $('[name="payment_method"]').on('change', updateReferenceRequirement);
    $('[name="type"], [name="amount"], [name="status"]').on('input change', updateSolidaritySide);

    $('#solidarityVoucher').on('change', function () {
        const file = this.files && this.files.length ? this.files[0] : null;
        $('#solidarityVoucherName').text(file ? file.name : 'JPG, PNG, WEBP o PDF - max. 4 MB');
    });

    $('#solidarityForm').on('submit', function (event) {
        event.preventDefault();
        clearSolidarityValidationErrors();
        setLoading(true);

        const form = this;
        const id = $(form).attr('data-id');
        const formData = new FormData(form);
        let url = window.solidarityRoutes.store;

        if (id) {
            url = `${window.solidarityRoutes.base}/${id}`;
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                setLoading(false);
                $('#solidarityModal').modal('hide');
                tableSolidarity.ajax.reload(null, false);
                loadSolidaritySummary();
                toast(response.message, 'success');
            },
            error: function (xhr) {
                setLoading(false);

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showSolidarityValidationErrors(xhr.responseJSON.errors);
                    return;
                }

                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar el movimiento.', 'error');
            }
        });
    });

    $(document).on('click', '.editSolidarity', function () {
        setLoading(true);
        $.get(`${window.solidarityRoutes.base}/${$(this).data('id')}/edit`, function (movement) {
            setLoading(false);
            fillSolidarityForm(movement);
            $('#solidarityModal').modal('show');
        }).fail(showSolidarityActionError);
    });

    $(document).on('click', '.showSolidarity', function () {
        setLoading(true);
        $.get(`${window.solidarityRoutes.base}/${$(this).data('id')}`, function (movement) {
            setLoading(false);
            fillSolidarityDetail(movement);
            $('#solidarityDetailModal').modal('show');
        }).fail(showSolidarityActionError);
    });

    $(document).on('click', '.annulSolidarity', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Anular movimiento',
            text: '¿Esta seguro de anular este movimiento de solidaridad?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.post(`${window.solidarityRoutes.base}/${id}/anular`, function (response) {
                tableSolidarity.ajax.reload(null, false);
                loadSolidaritySummary();
                toast(response.message, 'success');
            }).fail(showSolidarityActionError);
        });
    });
});

function resetSolidarityForm() {
    const form = $('#solidarityForm');
    form[0].reset();
    form.removeAttr('data-id');
    clearSolidarityValidationErrors();

    $('#solidarityModalLabel').text('Nuevo movimiento solidario');
    $('#solidaritySaveText').text('Guardar movimiento');
    $('[name="movement_date"]').val(new Date().toISOString().slice(0, 10));
    $('[name="type"]').val('ingreso');
    $('[name="status"]').val('registrado');
    $('#solidarityVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB');
    $('#solidarityCurrentVoucherBox').addClass('d-none');
    $('#solidarityCurrentVoucherLink').attr('href', '#');
    setSolidarityCode(window.solidarityRoutes.nextCodeValue || 'SOL-000001');
    updateReferenceRequirement();
    updateSolidaritySide();
}

function fetchNextSolidarityCode() {
    $.get(window.solidarityRoutes.nextCode, function (response) {
        setSolidarityCode(response.code || 'SOL-000001');
    });
}

function setSolidarityCode(code) {
    $('[name="code"]').val(code);
    $('#solidaritySideCode').text(code);
}

function fillSolidarityForm(movement) {
    resetSolidarityForm();
    const form = $('#solidarityForm');
    form.attr('data-id', movement.id);

    $('#solidarityModalLabel').text('Editar movimiento solidario');
    $('#solidaritySaveText').text('Actualizar movimiento');
    setSolidarityCode(movement.code || 'Sin codigo');
    setValue('movement_date', movement.movement_date);
    setValue('type', movement.type);
    setValue('status', movement.status);
    setValue('member_id', movement.member_id);
    setValue('concept', movement.concept);
    setValue('amount', movement.amount);
    setValue('payment_method', movement.payment_method);
    setValue('payment_reference', movement.payment_reference);
    setValue('observation', movement.observation);

    if (movement.voucher_url) {
        $('#solidarityCurrentVoucherBox').removeClass('d-none');
        $('#solidarityCurrentVoucherLink').attr('href', movement.voucher_url);
    }

    updateReferenceRequirement();
    updateSolidaritySide();
}

function fillSolidarityDetail(movement) {
    $('#detailSolidarityCode').text(movement.code || '-');
    $('#detailSolidarityConcept').text(movement.concept || '-');
    $('#detailSolidarityStatus').html(statusBadge(movement.status, movement.status_label));
    $('#detailSolidarityType').text(movement.type_label || '-');
    $('#detailSolidarityAmount').text(movement.amount_formatted || 'S/ 0.00');
    $('#detailSolidarityImpact').text(movement.impact_label || '-');
    $('#detailSolidarityDate').text(movement.movement_date_formatted || '-');
    $('#detailSolidarityPaymentMethod').text(movement.payment_method_label || '-');
    $('#detailSolidarityReference').text(movement.payment_reference || '-');
    $('#detailSolidarityCash').text(movement.cash_movement_number || '-');
    $('#detailSolidarityMember').text(movement.member_name || '-');
    $('#detailSolidarityDni').text(movement.member_dni || '-');
    $('#detailSolidarityCreatedAt').text(movement.created_at || '-');
    $('#detailSolidarityCreatedBy').text(movement.created_by_name || '-');
    $('#detailSolidarityObservation').text(movement.observation || '-');

    setLink('#detailSolidarityVoucherView', movement.voucher_url, '<i class="fas fa-eye mr-1"></i> Ver comprobante', '<i class="fas fa-eye mr-1"></i> Sin comprobante');
    setLink('#detailSolidarityVoucherDownload', movement.voucher_download_url, '<i class="fas fa-download mr-1"></i> Descargar comprobante', '<i class="fas fa-download mr-1"></i> Descargar comprobante');
    setLink('#detailSolidarityReceipt', movement.receipt_url, `<i class="fas fa-print mr-1"></i> ${escapeHtml(movement.receipt_number || 'Ver recibo')}`, '<i class="fas fa-print mr-1"></i> Sin recibo');
    setLink('#detailSolidarityReceiptPdf', movement.receipt_pdf_url, '<i class="fas fa-file-pdf mr-1"></i> PDF', '<i class="fas fa-file-pdf mr-1"></i> PDF');
}

function loadSolidaritySummary() {
    $.get(window.solidarityRoutes.summary, {
        date_from: $('#solidarity_filter_date_from').val(),
        date_to: $('#solidarity_filter_date_to').val()
    }, function (summary) {
        $('#solidaritySummaryBalance').text(`S/ ${summary.balance || '0.00'}`);
        $('#solidaritySummaryIncome').text(`S/ ${summary.income || '0.00'}`);
        $('#solidaritySummaryExpense').text(`S/ ${summary.expense || '0.00'}`);
        $('#solidaritySummaryMonth').text(summary.month_movements || '0');
    });
}

function updateReferenceRequirement() {
    const method = $('[name="payment_method"]').val();
    const required = ['yape', 'plin', 'transferencia'].includes(method);
    $('[name="payment_reference"]').prop('required', required);
    $('#solidarityReferenceRequired').toggleClass('d-none', !required);
}

function updateSolidaritySide() {
    const amount = parseFloat($('[name="amount"]').val()) || 0;
    const type = $('[name="type"]').val() || 'ingreso';
    const status = $('[name="status"]').val() || 'registrado';

    $('#solidaritySideAmount').text(`S/ ${amount.toFixed(2)}`);
    $('#solidaritySideType').text(type === 'egreso' ? 'Egreso' : 'Ingreso');
    $('#solidaritySideStatus').text(status === 'anulado' ? 'Anulado' : 'Registrado');
}

function setLink(selector, url, activeHtml, inactiveHtml) {
    const link = $(selector);

    if (url) {
        link.removeClass('disabled').attr('href', url).html(activeHtml);
        return;
    }

    link.addClass('disabled').attr('href', '#').html(inactiveHtml);
}

function showSolidarityValidationErrors(errors) {
    let list = '<ul class="mb-0">';

    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`[name="${key}"]`);
        input.addClass('is-invalid');
        placeSolidarityFieldError(input, messages[0]);
    });

    list += '</ul>';
    $('#solidarity-error-messages').removeClass('d-none').html(list);
}

function placeSolidarityFieldError(input, message) {
    const feedback = `<div class="invalid-feedback d-block">${message}</div>`;

    if (input.closest('.input-group').length) {
        input.closest('.input-group').after(feedback);
        return;
    }

    input.after(feedback);
}

function clearSolidarityValidationErrors() {
    $('#solidarity-error-messages').addClass('d-none').empty();
    $('#solidarityForm .is-invalid').removeClass('is-invalid');
    $('#solidarityForm .invalid-feedback').remove();
}

function showSolidarityActionError(xhr) {
    setLoading(false);
    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error');
}

function statusBadge(status, label) {
    const cls = status === 'anulado' ? 'danger' : 'success';
    return `<span class="badge badge-${cls}">${escapeHtml(label || status || '-')}</span>`;
}

function setValue(name, value) {
    $(`[name="${name}"]`).val(value || '');
}

function setLoading(show) {
    if (divLoading) divLoading.style.display = show ? 'flex' : 'none';
}

function toast(message, icon) {
    Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
