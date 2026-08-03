var divLoading = document.getElementById('divLoading');
let tableCashMovement;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    tableCashMovement = $('#tableCashMovement').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.cashMovementRoutes.list,
            data: function (data) {
                data.date_from = $('#cash_filter_date_from').val();
                data.date_to = $('#cash_filter_date_to').val();
                data.type = $('#cash_filter_type').val();
                data.category = $('#cash_filter_category').val();
                data.status = $('#cash_filter_status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'movement_number', name: 'movement_number', defaultContent: '-' },
            { data: 'movement_date', name: 'movement_date' },
            { data: 'type', name: 'type' },
            { data: 'category', name: 'category' },
            { data: 'concept', name: 'concept' },
            { data: 'payment_method', name: 'payment_method' },
            { data: 'amount', name: 'amount' },
            { data: 'balance_after', name: 'balance_after' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });

    populateCategorySelect($('#cash_filter_category'), '', true);
    populateCategorySelect($('[name="category"]'), 'ingreso');
    loadCashSummary();
    tableCashMovement.on('draw', loadCashSummary);

    $('#btnNewCashMovement').on('click', function () {
        resetCashForm();
        fetchNextCashCode();
        $('#cashMovementModal').modal('show');
    });

    $('#cashMovementModal').on('hidden.bs.modal', resetCashForm);

    $('#cash_filter_date_from, #cash_filter_date_to, #cash_filter_type, #cash_filter_category, #cash_filter_status').on('change', function () {
        if (this.id === 'cash_filter_type') {
            populateCategorySelect($('#cash_filter_category'), $(this).val(), true);
        }

        tableCashMovement.ajax.reload();
    });

    $('#btnClearCashFilters').on('click', function () {
        $('#cash_filter_date_from').val('');
        $('#cash_filter_date_to').val('');
        $('#cash_filter_type').val('');
        $('#cash_filter_status').val('');
        populateCategorySelect($('#cash_filter_category'), '', true);
        tableCashMovement.ajax.reload();
    });

    $('[name="type"]').on('change', function () {
        populateCategorySelect($('[name="category"]'), $(this).val());
        updateCashSide();
    });

    $('[name="amount"], [name="status"]').on('input change', updateCashSide);

    $('#cashVoucher').on('change', function () {
        const file = this.files && this.files.length ? this.files[0] : null;
        $('#cashVoucherName').text(file ? file.name : 'JPG, PNG, WEBP o PDF - max. 4 MB');
    });

    $('#cashMovementForm').on('submit', function (event) {
        event.preventDefault();
        clearCashValidationErrors();
        setLoading(true);

        const form = this;
        const id = $(form).attr('data-id');
        const formData = new FormData(form);
        let url = window.cashMovementRoutes.store;

        if (id) {
            url = `${window.cashMovementRoutes.base}/${id}`;
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
                $('#cashMovementModal').modal('hide');
                tableCashMovement.ajax.reload(null, false);
                loadCashSummary();
                toast(response.message, 'success');
            },
            error: function (xhr) {
                setLoading(false);

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showCashValidationErrors(xhr.responseJSON.errors);
                    return;
                }

                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar el movimiento.', 'error');
            }
        });
    });

    $(document).on('click', '.editCashMovement', function () {
        const id = $(this).data('id');
        setLoading(true);

        $.get(`${window.cashMovementRoutes.base}/${id}/edit`, function (movement) {
            setLoading(false);
            fillCashForm(movement);
            $('#cashMovementModal').modal('show');
        }).fail(function (xhr) {
            setLoading(false);
            Swal.fire('Error', xhr.responseJSON?.message || 'No se encontro el movimiento solicitado.', 'error');
        });
    });

    $(document).on('click', '.showCashMovement', function () {
        const id = $(this).data('id');
        setLoading(true);

        $.get(`${window.cashMovementRoutes.base}/${id}`, function (movement) {
            setLoading(false);
            fillCashDetail(movement);
            $('#cashMovementDetailModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro el movimiento solicitado.', 'error');
        });
    });

    $(document).on('click', '.annulCashMovement', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Anular movimiento',
            text: 'El movimiento quedara en historial y no contara para el saldo general.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.post(`${window.cashMovementRoutes.base}/${id}/anular`, function (response) {
                tableCashMovement.ajax.reload(null, false);
                loadCashSummary();
                toast(response.message, 'success');
            }).fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo anular el movimiento.', 'error');
            });
        });
    });

    const movementId = new URLSearchParams(window.location.search).get('movement_id');
    if (movementId) {
        setLoading(true);
        $.get(`${window.cashMovementRoutes.base}/${movementId}`, function (movement) {
            setLoading(false);
            fillCashDetail(movement);
            $('#cashMovementDetailModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontró el movimiento solicitado.', 'error');
        });
    }
});

