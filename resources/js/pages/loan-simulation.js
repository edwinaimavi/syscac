var divLoading = document.getElementById('divLoading');
let tableLoanSimulation;
let loanSimulationPreviewRequest = null;
let currentLoanSimulationEligibility = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    tableLoanSimulation = $('#tableLoanSimulation').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.loanSimulationRoutes.list,
            data: function (data) {
                data.date_from = $('#loan_sim_filter_date_from').val();
                data.date_to = $('#loan_sim_filter_date_to').val();
                data.member_id = $('#loan_sim_filter_member_id').val();
                data.status = $('#loan_sim_filter_status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code', defaultContent: '-' },
            { data: 'simulation_date', name: 'simulation_date' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'member_dni', name: 'member.dni' },
            { data: 'amount', name: 'amount' },
            { data: 'interest_rate', name: 'interest_rate' },
            { data: 'term_months', name: 'term_months' },
            { data: 'total_interest', name: 'total_interest' },
            { data: 'total_payment', name: 'total_payment' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });

    loadLoanSimulationSummary();
    tableLoanSimulation.on('draw', loadLoanSimulationSummary);

    $('#btnNewLoanSimulation').on('click', function () {
        resetLoanSimulationForm();
        fetchNextLoanSimulationCode();
        $('#loanSimulationModal').modal('show');
    });

    $('#loanSimulationModal').on('hidden.bs.modal', resetLoanSimulationForm);

    $('#loan_sim_filter_date_from, #loan_sim_filter_date_to, #loan_sim_filter_member_id, #loan_sim_filter_status').on('change', function () {
        tableLoanSimulation.ajax.reload();
    });

    $('#btnClearLoanSimulationFilters').on('click', function () {
        $('#loan_sim_filter_date_from').val('');
        $('#loan_sim_filter_date_to').val('');
        $('#loan_sim_filter_member_id').val('');
        $('#loan_sim_filter_status').val('');
        tableLoanSimulation.ajax.reload();
    });

    $('#loanSimulationForm [name="amount"], #loanSimulationForm [name="interest_rate"], #loanSimulationForm [name="term_months"], #loanSimulationForm [name="start_date"], #loanSimulationForm [name="first_payment_date"], #loanSimulationForm [name="member_id"], #loanSimulationForm [name="guarantor_member_id"], #loanSimulationForm [name="simulation_date"], #loanSimulationForm [name="amortization_method"]').on('input change', debounce(calculateLoanSimulationPreview, 350));
    $('#loanSimulationForm [name="member_id"]').on('change', function () {
        $('#loanSimulationMinorWarning').toggleClass('d-none', $(this).find(':selected').data('minor') !== 1);
    });

    $('#btnCalculateLoanSimulation').on('click', calculateLoanSimulationPreview);

    $('#loanSimulationForm').on('submit', function (event) {
        event.preventDefault();
        clearLoanSimulationValidationErrors();

        if (currentLoanSimulationEligibility?.withdrawal_blocked) {
            showLoanSimulationValidationErrors({ member_id: ['No se puede generar préstamo. El socio tiene un proceso de retiro/cierre pendiente o confirmado.'] });
            return;
        }

        if (currentLoanSimulationEligibility?.requires_guarantor && !$('#loanSimulationForm [name="guarantor_member_id"]').val()) {
            showLoanSimulationValidationErrors({ guarantor_member_id: [guarantorRequiredMessage(currentLoanSimulationEligibility)] });
            return;
        }

        setLoading(true);

        const form = this;
        const id = $(form).attr('data-id');
        const formData = new FormData(form);
        let url = window.loanSimulationRoutes.store;

        if (id) {
            url = `${window.loanSimulationRoutes.base}/${id}`;
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
                $('#loanSimulationModal').modal('hide');
                tableLoanSimulation.ajax.reload(null, false);
                loadLoanSimulationSummary();
                toast(response.message, 'success');
            },
            error: function (xhr) {
                setLoading(false);

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showLoanSimulationValidationErrors(xhr.responseJSON.errors);
                    return;
                }

                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar la simulacion.', 'error');
            }
        });
    });

    $(document).on('click', '.editLoanSimulation', function () {
        const id = $(this).data('id');
        setLoading(true);

        $.get(`${window.loanSimulationRoutes.base}/${id}/edit`, function (simulation) {
            setLoading(false);
            fillLoanSimulationForm(simulation);
            $('#loanSimulationModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro la simulacion solicitada.', 'error');
        });
    });

    $(document).on('click', '.showLoanSimulation', function () {
        const id = $(this).data('id');
        setLoading(true);

        $.get(`${window.loanSimulationRoutes.base}/${id}`, function (simulation) {
            setLoading(false);
            fillLoanSimulationDetail(simulation);
            $('#loanSimulationDetailModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro la simulacion solicitada.', 'error');
        });
    });

    $(document).on('click', '.annulLoanSimulation', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Anular simulacion',
            text: 'La simulacion quedara en historial y no se usara como vigente.',
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Motivo obligatorio',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar',
            inputValidator: value => !value?.trim() ? 'El motivo es obligatorio.' : undefined
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.post(`${window.loanSimulationRoutes.base}/${id}/anular`, { reason: result.value.trim() }, function (response) {
                tableLoanSimulation.ajax.reload(null, false);
                loadLoanSimulationSummary();
                toast(response.message, 'success');
            }).fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo anular la simulacion.', 'error');
            });
        });
    });

    $(document).on('click', '.effectLoanSimulation', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Dejar simulación sin efecto', input: 'textarea', inputLabel: 'Motivo obligatorio', inputPlaceholder: 'Indique por qué ya no se utilizará.', showCancelButton: true, confirmButtonText: 'Dejar sin efecto', cancelButtonText: 'Cancelar', inputValidator: value => !value?.trim() ? 'El motivo es obligatorio.' : undefined }).then(result => {
            if (!result.isConfirmed) return;
            $.post(`${window.loanSimulationRoutes.base}/${id}/sin-efecto`, { reason: result.value.trim() }, response => {
                tableLoanSimulation.ajax.reload(null, false);
                loadLoanSimulationSummary();
                toast(response.message, 'success');
            }).fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo actualizar la simulación.', 'error'));
        });
    });
});

