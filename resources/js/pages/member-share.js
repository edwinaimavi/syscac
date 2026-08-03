var divLoading = document.getElementById('divLoading');
let tableMemberShare;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    initMemberSelects();

    tableMemberShare = $('#tableMemberShare').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.memberShareRoutes.list,
            data: function (data) {
                data.member_id = $('#filter_member_id').val();
                data.status = $('#filter_status').val();
                data.date_from = $('#filter_date_from').val();
                data.date_to = $('#filter_date_to').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code', defaultContent: '-' },
            { data: 'date', name: 'date' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'member_dni', name: 'member.dni' },
            { data: 'total_paid', name: 'total_paid' },
            { data: 'share_capital_amount', name: 'share_capital_amount' },
            { data: 'solidarity_amount', name: 'solidarity_amount' },
            { data: 'administrative_fee_amount', name: 'administrative_fee_amount' },
            { data: 'share_value', name: 'share_value' },
            { data: 'shares_quantity', name: 'shares_quantity' },
            { data: 'payment_method', name: 'payment_method' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });

    tableMemberShare.on('draw', loadShareSummary);
    loadShareSummary();

    $('#btnNewShare').on('click', function () {
        resetShareForm();
        fetchNextCode();
        $('#memberShareModal').modal('show');
    });

    $('#memberShareModal').on('shown.bs.modal', function () {
        $('#share_member_id').select2('open');
    });

    $('#memberShareModal').on('hidden.bs.modal', function () {
        resetShareForm();
    });

    $('#filter_member_id, #filter_status, #filter_date_from, #filter_date_to').on('change', function () {
        tableMemberShare.ajax.reload();
    });

    $('#btnClearShareFilters').on('click', function () {
        $('#filter_member_id').val('').trigger('change.select2');
        $('#filter_status').val('');
        $('#filter_date_from').val('');
        $('#filter_date_to').val('');
        tableMemberShare.ajax.reload();
    });

    $('[name="total_paid"]').on('input', suggestShareBreakdown);
    $('[name="solidarity_amount"], [name="administrative_fee_amount"]').on('input', calculateShares);
    $('#memberShareForm [name="payment_method"]').on('change', updateSharePaymentFields);

    $('#shareVoucher').on('change', function () {
        const file = this.files && this.files.length ? this.files[0] : null;
        handleShareVoucherSelection(file, this);
    });

    $('#btnChangeShareVoucher').on('click', function () {
        $('#shareVoucher').trigger('click');
    });

    $('#memberShareForm').on('submit', function (event) {
        event.preventDefault();
        clearShareValidationErrors();
        setLoading(true);

        const form = this;
        const id = $(form).attr('data-id');
        const formData = new FormData(form);
        let url = window.memberShareRoutes.store;

        if (id) {
            url = `${window.memberShareRoutes.base}/${id}`;
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
                $('#memberShareModal').modal('hide');
                tableMemberShare.ajax.reload(null, false);
                toast(response.message, 'success');
            },
            error: function (xhr) {
                setLoading(false);

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showShareValidationErrors(xhr.responseJSON.errors);
                    return;
                }

                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar el aporte.', 'error');
            }
        });
    });

    $(document).on('click', '.editShare', function () {
        const id = $(this).data('id');
        setLoading(true);

        $.get(`${window.memberShareRoutes.base}/${id}/edit`, function (share) {
            setLoading(false);
            fillShareForm(share);
            $('#memberShareModal').modal('show');
        }).fail(function (xhr) {
            setLoading(false);
            Swal.fire('Error', xhr.responseJSON?.message || 'No se encontro el aporte solicitado.', 'error');
        });
    });

    $(document).on('click', '.showShare', function () {
        const id = $(this).data('id');
        setLoading(true);

        $.get(`${window.memberShareRoutes.base}/${id}`, function (share) {
            setLoading(false);
            fillShareDetail(share);
            $('#memberShareDetailModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro el aporte solicitado.', 'error');
        });
    });

    $(document).on('click', '.annulShare', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Anular aporte',
            text: 'El aporte quedara en historial, pero no contara para reportes futuros.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.post(`${window.memberShareRoutes.base}/${id}/anular`, function (response) {
                tableMemberShare.ajax.reload(null, false);
                toast(response.message, 'success');
            }).fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo anular el aporte.', 'error');
            });
        });
    });

    $(document).on('click', '.openVoucherPreview', function (event) {
        event.preventDefault();

        const imageUrl = $(this).data('preview');
        const downloadUrl = $(this).data('download');

        if (!imageUrl) {
            Swal.fire('Error', 'Comprobante no encontrado.', 'error');
            return;
        }

        $('#voucherPreviewImage').attr('src', imageUrl);
        $('#voucherPreviewDownloadLink').attr('href', downloadUrl || imageUrl);
        $('#voucherPreviewModal').modal('show');
    });

    $('#voucherPreviewModal').on('hidden.bs.modal', function () {
        $('#voucherPreviewImage').attr('src', '');
        $('#voucherPreviewDownloadLink').attr('href', '#');
    });
});