function resetCashForm() {
    const form = $('#cashMovementForm');
    form[0].reset();
    form.removeAttr('data-id');
    clearCashValidationErrors();

    $('#cashMovementModalLabel').text('Nuevo movimiento');
    $('#cashMovementSaveText').text('Guardar movimiento');
    $('[name="movement_date"]').val(new Date().toISOString().slice(0, 10));
    $('[name="type"]').val('ingreso');
    $('[name="status"]').val('registrado');
    populateCategorySelect($('[name="category"]'), 'ingreso');
    $('#cashVoucherName').text('JPG, PNG, WEBP o PDF - max. 4 MB');
    $('#cashCurrentVoucherBox').addClass('d-none');
    $('#cashCurrentVoucherLink').attr('href', '#');
    setCashCode(window.cashMovementRoutes.nextCodeValue || 'CAJ-000001');
    updateCashSide();
}

function fetchNextCashCode() {
    $.get(window.cashMovementRoutes.nextCode, function (response) {
        setCashCode(response.code || 'CAJ-000001');
    });
}

function setCashCode(code) {
    $('[name="movement_number"]').val(code);
    $('#cashSideCode').text(code);
}

function fillCashForm(movement) {
    resetCashForm();
    const form = $('#cashMovementForm');
    form.attr('data-id', movement.id);

    $('#cashMovementModalLabel').text('Editar movimiento');
    $('#cashMovementSaveText').text('Actualizar movimiento');
    setCashCode(movement.movement_number || 'Sin codigo');
    setValue('movement_date', movement.movement_date);
    setValue('type', movement.type);
    populateCategorySelect($('[name="category"]'), movement.type, false, movement.category);
    setValue('concept', movement.concept);
    setValue('amount', movement.amount);
    setValue('payment_method', movement.payment_method);
    setValue('reference', movement.reference);
    setValue('status', movement.status);
    setValue('observation', movement.observation);

    if (movement.voucher_status === 'available' && movement.voucher_url) {
        $('#cashCurrentVoucherBox').removeClass('d-none');
        $('#cashCurrentVoucherLink').attr('href', movement.voucher_url);
    }

    updateCashSide();
}

function fillCashDetail(movement) {
    $('#detailCashCode').text(movement.movement_number || '-');
    $('#detailCashConcept').text(movement.concept || '-');
    $('#detailCashStatus').html(statusBadge(movement.status, movement.status_label));
    $('#detailCashType').text(movement.type_label || '-');
    $('#detailCashAmount').text(movement.amount_formatted || 'S/ 0.00');
    $('#detailCashBalanceAfter').text(movement.balance_after_formatted || '-');
    $('#detailCashDate').text(movement.movement_date_formatted || '-');
    $('#detailCashCategory').text(movement.category_label || '-');
    $('#detailCashPaymentMethod').text(movement.payment_method_label || '-');
    $('#detailCashReference').text(movement.reference_display || '-');
    $('#detailCashBalanceBefore').text(movement.balance_before_formatted || '-');
    $('#detailCashBalanceAfterCard').text(movement.balance_after_formatted || '-');
    $('#detailCashCreatedAt').text(movement.created_at || '-');
    $('#detailCashCreatedBy').text(movement.created_by_name || '-');
    $('#detailCashObservation').text(movement.observation || '-');

    fillCashOrigin(movement.origin || {});
    fillCashVoucher(movement);
}

function fillCashVoucher(movement) {
    const message = movement.voucher_message || 'Sin comprobante registrado';

    if (movement.voucher_status === 'available' && movement.voucher_url) {
        $('#detailCashVoucherLink')
            .removeClass('disabled')
            .attr('href', movement.voucher_url)
            .html('<i class="fas fa-file-alt mr-1"></i> Ver comprobante');
        $('#detailCashVoucherText').addClass('d-none').text('');
    } else {
        $('#detailCashVoucherLink')
            .addClass('disabled')
            .attr('href', '#')
            .html(`<i class="fas fa-file-alt mr-1"></i> ${escapeHtml(message)}`);
        $('#detailCashVoucherText').removeClass('d-none').text(message);
    }
}