function resetLoanSimulationForm() {
    const form = $('#loanSimulationForm');
    form[0].reset();
    form.removeAttr('data-id');
    clearLoanSimulationValidationErrors();

    $('#loanSimulationModalLabel').text('Nuevo simulador de prestamo');
    $('#loanSimulationSaveText').text('Generar simulacion');
    $('[name="simulation_date"]').val(new Date().toISOString().slice(0, 10));
    $('[name="start_date"]').val(new Date().toISOString().slice(0, 10));
    $('[name="status"]').val('simulada');
    $('[name="amortization_method"]').val('aleman');
    $('[name="interest_type"]').val('mensual');
    $('[name="guarantor_member_id"]').val('').prop('required', false);
    currentLoanSimulationEligibility = null;
    setLoanSimulationWithdrawalBlocked(false);
    if (loanSimulationPreviewRequest) loanSimulationPreviewRequest.abort();
    loanSimulationPreviewRequest = null;
    $('#loanSimulationEligibility').addClass('d-none').empty();
    $('#loanSimulationGuarantorGroup').removeClass('is-required');
    $('#loanSimulationGuarantorRequired').addClass('d-none');
    $('#loanSimulationGuarantorHelp').text('Opcional mientras el monto no supere el limite permitido.');
    $('#loanSimulationForm [name="guarantor_member_id"] option').prop('disabled', false).removeClass('d-none');
    setLoanSimulationCode(window.loanSimulationRoutes.nextCodeValue || 'SIM-000001');
    resetLoanSimulationPreview();
}

function fetchNextLoanSimulationCode() {
    $.get(window.loanSimulationRoutes.nextCode, function (response) {
        setLoanSimulationCode(response.code || 'SIM-000001');
    });
}

function setLoanSimulationCode(code) {
    $('[name="code"]').val(code);
    $('#loanSimulationSideCode').text(code);
}