function initMemberSelects() {
    if (!$.fn.select2) return;

    $('#share_member_id').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#memberShareModal'),
        placeholder: 'Buscar por codigo, DNI o nombres'
    });

    $('#filter_member_id').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Todos los socios'
    });
}

function resetShareForm() {
    const form = $('#memberShareForm');
    form[0].reset();
    form.removeAttr('data-id');
    clearShareValidationErrors();

    $('#memberShareModalLabel').text('Nuevo aporte');
    $('#memberShareSaveText').text('Guardar aporte');
    $('[name="date"]').val(new Date().toISOString().slice(0, 10));
    $('[name="share_value"]').val(window.memberShareRoutes.defaultShareValue || '20.00');
    $('[name="status"]').val('registrado');
    $('[name="shares_quantity"]').val('');
    $('#share_member_id').val('').trigger('change');
    $('#shareVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB');
    $('#currentVoucherBox').addClass('d-none');
    $('#currentVoucherLink').attr('href', '#');
    $('#currentVoucherDownloadLink').attr('href', '#').addClass('disabled');
    $('#currentVoucherPreview').empty();
    $('#currentVoucherTitle').text('Comprobante actual');
    $('#currentVoucherStatus').text('Sin comprobante registrado');
    $('#btnChangeShareVoucher').addClass('d-none');
    setShareCode(window.memberShareRoutes.nextCodeValue || 'APO-000001');
    updateSharePaymentFields();
    updateShareSide();
}

function updateSharePaymentFields() {
    const method = $('#memberShareForm [name="payment_method"]').val();
    const requiresReference = ['yape', 'plin', 'transferencia'].includes(method);
    const isCash = method === 'efectivo';
    const reference = $('#memberShareForm [name="payment_reference"]');

    if (isCash) {
        reference.val('').prop('required', false).removeClass('is-invalid');
        reference.siblings('.invalid-feedback').remove();
        $('#sharePaymentReferenceGroup').addClass('d-none');
    } else {
        $('#sharePaymentReferenceGroup').removeClass('d-none');
        reference
            .prop('required', requiresReference)
            .attr('placeholder', 'Operación, código o número de referencia');
    }

    $('#sharePaymentReferenceRequired').toggleClass('d-none', !requiresReference);
    $('#shareVoucherLabel').text(isCash ? 'Comprobante opcional' : 'Comprobante');
}

function fetchNextCode() {
    $.get(window.memberShareRoutes.nextCode, function (response) {
        setShareCode(response.code || 'APO-000001');
    });
}

function setShareCode(code) {
    $('[name="code"]').val(code);
    $('#shareSideCode').text(code);
}

function fillShareForm(share) {
    resetShareForm();
    const form = $('#memberShareForm');
    form.attr('data-id', share.id);

    $('#memberShareModalLabel').text('Editar aporte');
    $('#memberShareSaveText').text('Actualizar aporte');
    setShareCode(share.code || 'Sin codigo');
    setValue('date', share.date);
    setValue('total_paid', share.total_paid);
    setValue('solidarity_amount', share.solidarity_amount);
    setValue('administrative_fee_amount', share.administrative_fee_amount);
    setValue('share_value', share.share_value);
    setValue('shares_quantity', share.shares_quantity);
    setValue('payment_method', share.payment_method);
    setValue('payment_reference', share.payment_reference);
    updateSharePaymentFields();
    setValue('status', share.status);
    setValue('observation', share.observation);
    $('#share_member_id').val(share.member_id).trigger('change');

    renderVoucherBox({
        previewSelector: '#currentVoucherPreview',
        linkSelector: '#currentVoucherLink',
        titleSelector: '#currentVoucherTitle',
        statusSelector: '#currentVoucherStatus',
        voucher: share,
        editable: true
    });

    $('#currentVoucherBox').removeClass('d-none');
    $('#btnChangeShareVoucher').removeClass('d-none');

    calculateShares();
}