function fillCashOrigin(origin) {
    $('#detailCashOriginType').text(origin.type || '-');
    $('#detailCashOriginCode').text(origin.code || '-');
    $('#detailCashOriginMember').text(origin.member || '-');
    $('#detailCashOriginLoan').text(origin.loan || '-');
    $('#detailCashOriginModule').text(origin.module || '-');
    const breakdown = origin.financial_breakdown;
    $('#detailCashPaymentBreakdown').toggleClass('d-none', !breakdown);
    if (breakdown) {
        $('#detailCashPaymentCapital').text(breakdown.capital || 'S/ 0.00');
        $('#detailCashPaymentInterest').text(breakdown.interest || 'S/ 0.00');
        $('#detailCashPaymentLateFee').text(breakdown.late_fee || 'S/ 0.00');
        $('#detailCashPaymentLateFeeWaived').text(breakdown.late_fee_waived || 'S/ 0.00');
        $('#detailCashPaymentTotal').text(breakdown.total || 'S/ 0.00');
    }
    $('#detailCashOriginTechnical').text(origin.technical_relation || '-');

    if (origin.url) {
        $('#detailCashOriginLink')
            .removeClass('disabled')
            .attr('href', origin.url)
            .html('<i class="fas fa-external-link-alt mr-1"></i> Ver registro origen');
    } else {
        $('#detailCashOriginLink')
            .addClass('disabled')
            .attr('href', '#')
            .html('<i class="fas fa-external-link-alt mr-1"></i> Sin enlace disponible');
    }
}

function populateCategorySelect(select, type = '', includeAll = false, selected = '') {
    const income = window.cashMovementRoutes.incomeCategories || {};
    const expense = window.cashMovementRoutes.expenseCategories || {};
    const categories = type === 'egreso' ? expense : (type === 'ingreso' ? income : { ...income, ...expense });

    select.empty();

    if (includeAll) {
        select.append('<option value="">Todas</option>');
    }

    $.each(categories, function (value, label) {
        select.append(`<option value="${value}">${label}</option>`);
    });

    if (selected) {
        select.val(selected);
    }
}

function loadCashSummary() {
    $.get(window.cashMovementRoutes.summary, {
        date_from: $('#cash_filter_date_from').val(),
        date_to: $('#cash_filter_date_to').val()
    }, function (summary) {
        $('#cashSummaryBalance').text(`S/ ${summary.balance || '0.00'}`);
        $('#cashSummaryIncome').text(`S/ ${summary.income || '0.00'}`);
        $('#cashSummaryExpense').text(`S/ ${summary.expense || '0.00'}`);
        $('#cashSummaryMonth').text(summary.month_movements || '0');
    });
}

function updateCashSide() {
    const amount = parseFloat($('[name="amount"]').val()) || 0;
    const type = $('[name="type"]').val() || 'ingreso';
    const status = $('[name="status"]').val() || 'registrado';

    $('#cashSideAmount').text(`S/ ${amount.toFixed(2)}`);
    $('#cashSideType').text(type === 'egreso' ? 'Egreso' : 'Ingreso');
    $('#cashSideStatus').text(status === 'anulado' ? 'Anulado' : 'Registrado');
}

function showCashValidationErrors(errors) {
    let list = '<ul class="mb-0">';

    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`[name="${key}"]`);
        input.addClass('is-invalid');
        placeCashFieldError(input, messages[0]);
    });

    list += '</ul>';
    $('#cash-error-messages').removeClass('d-none').html(list);
}

function placeCashFieldError(input, message) {
    const feedback = `<div class="invalid-feedback d-block">${message}</div>`;

    if (input.closest('.input-group').length) {
        input.closest('.input-group').after(feedback);
        return;
    }

    input.after(feedback);
}

function clearCashValidationErrors() {
    $('#cash-error-messages').addClass('d-none').empty();
    $('#cashMovementForm .is-invalid').removeClass('is-invalid');
    $('#cashMovementForm .invalid-feedback').remove();
}

function statusBadge(status, label) {
    const cls = status === 'anulado' ? 'danger' : 'success';
    return `<span class="badge badge-${cls}">${escapeHtml(label || status || '-')}</span>`;
}

function setValue(name, value) {
    $(`[name="${name}"]`).val(value || '');
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