function fillLoanSimulationForm(simulation) {
    resetLoanSimulationForm();
    const form = $('#loanSimulationForm');
    form.attr('data-id', simulation.id);

    $('#loanSimulationModalLabel').text('Editar simulacion de prestamo');
    $('#loanSimulationSaveText').text('Actualizar simulacion');
    ensureHistoricalSimulationMemberOption(simulation);
    setLoanSimulationCode(simulation.code || 'Sin codigo');
    setValue('simulation_date', simulation.simulation_date);
    setValue('member_id', simulation.member_id);
    setValue('guarantor_member_id', simulation.guarantor_member_id);
    setValue('status', simulation.status);
    setValue('amount', simulation.amount);
    setValue('interest_rate', simulation.interest_rate);
    setValue('term_months', simulation.term_months);
    setValue('start_date', simulation.start_date);
    setValue('first_payment_date', simulation.first_payment_date);
    setValue('amortization_method', simulation.amortization_method || 'aleman');
    setValue('observation', simulation.observation);

    updateLoanSimulationPreviewFromSummary({
        fixed_principal_formatted: simulation.fixed_principal_formatted,
        total_interest_formatted: simulation.total_interest_formatted,
        total_payment_formatted: simulation.total_payment_formatted,
        first_installment_formatted: simulation.first_installment_formatted,
        last_installment_formatted: simulation.last_installment_formatted
    });
    showLoanSimulationEligibility(simulation.eligibility);
    calculateLoanSimulationPreview();
}

function calculateLoanSimulationPreview() {
    const form = $('#loanSimulationForm');
    const amount = parseFloat($('[name="amount"]').val()) || 0;
    const rate = parseFloat($('[name="interest_rate"]').val());
    const months = parseInt($('[name="term_months"]').val(), 10) || 0;
    const member = $('[name="member_id"]').val();
    const startDate = $('[name="start_date"]').val();

    if (!member || amount <= 0 || Number.isNaN(rate) || months < 1 || !startDate) {
        resetLoanSimulationPreview();
        return;
    }

    if (loanSimulationPreviewRequest) loanSimulationPreviewRequest.abort();
    loanSimulationPreviewRequest = $.ajax({
        url: window.loanSimulationRoutes.calculate,
        type: 'POST',
        data: form.serialize(),
        success: function (response) {
            updateLoanSimulationPreviewFromSummary(response.summary || {});
            showLoanSimulationEligibility(response.eligibility);
        },
        error: function (xhr) {
            if (xhr.statusText === 'abort') return;
            resetLoanSimulationPreview();
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                showLoanSimulationValidationErrors(xhr.responseJSON.errors);
            }
        },
        complete: function () {
            loanSimulationPreviewRequest = null;
        }
    });
}

