var divLoading = document.getElementById('divLoading');
let tableLoan;
let currentDisburseLoanId = null;
let loanCalculationVersion = 0;
let disbursementPreviewUrl = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    tableLoan = $('#tableLoan').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.loanRoutes.list,
            data: function (data) {
                data.date_from = $('#loan_filter_date_from').val();
                data.date_to = $('#loan_filter_date_to').val();
                data.member_id = $('#loan_filter_member_id').val();
                data.status = $('#loan_filter_status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'loan_number', name: 'loan_number' },
            { data: 'date', name: 'start_date' },
            { data: 'member_name', name: 'member.full_name' },
            { data: 'member_dni', name: 'member.dni' },
            { data: 'approved_amount', name: 'approved_amount' },
            { data: 'interest_rate', name: 'interest_rate' },
            { data: 'term_months', name: 'term_months' },
            { data: 'current_balance', name: 'current_balance' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' }
    });

    loadLoanSummary();
    tableLoan.on('draw', loadLoanSummary);

    $('#btnNewLoan').on('click', function () {
        resetLoanForm();
        fetchNextLoanCode();
        $('#loanModal').modal('show');
    });

    $('#loanModal').on('hidden.bs.modal', resetLoanForm);

    $('#loan_filter_date_from, #loan_filter_date_to, #loan_filter_member_id, #loan_filter_status').on('change', function () {
        tableLoan.ajax.reload();
    });

    $('#btnClearLoanFilters').on('click', function () {
        $('#loan_filter_date_from, #loan_filter_date_to, #loan_filter_member_id, #loan_filter_status').val('');
        tableLoan.ajax.reload();
    });

    $('[name="requested_amount"], [name="approved_amount"], [name="interest_rate"], [name="term_months"], [name="start_date"], [name="first_payment_date"], [name="member_id"], [name="guarantor_member_id"]').on('input change', function () {
        invalidateLoanCalculation();
        resetLoanPreview();
        showNeutralLoanEligibility($(this).attr('name') === 'member_id' && !this.value ? 'Seleccione un socio para evaluar.' : 'Complete los datos para calcular la evaluacion.');
        debouncedLoanCalculation();
    });
    $('#btnCalculateLoan').on('click', calculateLoanPreview);
    $('#loanForm [name="member_id"]').on('change', function () { loadPendingLoanSimulations(this.value); });
    $('#loanForm [name="member_id"]').on('change', function () {
        $('#loanMinorWarning').toggleClass('d-none', $(this).find(':selected').data('minor') !== 1);
    });

    $(document).on('click', '.takePendingSimulation', function () {
        $('#loanForm [name="loan_simulation_id"]').val($(this).data('id')).trigger('change');
    });

    $(document).on('click', '.effectPendingSimulation', function () {
        leaveSimulationWithoutEffect($(this).data('id'), () => loadPendingLoanSimulations($('#loanForm [name="member_id"]').val()));
    });

    $('[name="loan_simulation_id"]').on('change', function () {
        invalidateLoanCalculation();
        clearLoanValidationErrors();
        resetLoanPreview();
        showNeutralLoanEligibility('Complete los datos para calcular la evaluacion.');
        const option = $(this).find(':selected');
        setValue('guarantor_member_id', '');
        if (! option.val()) {
            ['member_id', 'requested_amount', 'approved_amount', 'interest_rate', 'term_months', 'first_payment_date'].forEach((name) => setValue(name, ''));
            setValue('start_date', new Date().toISOString().slice(0, 10));
            showNeutralLoanEligibility('Seleccione un socio para evaluar.');
            return;
        }
        setValue('member_id', option.data('member-id'));
        setValue('requested_amount', option.data('amount'));
        setValue('approved_amount', option.data('amount'));
        setValue('interest_rate', option.data('rate'));
        setValue('term_months', option.data('term'));
        setValue('start_date', option.data('start'));
        setValue('first_payment_date', option.data('first'));
        calculateLoanPreview();
    });

    $('#loanForm').on('submit', function (event) {
        event.preventDefault();
        clearLoanValidationErrors();
        setLoading(true);

        const id = $(this).attr('data-id');
        const formData = new FormData(this);
        let url = window.loanRoutes.store;
        if (id) {
            url = `${window.loanRoutes.base}/${id}`;
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url, type: 'POST', data: formData, processData: false, contentType: false,
            success: function (response) {
                setLoading(false);
                $('#loanModal').modal('hide');
                tableLoan.ajax.reload(null, false);
                loadLoanSummary();
                toast(response.message, 'success');
            },
            error: handleLoanAjaxError
        });
    });

    $(document).on('click', '.editLoan', function () {
        const id = $(this).data('id');
        setLoading(true);
        $.get(`${window.loanRoutes.base}/${id}/edit`, function (loan) {
            setLoading(false);
            fillLoanForm(loan);
            $('#loanModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro el prestamo solicitado.', 'error');
        });
    });

    $(document).on('click', '.showLoan', function () {
        const id = $(this).data('id');
        setLoading(true);
        $.get(`${window.loanRoutes.base}/${id}`, function (loan) {
            setLoading(false);
            fillLoanDetail(loan);
            $('#loanDetailModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro el prestamo solicitado.', 'error');
        });
    });

    $(document).on('click', '.scheduleLoan', function () {
        const id = $(this).data('id');
        setLoading(true);
        $.get(`${window.loanRoutes.base}/${id}/cronograma`, function (response) {
            setLoading(false);
            fillLoanSchedule(response.loan, response.installments || []);
            $('#loanScheduleModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se pudo cargar el cronograma.', 'error');
        });
    });

    $(document).on('click', '.approveLoan', function () {
        const id = $(this).data('id');
        $.get(`${window.loanRoutes.base}/${id}`, function (loan) {
            Swal.fire({
                title: 'Aprobar prestamo',
                html: `<strong>${escapeHtml(loan.member_name || '-')}</strong><br>${escapeHtml(loan.approved_amount_formatted)} - ${escapeHtml(loan.term_months_formatted)}<br>Total: ${escapeHtml(loan.total_amount_formatted)}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, aprobar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.post(`${window.loanRoutes.base}/${id}/aprobar`, afterLoanAction).fail(showActionError);
            });
        });
    });

    $(document).on('click', '.disburseLoan', function () {
        currentDisburseLoanId = $(this).data('id');
        clearLoanDisburseValidationErrors();
        $('#loanDisburseForm')[0].reset();
        resetDisbursementVoucher();
        updateDisbursementRequirements();
        $('[name="disbursed_at"]').val(new Date().toISOString().slice(0, 10));
        $.when($.get(`${window.loanRoutes.base}/${currentDisburseLoanId}`), $.get(window.loanRoutes.cashBalance))
            .done(function (loanResponse, balanceResponse) {
                const loan = loanResponse[0];
                const balance = balanceResponse[0];
                $('#disburseLoanCode').text(loan.loan_number || '-');
                $('#disburseLoanAmount').text(loan.approved_amount_formatted || 'S/ 0.00');
                $('#disburseLoanMember').text(loan.member_name || '-');
                $('#disburseCashBalance').text(`S/ ${balance.balance || '0.00'}`);
                $('#loanDisburseModal').modal('show');
            });
    });

    $('#loanDisburseForm [name="payment_method"]').on('change', updateDisbursementRequirements);

    $('#loanDisbursementVoucher').on('change', function () {
        renderDisbursementVoucher(this.files && this.files[0]);
    });

    $(document).on('click', '#removeLoanDisbursementVoucher', resetDisbursementVoucher);
    $('#loanDisburseModal').on('hidden.bs.modal', function () {
        currentDisburseLoanId = null;
        $('#loanDisburseForm')[0].reset();
        clearLoanDisburseValidationErrors();
        resetDisbursementVoucher();
        updateDisbursementRequirements();
    });

    $('#loanDisburseForm').on('submit', function (event) {
        event.preventDefault();
        clearLoanDisburseValidationErrors();
        if (!currentDisburseLoanId) return;
        setLoading(true);
        const formData = new FormData(this);
        $.ajax({
            url: `${window.loanRoutes.base}/${currentDisburseLoanId}/desembolsar`,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                setLoading(false);
                $('#loanDisburseModal').modal('hide');
                afterLoanAction(response);
            },
            error: function (xhr) {
                setLoading(false);
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showLoanDisburseValidationErrors(xhr.responseJSON.errors);
                    return;
                }
                showActionError(xhr);
            }
        });
    });

    $(document).on('click', '.annulLoan', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Anular prestamo',
            text: 'Solo se permite anular prestamos pendientes o aprobados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Si, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(`${window.loanRoutes.base}/${id}/anular`, afterLoanAction).fail(showActionError);
        });
    });

    openLoanFromQueryString();
});

function resetLoanForm() {
    invalidateLoanCalculation();
    const form = $('#loanForm');
    form[0].reset();
    form.removeAttr('data-id');
    clearLoanValidationErrors();
    $('#loanModalLabel').text('Nuevo prestamo');
    $('#loanSaveText').text('Guardar prestamo');
    $('[name="status"]').val('pendiente');
    $('[name="start_date"]').val(new Date().toISOString().slice(0, 10));
    setLoanCode(window.loanRoutes.nextCodeValue || 'PRE-000001');
    resetLoanPreview();
    showNeutralLoanEligibility('Seleccione un socio para evaluar.');
    $('#loanForm input, #loanForm select, #loanForm textarea').prop('disabled', false);
    $('#loanPendingSimulationsCard').addClass('d-none');
    $('#loanPendingSimulationsList').empty();
}

function loadPendingLoanSimulations(memberId) {
    if (!memberId) {
        $('#loanPendingSimulationsCard').addClass('d-none');
        return;
    }
    $.get(`${window.loanRoutes.pendingSimulationsBase}/${memberId}`, response => {
        const rows = response.simulations || [];
        $('#loanPendingSimulationsCard').toggleClass('d-none', !rows.length);
        $('#loanPendingSimulationsList').html(rows.map(simulation => `
            <div class="bg-white border rounded p-2 mb-2 d-flex justify-content-between align-items-center flex-wrap">
                <div class="small"><strong>${escapeHtml(simulation.code)}</strong> · ${escapeHtml(simulation.simulation_date)} · ${escapeHtml(simulation.amount_formatted)}<br>
                <span class="text-muted">Tasa ${escapeHtml(simulation.interest_rate)}% · ${escapeHtml(simulation.term_months)} meses · Total ${escapeHtml(simulation.total_payment_formatted)}</span></div>
                <div class="btn-group btn-group-sm mt-1"><button type="button" class="btn btn-outline-success takePendingSimulation" data-id="${simulation.id}"><i class="fas fa-check mr-1"></i> Tomar simulación</button><button type="button" class="btn btn-outline-secondary effectPendingSimulation" data-id="${simulation.id}"><i class="fas fa-times mr-1"></i> Dejar sin efecto</button></div>
            </div>`).join(''));
    }).fail(() => $('#loanPendingSimulationsCard').addClass('d-none'));
}

function leaveSimulationWithoutEffect(id, done) {
    Swal.fire({ title: 'Dejar simulación sin efecto', input: 'textarea', inputLabel: 'Motivo obligatorio', inputPlaceholder: 'Ejemplo: Simulación reemplazada por préstamo directo.', showCancelButton: true, confirmButtonText: 'Dejar sin efecto', cancelButtonText: 'Cancelar', inputValidator: value => !value?.trim() ? 'El motivo es obligatorio.' : undefined })
        .then(result => {
            if (!result.isConfirmed) return;
            $.post(`${window.loanRoutes.simulationBase}/${id}/sin-efecto`, { reason: result.value.trim() }, response => {
                $(`#loanForm [name="loan_simulation_id"] option[value="${id}"]`).remove();
                if (done) done();
                toast(response.message, 'success');
            }).fail(showActionError);
        });
}

function openLoanFromQueryString() {
    const params = new URLSearchParams(window.location.search);
    const simulationId = params.get('simulation_id');
    const loanId = params.get('loan_id');

    if (loanId) {
        setLoading(true);
        $.get(`${window.loanRoutes.base}/${loanId}`, function (loan) {
            setLoading(false);
            fillLoanDetail(loan);
            $('#loanDetailModal').modal('show');
        }).fail(function () {
            setLoading(false);
            Swal.fire('Error', 'No se encontro el prestamo solicitado.', 'error');
        });
        return;
    }

    if (!simulationId) return;

    resetLoanForm();
    fetchNextLoanCode();
    $('[name="loan_simulation_id"]').val(simulationId).trigger('change');
    $('#loanModal').modal('show');
}

function fetchNextLoanCode() {
    $.get(window.loanRoutes.nextCode, function (response) {
        setLoanCode(response.code || 'PRE-000001');
    });
}

function setLoanCode(code) {
    $('[name="loan_number"]').val(code);
    $('#loanSideCode').text(code);
}

function fillLoanForm(loan) {
    resetLoanForm();
    $('#loanForm').attr('data-id', loan.id);
    $('#loanModalLabel').text('Editar prestamo');
    $('#loanSaveText').text('Actualizar prestamo');
    setLoanCode(loan.loan_number);
    ensureHistoricalLoanMemberOption(loan);
    setValue('loan_simulation_id', loan.loan_simulation_id);
    setValue('member_id', loan.member_id);
    setValue('guarantor_member_id', loan.guarantor_member_id);
    setValue('status', loan.status);
    setValue('requested_amount', loan.requested_amount);
    setValue('approved_amount', loan.approved_amount);
    setValue('interest_rate', loan.interest_rate);
    setValue('term_months', loan.term_months);
    setValue('start_date', loan.start_date);
    setValue('first_payment_date', loan.first_payment_date);
    setValue('purpose', loan.purpose);
    setValue('observation', loan.observation);
    updateLoanPreviewFromSummary(loan);
    showLoanEligibility(loan.evaluation);
}

function calculateLoanPreview() {
    const calculationVersion = ++loanCalculationVersion;
    const amount = parseFloat($('[name="approved_amount"]').val()) || 0;
    const requested = parseFloat($('[name="requested_amount"]').val()) || 0;
    const rate = parseFloat($('[name="interest_rate"]').val());
    const months = parseInt($('[name="term_months"]').val(), 10) || 0;
    const member = $('[name="member_id"]').val();
    const start = $('[name="start_date"]').val();
    if (!member || requested <= 0 || amount <= 0 || Number.isNaN(rate) || months < 1 || !start) {
        resetLoanPreview();
        return;
    }
    $.post(window.loanRoutes.calculate, $('#loanForm').serialize(), function (response) {
        if (calculationVersion !== loanCalculationVersion || !$('#loanModal').hasClass('show')) return;
        updateLoanPreviewFromSummary(response.summary || {});
        showLoanEligibility(response.eligibility);
    }).fail(function (xhr) {
        if (calculationVersion !== loanCalculationVersion) return;
        if (xhr.status === 422 && xhr.responseJSON?.errors) showLoanValidationErrors(xhr.responseJSON.errors);
    });
}

function updateLoanPreviewFromSummary(summary) {
    $('#loanPreviewPrincipal, #loanSidePrincipal').text(summary.fixed_principal_formatted || 'S/ 0.00');
    $('#loanPreviewInterest, #loanSideInterest').text(summary.total_interest_formatted || 'S/ 0.00');
    $('#loanPreviewTotal, #loanSideTotal').text(summary.total_payment_formatted || summary.total_amount_formatted || 'S/ 0.00');
    $('#loanPreviewFirst').text(summary.first_installment_formatted || 'S/ 0.00');
    $('#loanPreviewLast').text(summary.last_installment_formatted || 'S/ 0.00');
}

function resetLoanPreview() {
    updateLoanPreviewFromSummary({});
}

function fillLoanDetail(loan) {
    $('#detailLoanCode').text(loan.loan_number || '-');
    $('#detailLoanMember').text(loan.member_name || '-');
    $('#detailLoanStatus').html(statusBadge(loan.status, loan.status_label));
    $('#detailLoanApproved').text(loan.approved_amount_formatted || 'S/ 0.00');
    $('#detailLoanPendingCapital').text(loan.pending_capital_formatted || 'S/ 0.00');
    $('#detailLoanEstimatedInterest').text(loan.estimated_future_interest_formatted || 'S/ 0.00');
    $('#detailLoanBalance').text(loan.current_balance_formatted || 'S/ 0.00');
    $('#detailLoanInterest').text(loan.total_interest_formatted || 'S/ 0.00');
    $('#detailLoanDni').text(loan.member_dni || '-');
    $('#detailLoanRate').text(loan.interest_rate_formatted || '-');
    $('#detailLoanTerm').text(loan.term_months_formatted || '-');
    $('#detailLoanFirstDate').text(loan.first_payment_date_formatted || '-');
    $('#detailLoanApprovedAt').text(loan.approved_at || '-');
    $('#detailLoanDisbursedAt').text(loan.disbursed_at || '-');
    $('#detailLoanCreatedBy').text(loan.created_by_name || '-');
    $('#detailLoanApprovedBy').text(loan.approved_by_name || '-');
    $('#detailLoanDisbursedBy').text(loan.disbursed_by_name || '-');
    $('#detailLoanTotal').text(loan.total_amount_formatted || '-');
    $('#detailLoanPurpose').text(loan.purpose || '-');
    $('#detailLoanObservation').text(loan.observation || '-');
    const evaluation = loan.evaluation || {};
    $('#detailLoanMemberType').text(evaluation.member_type === 'antiguo' ? 'Antiguo' : 'Nuevo');
    $('#detailLoanContributionCount').text(evaluation.contribution_count ?? '-');
    $('#detailLoanContributions').text(`S/ ${Number(evaluation.total_contributions || 0).toFixed(2)}`);
    $('#detailLoanLimit').text(`S/ ${Number(evaluation.loan_limit_without_guarantor || 0).toFixed(2)}`);
    $('#detailLoanRequiresGuarantor').text(evaluation.requires_guarantor ? 'Si' : 'No');
    $('#detailLoanGuarantorReason').text((evaluation.guarantor_requirement_reason || '-').replaceAll('_', ' '));
    $('#detailLoanGuarantor').text(loan.guarantor_member_name ? `${loan.guarantor_member_name} - ${loan.guarantor_member_dni || '-'}` : '-');
    $('#detailLoanGuarantorContributions').text(`S/ ${Number(evaluation.guarantor_total_contributions || 0).toFixed(2)}`);
    fillLoanFinancialSummary(loan.financial_summary || {});
    fillLoanRelatedPayments(loan.related_payments || []);
    fillLoanDisbursementDetail(loan);
}

function fillLoanFinancialSummary(summary) {
    $('#detailLoanProjectedTotal').text(summary.projected_total_formatted || 'S/ 0.00');
    $('#detailLoanRealPaid').text(summary.total_paid_formatted || 'S/ 0.00');
    $('#detailLoanCapitalPaid').text(summary.capital_paid_formatted || 'S/ 0.00');
    $('#detailLoanInterestPaid').text(summary.interest_paid_formatted || 'S/ 0.00');
    $('#detailLoanAdvanceExonerated').text(summary.advance_interest_exonerated_formatted || 'S/ 0.00');
    $('#detailLoanLiquidationExonerated').text(summary.liquidation_interest_not_collected_formatted || 'S/ 0.00');
    $('#detailLoanFinalBalance').text(summary.final_balance_formatted || 'S/ 0.00');
    $('#detailLoanFinalStatus').text(summary.final_status || '-');
    $('#detailLoanLiquidatedBy').text(summary.liquidated_by || '-');
    $('#detailLoanLiquidatedAt').text(summary.liquidated_at || '-');
    $('#detailLoanLiquidationCreatedAt').text(summary.liquidation_created_at || '-');
}

function fillLoanRelatedPayments(payments) {
    const rows = payments.map((payment) => {
        const receipt = payment.receipt_url ? `<a href="${escapeHtml(payment.receipt_url)}" target="_blank" class="btn btn-light border btn-sm" title="Ver recibo"><i class="fas fa-receipt"></i></a>` : '';
        const voucher = payment.voucher_url ? `<a href="${escapeHtml(payment.voucher_url)}" target="_blank" class="btn btn-light border btn-sm" title="Ver comprobante"><i class="fas fa-paperclip"></i></a>` : '';
        return `<tr><td><strong>${escapeHtml(payment.payment_number)}</strong></td><td>${escapeHtml(payment.payment_date_formatted)}</td><td>${escapeHtml(payment.payment_type_label)}</td><td class="text-right font-weight-bold">${escapeHtml(payment.amount_formatted)}</td><td>${escapeHtml(payment.created_by_name)}<small class="d-block text-muted">${escapeHtml(payment.created_at_formatted)}</small></td><td class="text-right"><a href="${escapeHtml(payment.show_url)}" class="btn btn-light border btn-sm" title="Ver cobro"><i class="fas fa-eye"></i></a> ${receipt} ${voucher}</td></tr>`;
    }).join('');
    $('#detailLoanPaymentsRows').html(rows || '<tr><td colspan="6" class="text-center text-muted">Sin cobros registrados.</td></tr>');
}

function showLoanEligibility(evaluation) {
    if (!evaluation) return showNeutralLoanEligibility();
    const withdrawalBlocked = Boolean(evaluation.withdrawal_blocked) || evaluation.can_request_loan === false;
    const contributionEligible = evaluation.eligible_by_contribution_count !== false;
    const enabled = !withdrawalBlocked && contributionEligible;
    setLoanWithdrawalBlocked(withdrawalBlocked);
    if (withdrawalBlocked) {
        $('#loanEligibility').removeClass('is-neutral is-success is-warning').addClass('is-danger').html(
            `<div class="loan-eligibility-heading"><div><i class="fas fa-ban"></i><strong>No habilitado para préstamo</strong></div></div>` +
            `<p class="mb-3">${escapeHtml(evaluation.withdrawal_message || 'Socio no habilitado para préstamo. Tiene un proceso de retiro/cierre pendiente o confirmado.')}</p>` +
            `<div class="loan-eligibility-grid"><div><span>Estado</span><strong>No habilitado</strong></div><div><span>Motivo</span><strong>${escapeHtml(evaluation.withdrawal_reason || 'Retiro pendiente o confirmado')}</strong></div><div><span>Saldo en contra</span><strong>${escapeHtml(evaluation.withdrawal_balance_against_formatted || 'S/ 0.00')}</strong></div><div class="span-2"><span>Acción sugerida</span><strong>${escapeHtml(evaluation.withdrawal_action || 'Regularizar deuda o anular el cierre si corresponde.')}</strong></div></div>`
        );
        return;
    }
    if (evaluation.guarantor_eligible === false) {
        $('#loanSaveButton').prop('disabled', true).attr('title', 'El garante tiene un proceso de retiro/cierre.');
        $('#loanEligibility').removeClass('is-neutral is-success is-warning').addClass('is-danger').html(
            `<div class="loan-eligibility-heading"><div><i class="fas fa-ban"></i><strong>Garante no habilitado</strong></div></div><p class="mb-0">El garante seleccionado tiene un proceso de retiro/cierre pendiente o confirmado. Seleccione otro garante vigente.</p>`
        );
        return;
    }
    const reason = evaluation.guarantor_requirement_reason ? String(evaluation.guarantor_requirement_reason).replaceAll('_', ' ') : '';
    $('#loanEligibility').removeClass('is-neutral is-success is-warning is-danger').addClass(enabled ? (evaluation.requires_guarantor ? 'is-warning' : 'is-success') : 'is-danger').html(
        `<div class="loan-eligibility-heading"><div><i class="fas ${enabled ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><strong>${enabled ? 'Habilitado para prestamo' : 'No habilitado para prestamo'}</strong></div></div>` +
        `<div class="loan-eligibility-grid"><div><span>Tipo de socio</span><strong>${evaluation.member_type === 'antiguo' ? 'Antiguo' : 'Nuevo'}</strong></div><div><span>Aportes registrados</span><strong>${Number(evaluation.contribution_count || 0)}</strong></div><div><span>Total aportado</span><strong>S/ ${Number(evaluation.total_contributions || 0).toFixed(2)}</strong></div><div><span>Limite sin garante</span><strong>S/ ${Number(evaluation.loan_limit_without_guarantor || 0).toFixed(2)}</strong></div><div><span>Requiere garante</span><strong>${evaluation.requires_guarantor ? 'Si' : 'No'}</strong></div><div><span>Historial crediticio</span><strong class="credit-risk-${escapeHtml(evaluation.credit_history?.color || 'verde')}">${escapeHtml(evaluation.credit_history?.label || 'Excelente')} · ${Number(evaluation.credit_history?.score ?? 100)}/100</strong></div><div><span>Atrasos leves / graves</span><strong>${Number(evaluation.credit_history?.mild_late || 0)} / ${Number(evaluation.credit_history?.serious_late || 0)}</strong></div><div><span>Deuda vencida activa</span><strong>${escapeHtml(evaluation.credit_history?.active_overdue_amount_formatted || 'S/ 0.00')}</strong></div><div class="span-2"><span>Recomendación crediticia</span><strong>${escapeHtml(evaluation.credit_history?.recommendation || 'Evaluar según las reglas generales.')}</strong></div></div>` +
        (reason ? `<p class="loan-eligibility-reason"><strong>Motivo:</strong> ${escapeHtml(reason)}</p>` : '')
    );
}

function showNeutralLoanEligibility(message = 'Seleccione un socio y complete los datos para calcular la evaluacion.') {
    setLoanWithdrawalBlocked(false);
    $('#loanEligibility').removeClass('is-success is-warning is-danger').addClass('is-neutral').html(`<div class="loan-eligibility-empty"><i class="fas fa-user-check"></i><span>${escapeHtml(message)}</span></div>`);
}

function setLoanWithdrawalBlocked(blocked) {
    $('#loanSaveButton').prop('disabled', blocked).attr('title', blocked ? 'Bloqueado por proceso de retiro/cierre.' : '');
    $('#loanForm [name="loan_simulation_id"], #loanForm [name="guarantor_member_id"], #loanForm [name="requested_amount"], #loanForm [name="approved_amount"], #loanForm [name="interest_rate"], #loanForm [name="term_months"], #loanForm [name="start_date"], #loanForm [name="first_payment_date"], #loanForm [name="purpose"], #loanForm [name="observation"]').prop('disabled', blocked);
}

function ensureHistoricalLoanMemberOption(loan) {
    const select = $('#loanForm [name="member_id"]');
    if (!loan.member_id || select.find(`option[value="${loan.member_id}"]`).length) return;
    const suffix = loan.evaluation?.withdrawal_blocked ? ' - No habilitado: retiro/cierre' : '';
    select.append(new Option(`${loan.member_code || 'SOCIO'} - ${loan.member_dni || '-'} - ${loan.member_name || '-'}${suffix}`, loan.member_id));
}

function invalidateLoanCalculation() { loanCalculationVersion += 1; }
const debouncedLoanCalculation = debounce(calculateLoanPreview, 450);

function fillLoanSchedule(loan, installments) {
    $('#scheduleLoanCode').text(loan.loan_number || '-');
    $('#scheduleLoanMember').text(loan.member_name || '-');
    $('#scheduleLoanStatus').html(statusBadge(loan.status, loan.status_label));
    updateDocumentLink('#scheduleLoanPrintLink', loan.schedule_print_url, 'Imprimir');
    updateDocumentLink('#scheduleLoanPdfLink', loan.schedule_pdf_url, 'PDF');
    const rows = installments.map((row) => `
        <tr class="loan-installment-${escapeHtml(row.visual_status)}"><td>${escapeHtml(row.installment_number)}</td><td>${escapeHtml(row.due_date_formatted)}</td><td>${escapeHtml(row.opening_balance_formatted)}</td><td>${escapeHtml(row.principal_amount_formatted)}</td><td>${escapeHtml(row.interest_amount_formatted)}</td><td><strong>${escapeHtml(row.interest_exonerated_formatted)}</strong></td><td>${escapeHtml(row.installment_amount_formatted)}</td><td>${escapeHtml(row.paid_amount_formatted)}</td><td>${escapeHtml(row.remaining_amount_formatted)}</td><td>${escapeHtml(row.closing_balance_formatted)}</td><td>${escapeHtml(row.payment_date_formatted)}</td><td>${escapeHtml(row.late_days)}</td><td>${escapeHtml(row.late_fee_paid_formatted)}</td><td><strong>${escapeHtml(row.late_fee_pending_formatted)}</strong></td><td><span class="credit-risk-${escapeHtml(row.credit_color)}">${escapeHtml(row.credit_status_label)}</span></td></tr>
    `).join('');
    $('#loanScheduleRows').html(rows || '<tr><td colspan="15">Sin cuotas registradas.</td></tr>');
}

function fillLoanDisbursementDetail(loan) {
    const isDisbursed = loan.status === 'desembolsado' || loan.disbursed_at;
    $('#detailLoanDisbursementEmpty').toggleClass('d-none', Boolean(isDisbursed));
    $('#detailLoanDisbursementInfo').toggleClass('d-none', !isDisbursed);

    if (!isDisbursed) return;

    $('#detailLoanDisbursementDate').text(loan.disbursed_at || '-');
    $('#detailLoanDisbursementUser').text(loan.disbursed_by_name || '-');
    $('#detailLoanDisbursementMethod').text(loan.disbursement_payment_method_label || '-');
    $('#detailLoanDisbursementReference').text(loan.disbursement_reference || '-');
    updateDocumentLink('#detailLoanVoucherLink', loan.disbursement_voucher_view_url, 'Ver comprobante', 'Sin comprobante');
    updateDocumentLink('#detailLoanVoucherDownload', loan.disbursement_voucher_url, 'Descargar', 'Descargar');
    renderStoredDisbursementVoucher(loan);
    updateDocumentLink('#detailLoanReceiptLink', loan.disbursement_receipt_url, loan.disbursement_receipt_number ? `Ver recibo ${loan.disbursement_receipt_number}` : 'Ver recibo', 'Sin recibo');
}

function updateDocumentLink(selector, url, text, emptyText) {
    const link = $(selector);
    if (url) {
        link.attr('href', url).removeClass('disabled').contents().filter(function () { return this.nodeType === 3; }).remove();
        link.append(` ${escapeHtml(text)}`);
        return;
    }

    link.attr('href', '#').addClass('disabled').contents().filter(function () { return this.nodeType === 3; }).remove();
    link.append(` ${escapeHtml(emptyText || text)}`);
}

function updateDisbursementRequirements() {
    const method = $('#loanDisburseForm [name="payment_method"]').val();
    const requiresReference = ['yape', 'plin', 'transferencia', 'cheque', 'otro'].includes(method);
    const reference = $('#loanDisburseForm [name="reference"]');
    $('#loanDisbursementReferenceGroup').toggleClass('d-none', !requiresReference);
    reference.prop('required', requiresReference);
    if (!requiresReference) reference.val('');
    $('#loanDisbursementVoucherHelp').text(requiresReference
        ? 'JPG, PNG, WEBP o PDF. Tamano maximo: 4 MB.'
        : 'Comprobante opcional. JPG, PNG, WEBP o PDF. Tamano maximo: 4 MB.');
}

function resetDisbursementVoucher() {
    if (disbursementPreviewUrl) URL.revokeObjectURL(disbursementPreviewUrl);
    disbursementPreviewUrl = null;
    $('#loanDisbursementVoucher').val('');
    $('#loanDisbursementVoucherName').text('Seleccionar comprobante');
    $('#loanDisbursementVoucherPreview').addClass('is-empty').html('<div class="loan-voucher-placeholder"><i class="far fa-file-alt"></i><strong>No se ha seleccionado comprobante</strong><span>La vista previa aparecera aqui.</span></div>');
}

function renderDisbursementVoucher(file) {
    if (!file) return resetDisbursementVoucher();
    if (disbursementPreviewUrl) URL.revokeObjectURL(disbursementPreviewUrl);
    disbursementPreviewUrl = URL.createObjectURL(file);
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    const size = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
    const visual = isPdf ? '<i class="fas fa-file-pdf loan-voucher-pdf-icon"></i>' : `<img src="${disbursementPreviewUrl}" alt="Vista previa del comprobante">`;
    $('#loanDisbursementVoucherName').text(file.name);
    $('#loanDisbursementVoucherPreview').removeClass('is-empty').html(`${visual}<div class="loan-voucher-meta"><strong>${escapeHtml(file.name)}</strong><span>${size}</span><div><a class="btn btn-light border btn-sm" href="${disbursementPreviewUrl}" target="_blank" rel="noopener"><i class="fas fa-eye mr-1"></i> Ver</a><label class="btn btn-light border btn-sm mb-0" for="loanDisbursementVoucher"><i class="fas fa-sync-alt mr-1"></i> Cambiar</label><button type="button" class="btn btn-outline-danger btn-sm" id="removeLoanDisbursementVoucher"><i class="fas fa-trash-alt mr-1"></i> Quitar</button></div></div>`);
}

function renderStoredDisbursementVoucher(loan) {
    const box = $('#detailLoanVoucherPreview');
    if (!loan.disbursement_voucher_view_url) return box.html('<div class="loan-voucher-placeholder"><i class="far fa-file-alt"></i><strong>No se registro comprobante de desembolso.</strong></div>');
    if (loan.disbursement_voucher_type === 'image') return box.html(`<img src="${escapeHtml(loan.disbursement_voucher_view_url)}" alt="Comprobante de desembolso"><strong>Comprobante de desembolso</strong>`);
    box.html(`<i class="fas fa-file-pdf loan-voucher-pdf-icon"></i><div><strong>${escapeHtml(loan.disbursement_voucher_name || 'Comprobante PDF')}</strong><span>Documento PDF</span></div>`);
}

function loadLoanSummary() {
    $.get(window.loanRoutes.summary, function (summary) {
        $('#loanSummaryApproved').text(`S/ ${summary.total_approved || '0.00'}`);
        $('#loanSummaryPending').text(summary.pending || '0');
        $('#loanSummaryDisbursed').text(summary.disbursed || '0');
        $('#loanSummaryReceivable').text(`S/ ${summary.receivable || '0.00'}`);
    });
}

function afterLoanAction(response) {
    tableLoan.ajax.reload(null, false);
    loadLoanSummary();
    toast(response.message, 'success');
}

function handleLoanAjaxError(xhr) {
    setLoading(false);
    if (xhr.status === 422 && xhr.responseJSON?.errors) {
        showLoanValidationErrors(xhr.responseJSON.errors);
        return;
    }
    showActionError(xhr);
}

function showActionError(xhr) {
    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo completar la operacion.', 'error');
}

function showLoanValidationErrors(errors) {
    clearLoanValidationErrors();
    let list = '<ul class="mb-0">';
    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`[name="${key}"]`);
        input.addClass('is-invalid');
        placeFieldError(input, messages[0]);
    });
    list += '</ul>';
    $('#loan-error-messages').removeClass('d-none').html(list);
}

