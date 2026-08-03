<div class="modal fade" id="refinancingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-sync-alt"></i></div><div><h5 class="modal-title mb-0" id="refModalLabel">Nuevo refinanciamiento</h5><small>Reordena deuda sin movimiento de Caja</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <form id="refinancingForm">@csrf
            <div class="modal-body member-modal-body">
                <div id="ref-error-messages" class="alert alert-danger d-none"></div>
                <section class="member-detail-card"><h6><i class="fas fa-info-circle"></i> Informacion</h6><div class="form-row">
                    <div class="form-group col-md-3"><label>Codigo</label><input type="text" class="form-control form-control-sm" name="code" readonly></div>
                    <div class="form-group col-md-3"><label>Fecha <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="refinancing_date" required></div>
                    <div class="form-group col-md-3"><label>Socio <span class="text-danger">*</span></label><select class="form-control form-control-sm" id="refMemberId"><option value="">Seleccione</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
                    <div class="form-group col-md-3"><label>Prestamo a refinanciar <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="original_loan_id" id="refLoanId" required><option value="">Seleccione socio</option></select></div>
                </div>
                <div id="refOverdueAlert" class="alert alert-warning py-2 d-none">Este prestamo tiene cuotas vencidas y puede ser refinanciado.</div>
                <div class="cash-detail-summary">
                    <div><span>Prestamo</span><strong id="refLoanCode">-</strong></div>
                    <div><span>Monto aprobado</span><strong id="refLoanApproved">S/ 0.00</strong></div>
                    <div><span>Saldo pendiente actual</span><strong id="refLoanBalance">S/ 0.00</strong></div>
                    <div><span>Total pagado</span><strong id="refLoanPaidTotal">S/ 0.00</strong></div>
                    <div><span>Total pendiente</span><strong id="refLoanPendingTotal">S/ 0.00</strong></div>
                    <div><span>Cuotas pagadas</span><strong id="refLoanPaidInstallments">0</strong></div>
                    <div><span>Cuotas pendientes</span><strong id="refLoanPendingInstallments">0</strong></div>
                    <div><span>Cuotas vencidas</span><strong id="refLoanOverdueInstallments">0</strong></div>
                    <div><span>Vencida mas antigua</span><strong id="refLoanOldestOverdue">-</strong></div>
                    <div><span>Estado</span><strong id="refLoanStatus">-</strong></div>
                </div></section>
                <section class="member-detail-card"><h6><i class="fas fa-balance-scale"></i> Saldo a refinanciar</h6><div class="form-row">
                    <div class="form-group col-md-4"><label>Saldo pendiente</label><input type="text" class="form-control form-control-sm" id="refPreviousBalanceText" readonly></div>
                    <div class="form-group col-md-4"><label>Monto adicional</label><input type="number" class="form-control form-control-sm" name="additional_amount" step="0.01" min="0" value="0"></div>
                    <div class="form-group col-md-4"><label>Nuevo monto refinanciado</label><input type="text" class="form-control form-control-sm" id="refNewAmountText" readonly></div>
                </div><small class="text-muted">El monto adicional queda registrado, pero esta version no mueve Caja automaticamente.</small></section>
                <section class="member-detail-card"><h6><i class="fas fa-calendar-alt"></i> Nuevas condiciones</h6><div class="form-row">
                    <div class="form-group col-md-3"><label>Tasa mensual <span class="text-danger">*</span></label><input type="number" class="form-control form-control-sm" name="interest_rate" step="0.01" min="0" required></div>
                    <div class="form-group col-md-3"><label>Plazo meses <span class="text-danger">*</span></label><input type="number" class="form-control form-control-sm" name="term_months" min="1" required></div>
                    <div class="form-group col-md-3"><label>Fecha inicio <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="start_date" required></div>
                    <div class="form-group col-md-3"><label>Primera cuota</label><input type="date" class="form-control form-control-sm" name="first_payment_date"></div>
                </div></section>
                <section class="member-detail-card"><h6><i class="fas fa-calculator"></i> Resumen calculado</h6><div class="cash-detail-summary"><div><span>Capital fijo</span><strong id="refFixedPrincipal">S/ 0.00</strong></div><div><span>Total interes</span><strong id="refTotalInterest">S/ 0.00</strong></div><div><span>Total a pagar</span><strong id="refTotalPayment">S/ 0.00</strong></div><div><span>Primera / ultima cuota</span><strong id="refFirstLast">S/ 0.00 / S/ 0.00</strong></div></div></section>
                <section class="member-detail-card mb-0"><h6><i class="fas fa-clipboard-list"></i> Motivo</h6><div class="form-row"><div class="form-group col-md-6"><label>Motivo <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" name="reason" rows="2" required></textarea></div><div class="form-group col-md-6"><label>Observacion</label><textarea class="form-control form-control-sm" name="observation" rows="2"></textarea></div></div></section>
            </div>
            <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Registrar refinanciamiento</button></div>
        </form>
    </div></div>
</div>