function calculateShares() {
    const total = parseFloat($('[name="total_paid"]').val()) || 0;
    const shareValue = parseFloat($('[name="share_value"]').val()) || 0;
    const solidarity = Math.max(0, parseFloat($('[name="solidarity_amount"]').val()) || 0);
    const administrative = Math.max(0, parseFloat($('[name="administrative_fee_amount"]').val()) || 0);
    const amount = Math.max(0, total - solidarity - administrative);
    const quantity = amount > 0 && shareValue > 0 ? amount / shareValue : 0;

    const isWhole = quantity > 0 && Math.abs(quantity - Math.round(quantity)) < 0.00001;
    $('[name="shares_quantity"]').val(isWhole ? Math.round(quantity) : '');
    $('[name="share_capital_amount"]').val(amount.toFixed(2));
    $('#shareSideCapital').text(`S/ ${amount.toFixed(2)}`); $('#shareSideSolidarity').text(`S/ ${solidarity.toFixed(2)}`); $('#shareSideAdministrative').text(`S/ ${administrative.toFixed(2)}`);
    $('#shareBreakdownWarning').toggleClass('d-none', total <= 0 || isWhole);
    updateShareSide(total, shareValue, isWhole ? Math.round(quantity) : 0);
}

function suggestShareBreakdown() {
    const total = Math.round((parseFloat($('[name="total_paid"]').val()) || 0) * 100) / 100;
    const remainder = ((Math.round(total * 100) % 2000) + 2000) % 2000;
    $('[name="solidarity_amount"]').val(remainder === 1000 ? '5.00' : '0.00');
    $('[name="administrative_fee_amount"]').val(remainder === 1000 ? '5.00' : '0.00');
    calculateShares();
}

function updateShareSide(amount = null, shareValue = null, quantity = null) {
    const currentAmount = amount ?? (parseFloat($('[name="amount"]').val()) || 0);
    const currentValue = shareValue ?? (parseFloat($('[name="share_value"]').val()) || 0);
    const currentQuantity = quantity ?? (parseFloat($('[name="shares_quantity"]').val()) || 0);

    $('#shareSideAmount').text(`S/ ${currentAmount.toFixed(2)}`);
    $('#shareSideValue').text(`S/ ${currentValue.toFixed(2)}`);
    $('#shareSideQuantity').text(trimQuantity(currentQuantity));
}

function fillShareDetail(share) {
    $('#detailShareCode').text(share.code || '-');
    $('#detailShareMember').text(share.member_name || '-');
    $('#detailShareStatus').html(statusBadge(share.status, share.status_label));
    $('#detailShareAmount').text(share.total_paid_formatted || 'S/ 0.00');
    $('#detailShareCapital').text(share.share_capital_amount_formatted || 'S/ 0.00');
    $('#detailShareSolidarity').text(share.solidarity_amount_formatted || 'S/ 0.00');
    $('#detailShareAdministrative').text(share.administrative_fee_amount_formatted || 'S/ 0.00');
    $('#detailShareQuantity').text(share.shares_quantity || '0');
    $('#detailShareValue').text(share.share_value_formatted || 'S/ 0.00');
    $('#detailMemberCode').text(share.member_code || '-');
    $('#detailMemberDni').text(share.member_dni || '-');
    $('#detailMemberName').text(share.member_name || '-');
    $('#detailShareDate').text(share.date_formatted || '-');
    $('#detailReceiptNumber').text(share.receipt_number || '-');
    $('#detailPaymentMethod').text(share.payment_method_label || '-');
    $('#detailPaymentReference').text(share.payment_reference_display || '-');
    $('#detailCreatedAt').text(share.created_at || '-');
    $('#detailCreatedBy').text(share.created_by_name || '-');
    $('#detailShareObservation').text(share.observation || '-');
    $('#detailReceiptLink').attr('href', share.receipt_url || '#');
    $('#detailCashMovementCode').text(share.cash_movement?.movement_number || '-');
    $('#detailCashMovementStatus').text(share.cash_movement?.status_label || '-');
    $('#detailCashMovementBalance').text(share.cash_movement?.balance_after || '-');
    $('#detailCashMovements').html((share.cash_movements || []).map(m => `<div class="d-flex justify-content-between border-bottom py-2"><span>${escapeHtml(m.category_label)} · ${escapeHtml(m.movement_number)}</span><strong>${escapeHtml(m.amount)} · ${escapeHtml(m.status_label)}</strong></div>`).join('') || '<span class="text-muted">Sin movimientos.</span>');

    renderVoucherBox({
        previewSelector: '#detailVoucherPreview',
        linkSelector: '#detailVoucherLink',
        statusSelector: '#detailVoucherStatus',
        voucher: share
    });
}