function showLoanSimulationEligibility(evaluation) {
    currentLoanSimulationEligibility = evaluation || null;
    if (!evaluation) {
        setLoanSimulationWithdrawalBlocked(false);
        updateGuarantorRequirement(null);
        return $('#loanSimulationEligibility').addClass('d-none').empty();
    }
    const withdrawalBlocked = Boolean(evaluation.withdrawal_blocked) || evaluation.can_request_loan === false;
    setLoanSimulationWithdrawalBlocked(withdrawalBlocked);
    if (withdrawalBlocked) {
        updateGuarantorRequirement(null);
        return $('#loanSimulationEligibility').removeClass('d-none is-success is-warning').addClass('is-danger').html(
            `<div class="loan-simulation-eligibility-title"><i class="fas fa-ban"></i><strong>No habilitado para préstamo</strong></div>` +
            `<p class="mb-3">${escapeHtml(evaluation.withdrawal_message || 'Socio no habilitado para préstamo. Tiene un proceso de retiro/cierre pendiente o confirmado.')}</p>` +
            `<div class="loan-simulation-eligibility-grid"><div><span>Estado</span><strong>No habilitado</strong></div><div><span>Motivo</span><strong>${escapeHtml(evaluation.withdrawal_reason || 'Retiro pendiente o confirmado')}</strong></div><div><span>Saldo en contra</span><strong>${escapeHtml(evaluation.withdrawal_balance_against_formatted || 'S/ 0.00')}</strong></div><div class="span-2"><span>Acción sugerida</span><strong>${escapeHtml(evaluation.withdrawal_action || 'Regularizar deuda o anular el cierre si corresponde.')}</strong></div></div>`
        );
    }
    if (evaluation.guarantor_eligible === false) {
        $('#loanSimulationSaveButton').prop('disabled', true).attr('title', 'El garante tiene un proceso de retiro/cierre.');
        updateGuarantorRequirement(null);
        return $('#loanSimulationEligibility').removeClass('d-none is-success is-warning').addClass('is-danger').html(
            `<div class="loan-simulation-eligibility-title"><i class="fas fa-ban"></i><strong>Garante no habilitado</strong></div><p class="mb-0">El garante seleccionado tiene un proceso de retiro/cierre pendiente o confirmado. Seleccione otro garante vigente.</p>`
        );
    }
    const status = evaluation.eligible_by_contribution_count ? (evaluation.contribution_count >= 3 ? 'Habilitado para prestamo' : 'No aplica minimo') : 'No habilitado';
    const reason = guarantorReason(evaluation.guarantor_requirement_reason);
    $('#loanSimulationEligibility').removeClass('d-none').html(
        `<div class="loan-simulation-eligibility-title"><i class="fas fa-user-check"></i><strong>Evaluacion del socio</strong></div>` +
        `<div class="loan-simulation-eligibility-grid">` +
        `<div><span>Tipo</span><strong>${evaluation.member_type === 'antiguo' ? 'Antiguo' : 'Nuevo'}</strong></div>` +
        `<div><span>Antiguedad</span><strong>${escapeHtml(evaluation.membership_time || '-')}</strong></div>` +
        `<div><span>Aportes</span><strong>${evaluation.contribution_count}/3</strong><small>${escapeHtml(status)}</small></div>` +
        `<div><span>Total aportado</span><strong>S/ ${Number(evaluation.total_contributions).toFixed(2)}</strong></div>` +
        `<div><span>Limite sin garante</span><strong>S/ ${Number(evaluation.loan_limit_without_guarantor).toFixed(2)}</strong></div>` +
        `<div><span>Requiere garante</span><strong class="${evaluation.requires_guarantor ? 'text-warning' : 'text-success'}">${evaluation.requires_guarantor ? 'Si' : 'No'}</strong></div>` +
        `<div><span>Historial crediticio</span><strong class="credit-risk-${escapeHtml(evaluation.credit_history?.color || 'verde')}">${escapeHtml(evaluation.credit_history?.label || 'Excelente')} · ${Number(evaluation.credit_history?.score ?? 100)}/100</strong></div>` +
        `<div><span>Atrasos leves / graves</span><strong>${Number(evaluation.credit_history?.mild_late || 0)} / ${Number(evaluation.credit_history?.serious_late || 0)}</strong></div>` +
        `<div><span>Deuda vencida</span><strong>${escapeHtml(evaluation.credit_history?.active_overdue_amount_formatted || 'S/ 0.00')}</strong></div>` +
        `<div class="span-2"><span>Recomendación</span><strong>${escapeHtml(evaluation.credit_history?.recommendation || 'Evaluar según las reglas generales.')}</strong></div>` +
        `${evaluation.requires_guarantor ? `<div class="span-2"><span>Motivo</span><strong>${escapeHtml(reason)}</strong></div>` : ''}` +
        `</div>`
    );
    updateGuarantorRequirement(evaluation);
}

function setLoanSimulationWithdrawalBlocked(blocked) {
    $('#loanSimulationSaveButton').prop('disabled', blocked).attr('title', blocked ? 'Bloqueado por proceso de retiro/cierre.' : '');
    $('#loanSimulationForm [name="guarantor_member_id"], #loanSimulationForm [name="amount"], #loanSimulationForm [name="interest_rate"], #loanSimulationForm [name="term_months"], #loanSimulationForm [name="start_date"], #loanSimulationForm [name="first_payment_date"], #loanSimulationForm [name="observation"]').prop('disabled', blocked);
}

function ensureHistoricalSimulationMemberOption(simulation) {
    const select = $('#loanSimulationForm [name="member_id"]');
    if (!simulation.member_id || select.find(`option[value="${simulation.member_id}"]`).length) return;
    const suffix = simulation.eligibility?.withdrawal_blocked ? ' - No habilitado: retiro/cierre' : '';
    select.append(new Option(`${simulation.member_code || 'SOCIO'} - ${simulation.member_dni || '-'} - ${simulation.member_name || '-'}${suffix}`, simulation.member_id));
}