function clearLoanValidationErrors() {
    $('#loan-error-messages').addClass('d-none').empty();
    $('#loanForm .is-invalid').removeClass('is-invalid');
    $('#loanForm .invalid-feedback').remove();
}

function showLoanDisburseValidationErrors(errors) {
    clearLoanDisburseValidationErrors();
    let list = '<ul class="mb-0">';
    $.each(errors, function (key, messages) {
        list += `<li>${messages[0]}</li>`;
        const input = $(`#loanDisburseForm [name="${key}"]`);
        input.addClass('is-invalid');
        placeFieldError(input, messages[0]);
    });
    list += '</ul>';
    $('#loan-disburse-error-messages').removeClass('d-none').html(list);
}

function clearLoanDisburseValidationErrors() {
    $('#loan-disburse-error-messages').addClass('d-none').empty();
    $('#loanDisburseForm .is-invalid').removeClass('is-invalid');
    $('#loanDisburseForm .invalid-feedback').remove();
}

function placeFieldError(input, message) {
    const feedback = `<div class="invalid-feedback d-block">${message}</div>`;
    if (input.closest('.input-group').length) {
        input.closest('.input-group').after(feedback);
        return;
    }
    input.after(feedback);
}

function statusBadge(status, label) {
    const cls = status === 'anulado' ? 'danger' : (status === 'pendiente' ? 'warning' : (status === 'aprobado' ? 'info' : 'success'));
    return `<span class="badge badge-${cls}">${escapeHtml(label || status || '-')}</span>`;
}

function setValue(name, value) { $(`[name="${name}"]`).val(value || ''); }
function setLoading(show) { if (divLoading) divLoading.style.display = show ? 'flex' : 'none'; }
function toast(message, icon) { Swal.fire({ title: message, icon, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
function debounce(callback, wait) { let timeout; return function () { clearTimeout(timeout); timeout = setTimeout(callback, wait); }; }
function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