function handleShareVoucherSelection(file, input) {
    if (!file) {
        $('#shareVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB');
        return;
    }

    const validation = validateVoucherFile(file);
    if (!validation.valid) {
        input.value = '';
        $('#shareVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB');
        renderVoucherPreview('#currentVoucherPreview', { status: 'missing', type: 'none', message: validation.message });
        $('#currentVoucherBox').removeClass('d-none');
        $('#currentVoucherTitle').text('Archivo no valido');
        $('#currentVoucherStatus').text(validation.message);
        $('#currentVoucherLink').addClass('disabled').attr('href', '#').html('<i class="fas fa-eye mr-1"></i> Ver comprobante');
        $('#currentVoucherDownloadLink').addClass('disabled').attr('href', '#');
        Swal.fire('Error', validation.message, 'error');
        return;
    }

    $('#shareVoucherName').text(file.name);
    $('#currentVoucherBox').removeClass('d-none');
    $('#currentVoucherTitle').text('Nuevo comprobante seleccionado');
    $('#currentVoucherStatus').text(file.type === 'application/pdf' ? 'Comprobante PDF' : 'Vista previa del comprobante');
    $('#currentVoucherLink').addClass('disabled').attr('href', '#').html('<i class="fas fa-eye mr-1"></i> Disponible al guardar');
    $('#currentVoucherDownloadLink').addClass('disabled').attr('href', '#');

    if (isImageFile(file)) {
        const reader = new FileReader();
        reader.onload = function (event) {
            renderVoucherPreview('#currentVoucherPreview', {
                status: 'available',
                type: 'image',
                preview_url: event.target.result,
                message: 'Vista previa'
            });
        };
        reader.readAsDataURL(file);
    } else {
        renderVoucherPreview('#currentVoucherPreview', {
            status: 'available',
            type: 'pdf',
            name: file.name,
            message: 'Comprobante PDF'
        });
    }
}

function validateVoucherFile(file) {
    const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    const allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    const extension = (file.name.split('.').pop() || '').toLowerCase();

    if (!allowed.includes(file.type) && !allowedExt.includes(extension)) {
        return { valid: false, message: 'El comprobante debe ser una imagen o PDF válido.' };
    }

    if (file.size > 4 * 1024 * 1024) {
        return { valid: false, message: 'El comprobante no debe superar los 4 MB.' };
    }

    return { valid: true };
}

function isImageFile(file) {
    return ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)
        || ['jpg', 'jpeg', 'png', 'webp'].includes((file.name.split('.').pop() || '').toLowerCase());
}

function renderVoucherBox({ previewSelector, linkSelector, titleSelector = null, statusSelector = null, voucher, editable = false }) {
    renderVoucherPreview(previewSelector, voucher);
    const statusText = voucher.voucher_message || 'Sin comprobante registrado';
    const link = $(linkSelector);

    if (titleSelector) {
        $(titleSelector).text(voucher.voucher_type === 'pdf' ? 'Comprobante PDF' : 'Comprobante actual');
    }

    if (statusSelector) {
        $(statusSelector).text(statusText);
    }

    if (voucher.voucher_status === 'available' && voucher.voucher_url) {
        link.removeClass('disabled');

        if (voucher.voucher_type === 'image') {
            link
                .attr('href', '#')
                .removeAttr('target')
                .addClass('openVoucherPreview')
                .data('preview', voucher.voucher_preview_url || voucher.voucher_url)
                .data('download', voucher.voucher_download_url || voucher.voucher_url)
                .html('<i class="fas fa-eye mr-1"></i> Ver comprobante');
        } else {
            link
                .attr('href', voucher.voucher_url)
                .attr('target', '_blank')
                .removeClass('openVoucherPreview')
                .removeData('preview')
                .removeData('download')
                .html('<i class="fas fa-file-pdf mr-1"></i> Ver PDF');
        }

        const downloadLink = $(linkSelector.replace('Link', 'DownloadLink'));
        if (downloadLink.length) {
            downloadLink
                .removeClass('disabled')
                .attr('href', voucher.voucher_download_url || voucher.voucher_url)
                .html('<i class="fas fa-download mr-1"></i> Descargar');
        }
    } else {
        link
            .addClass('disabled')
            .attr('href', '#')
            .attr('target', '_blank')
            .removeClass('openVoucherPreview')
            .removeData('preview')
            .removeData('download')
            .html(`<i class="fas fa-file-alt mr-1"></i> ${escapeHtml(statusText)}`);
        const downloadLink = $(linkSelector.replace('Link', 'DownloadLink'));
        if (downloadLink.length) {
            downloadLink
                .addClass('disabled')
                .attr('href', '#')
                .html('<i class="fas fa-download mr-1"></i> Descargar');
        }
    }

    if (editable) {
        $('#btnChangeShareVoucher').toggleClass('d-none', false);
    }
}