function updateGuarantorRequirement(evaluation) {
    const requires = Boolean(evaluation?.requires_guarantor);
    const memberId = String($('#loanSimulationForm [name="member_id"]').val() || '');
    const amount = parseFloat($('#loanSimulationForm [name="amount"]').val()) || 0;
    const select = $('#loanSimulationForm [name="guarantor_member_id"]');
    let available = 0;

    select.prop('required', requires);
    select.find('option[value!=""]').each(function () {
        const eligible = String(this.value) !== memberId && Number($(this).data('contributions') || 0) >= amount;
        $(this).prop('disabled', requires && !eligible).toggleClass('d-none', requires && !eligible);
        if (eligible) available++;
    });
    if (select.find('option:selected').prop('disabled')) select.val('');

    $('#loanSimulationGuarantorGroup').toggleClass('is-required', requires);
    $('#loanSimulationGuarantorRequired').toggleClass('d-none', !requires);
    $('#loanSimulationGuarantorHelp').text(requires
        ? (available ? 'Seleccione un socio vigente con aportes suficientes.' : 'No hay socios garantes con aportes suficientes para este monto.')
        : 'No requiere garante; puede seleccionarlo de forma opcional.');
}

function guarantorReason(reason) {
    const reasons = String(reason || '').split(',');
    const labels = [];
    if (reasons.includes('supera_limite_aportes')) labels.push('Supera el limite permitido segun aportes.');
    if (reasons.includes('supera_7000')) labels.push('Supera S/ 7,000.');
    return labels.join(' ') || '-';
}

function guarantorRequiredMessage(evaluation) {
    return `Este prestamo requiere garante. ${guarantorReason(evaluation?.guarantor_requirement_reason)}`.trim();
}

function updateLoanSimulationPreviewFromSummary(summary) {
    $('#loanSimulationPreviewPrincipal, #loanSimulationSidePrincipal').text(summary.fixed_principal_formatted || 'S/ 0.00');
    $('#loanSimulationPreviewInterest, #loanSimulationSideInterest').text(summary.total_interest_formatted || 'S/ 0.00');
    $('#loanSimulationPreviewTotal, #loanSimulationSideTotal').text(summary.total_payment_formatted || 'S/ 0.00');
    $('#loanSimulationPreviewFirst').text(summary.first_installment_formatted || 'S/ 0.00');
    $('#loanSimulationPreviewLast').text(summary.last_installment_formatted || 'S/ 0.00');
}

function resetLoanSimulationPreview() {
    updateLoanSimulationPreviewFromSummary({});
    showLoanSimulationEligibility(null);
}