function renderVoucherPreview(selector, voucher) {
    const box = $(selector);
    const status = voucher.voucher_status || voucher.status || 'missing';
    const type = voucher.voucher_type || voucher.type || 'none';
    const message = voucher.voucher_message || voucher.message || 'Sin comprobante registrado';
    const previewUrl = voucher.voucher_preview_url || voucher.preview_url;

    if (status === 'available' && type === 'image' && previewUrl) {
        box.html(`<img src="${escapeHtml(previewUrl)}" alt="Vista previa del comprobante">`);
        return;
    }

    if (status === 'available' && type === 'pdf') {
        box.html(`<div class="pdf-card"><i class="fas fa-file-pdf"></i><strong>Comprobante PDF</strong><small>${escapeHtml(voucher.voucher_name || voucher.name || '')}</small></div>`);
        return;
    }

    const icon = status === 'not_found' ? 'fas fa-exclamation-triangle' : 'fas fa-file-upload';
    box.html(`<div class="empty-card"><i class="${icon}"></i><strong>${escapeHtml(message)}</strong></div>`);
}

function loadShareSummary() {
    $.get(window.memberShareRoutes.summary, {
        member_id: $('#filter_member_id').val(),
        date_from: $('#filter_date_from').val(),
        date_to: $('#filter_date_to').val()
    }, function (summary) {
        $('#summaryTotalAmount').text(`S/ ${summary.total_amount || '0.00'}`);
        $('#summaryTotalReceived').text(`S/ ${summary.total_received || '0.00'}`);
        $('#summaryTotalSolidarity').text(`S/ ${summary.total_solidarity || '0.00'}`);
        $('#summaryTotalAdministrative').text(`S/ ${summary.total_administrative_fees || '0.00'}`);
        $('#summaryTotalShares').text(summary.total_shares || '0');
        $('#summaryTotalRecords').text(summary.total_records || '0');
    });
}

function showShareValidationErrors(errors) {
    let list = '<ul class="mb-0">';

    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`[name="${key}"]`);
        input.addClass('is-invalid');
        placeShareFieldError(input, messages[0]);
    });

    list += '</ul>';
    $('#share-error-messages').removeClass('d-none').html(list);
}

function placeShareFieldError(input, message) {
    const feedback = `<div class="invalid-feedback d-block">${message}</div>`;

    if (input.closest('.input-group').length) {
        input.closest('.input-group').after(feedback);
        return;
    }

    if (input.hasClass('select2-hidden-accessible')) {
        input.next('.select2-container').after(feedback);
        return;
    }

    input.after(feedback);
}

function clearShareValidationErrors() {
    $('#share-error-messages').addClass('d-none').empty();
    $('#memberShareForm .is-invalid').removeClass('is-invalid');
    $('#memberShareForm .invalid-feedback').remove();
}

function statusBadge(status, label) {
    const cls = status === 'anulado' ? 'danger' : 'success';
    return `<span class="badge badge-${cls}">${escapeHtml(label || status || '-')}</span>`;
}

function setValue(name, value) {
    $(`[name="${name}"]`).val(value || '');
}

function trimQuantity(value) {
    const number = parseFloat(value) || 0;
    return number.toFixed(4).replace(/\.?0+$/, '');
}

function setLoading(show) {
    if (divLoading) {
        divLoading.style.display = show ? 'flex' : 'none';
    }
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