function fillLoanSimulationDetail(simulation) {
    $('#detailLoanSimulationCode').text(simulation.code || '-');
    $('#detailLoanSimulationMember').text(simulation.member_name || '-');
    $('#detailLoanSimulationStatus').html(statusBadge(simulation.status, simulation.status_label));
    $('#detailLoanSimulationAmount').text(simulation.amount_formatted || 'S/ 0.00');
    $('#detailLoanSimulationInterest').text(simulation.total_interest_formatted || 'S/ 0.00');
    $('#detailLoanSimulationTotal').text(simulation.total_payment_formatted || 'S/ 0.00');
    $('#detailLoanSimulationDni').text(simulation.member_dni || '-');
    $('#detailLoanSimulationRate').text(simulation.interest_rate_formatted || '-');
    $('#detailLoanSimulationTerm').text(simulation.term_months_formatted || '-');
    $('#detailLoanSimulationMethod').text(simulation.amortization_method_label || 'Aleman');
    $('#detailLoanSimulationPrincipal').text(simulation.fixed_principal_formatted || '-');
    $('#detailLoanSimulationCount').text((simulation.installments || []).length);
    $('#detailLoanSimulationStart').text(simulation.start_date_formatted || '-');
    $('#detailLoanSimulationFirstDate').text(simulation.first_payment_date_formatted || '-');
    $('#detailLoanSimulationPrintLink').attr('href', simulation.print_url || '#');
    $('#detailLoanSimulationObservation').text(simulation.observation || 'Sin observaciones');
    $('#detailLoanSimulationLoanCode').text(simulation.converted_loan_number || '-');
    $('#detailLoanSimulationConvertedAt').text(simulation.converted_at || '-');
    $('#detailLoanSimulationConvertedBy').text(simulation.converted_by_name || '-');
    $('#detailLoanSimulationAuditStatus').text(simulation.status_label || '-');
    $('#detailLoanSimulationEffectReason').text(simulation.effect_reason || '-');
    $('#detailLoanSimulationEffectedBy').text(simulation.effected_by_name || simulation.converted_by_name || simulation.annulled_by_name || '-');
    $('#detailLoanSimulationEffectedAt').text(simulation.effected_at || simulation.converted_at || simulation.annulled_at || '-');
    const eligibility = simulation.eligibility || {};
    $('#detailLoanSimulationMemberType').text(eligibility.member_type === 'antiguo' ? 'Antiguo' : 'Nuevo');
    $('#detailLoanSimulationContributionCount').text(eligibility.contribution_count ?? '-');
    $('#detailLoanSimulationContributions').text(`S/ ${Number(eligibility.total_contributions || 0).toFixed(2)}`);
    $('#detailLoanSimulationLimit').text(`S/ ${Number(eligibility.loan_limit_without_guarantor || 0).toFixed(2)}`);
    $('#detailLoanSimulationRequiresGuarantor').text(eligibility.requires_guarantor ? 'Si' : 'No');
    $('#detailLoanSimulationGuarantor').text(eligibility.requires_guarantor ? (eligibility.guarantor_name || 'No registrado') : 'No aplica');
    $('#detailLoanSimulationReasonBox').toggleClass('d-none', !eligibility.requires_guarantor);
    $('#detailLoanSimulationReason').text(guarantorReason(eligibility.guarantor_requirement_reason));
    $('#detailLoanSimulationNotConverted').toggleClass('d-none', Boolean(simulation.converted_loan_url));
    $('#detailLoanSimulationConversionCard .member-detail-grid').toggleClass('d-none', !simulation.converted_loan_url);

    if (simulation.converted_loan_url) {
        $('#detailLoanSimulationLoanLink').removeClass('d-none').attr('href', simulation.converted_loan_url);
    } else {
        $('#detailLoanSimulationLoanLink').addClass('d-none').attr('href', '#');
    }

    const rows = (simulation.installments || []).map((row) => `
        <tr>
            <td>${escapeHtml(row.installment_number)}</td>
            <td>${escapeHtml(row.due_date_formatted || '-')}</td>
            <td>${escapeHtml(row.opening_balance_formatted || 'S/ 0.00')}</td>
            <td>${escapeHtml(row.principal_amount_formatted || 'S/ 0.00')}</td>
            <td>${escapeHtml(row.interest_amount_formatted || 'S/ 0.00')}</td>
            <td>${escapeHtml(row.installment_amount_formatted || 'S/ 0.00')}</td>
            <td>${escapeHtml(row.closing_balance_formatted || 'S/ 0.00')}</td>
        </tr>
    `).join('');

    $('#detailLoanSimulationSchedule').html(rows || '<tr><td colspan="7">Sin cronograma registrado.</td></tr>');
}

function loadLoanSimulationSummary() {
    $.get(window.loanSimulationRoutes.summary, {
        date_from: $('#loan_sim_filter_date_from').val(),
        date_to: $('#loan_sim_filter_date_to').val()
    }, function (summary) {
        $('#loanSimulationSummaryCurrent').text(`S/ ${summary.total_simulado_vigente || '0.00'}`);
        $('#loanSimulationSummaryConverted').text(`S/ ${summary.total_convertido || '0.00'}`);
        $('#loanSimulationSummaryRecords').text(summary.total_registros ?? '0');
        $('#loanSimulationSummaryLast').text(summary.ultima_simulacion || '-');
    });
}

function showLoanSimulationValidationErrors(errors) {
    clearLoanSimulationValidationErrors();
    let list = '<ul class="mb-0">';

    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`[name="${key}"]`);
        input.addClass('is-invalid');
        placeLoanSimulationFieldError(input, messages[0]);
    });

    list += '</ul>';
    $('#loan-simulation-error-messages').removeClass('d-none').html(list);
}

function placeLoanSimulationFieldError(input, message) {
    const feedback = `<div class="invalid-feedback d-block">${message}</div>`;

    if (input.closest('.input-group').length) {
        input.closest('.input-group').after(feedback);
        return;
    }

    input.after(feedback);
}

function clearLoanSimulationValidationErrors() {
    $('#loan-simulation-error-messages').addClass('d-none').empty();
    $('#loanSimulationForm .is-invalid').removeClass('is-invalid');
    $('#loanSimulationForm .invalid-feedback').remove();
}

function statusBadge(status, label) {
    const cls = status === 'anulada' ? 'danger' : (status === 'convertida' ? 'info' : 'success');
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

function debounce(callback, wait) {
    let timeout;
    return function () {
        clearTimeout(timeout);
        timeout = setTimeout(callback, wait);
    };
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
