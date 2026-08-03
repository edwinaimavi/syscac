<div class="modal fade" id="loanPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document"><div class="modal-content loan-payment-modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-cash-register"></i></div><div><h5 class="modal-title mb-0" id="loanPaymentModalLabel">Nuevo cobro</h5><small>Registra pagos de prestamos y genera ingreso en Caja</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <form id="loanPaymentForm" enctype="multipart/form-data">@csrf
            <div class="modal-body member-modal-body">
                <div id="loan-payment-error-messages" class="alert alert-danger d-none"></div>
                <section class="member-detail-card">
                    <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="historicalPayment" name="is_historical" value="1"><label class="custom-control-label font-weight-bold" for="historicalPayment">Registro histórico / migración</label></div>
                    <div id="historicalPaymentFields" class="d-none mt-3">
                        <div class="alert alert-info"><i class="fas fa-history mr-1"></i> Este cobro se registrará como histórico. Use la fecha real en que el socio pagó. Puede marcar si afecta o no la Caja actual.</div>
                        <div class="form-row">
                            <div class="form-group col-md-3"><label>Fecha de registro</label><input type="text" class="form-control form-control-sm" value="{{ now()->format('d/m/Y H:i') }}" readonly></div>
                            <div class="form-group col-md-3"><label>Fecha de corte</label><input type="text" class="form-control form-control-sm" value="{{ \Illuminate\Support\Carbon::parse($systemCutoffDate)->format('d/m/Y') }}" readonly></div>
                            <div class="form-group col-md-2"><label>Mora calculada</label><input type="text" class="form-control form-control-sm" id="historicalLateFeeCalculated" value="S/ 0.00" readonly></div>
                            <div class="form-group col-md-3"><label>Mora cobrada históricamente</label><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="late_fee_charged" value="0"></div>
                            <div class="form-group col-md-3"><label>Mora exonerada históricamente</label><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="late_fee_exonerated" value="0"></div>
                            <div class="form-group col-12"><label>Motivo de exoneración / ajuste</label><textarea class="form-control form-control-sm" name="late_fee_override_reason" maxlength="1000" placeholder="Ej.: Migración desde kardex histórico"></textarea></div>
                        </div>
                        <div class="form-row">
                            <div class="col-md-4 custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="historicalAffectsCash" name="affects_cash" value="1"><label class="custom-control-label" for="historicalAffectsCash">Afecta Caja actual</label></div>
                            <div class="col-md-4 custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="historicalAffectsProfit" name="affects_profit" value="1" checked><label class="custom-control-label" for="historicalAffectsProfit">Afecta utilidades</label></div>
                            <div class="col-md-4 custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="historicalAffectsCredit" name="affects_credit_history" value="1" checked><label class="custom-control-label" for="historicalAffectsCredit">Afecta historial crediticio</label></div>
                        </div>
                        <div class="form-row mt-3"><div class="form-group col-md-6"><label>Tratamiento de utilidad histórica</label><select class="form-control form-control-sm" name="profit_treatment"><option value="eligible">Incluir en cálculo del período real</option><option value="historical_closed">Período cerrado históricamente</option><option value="externally_distributed">Ya distribuido fuera del sistema</option></select><small class="text-muted">Los períodos cerrados quedan visibles como historial, sin generar una distribución pendiente duplicada.</small></div></div>
                    </div>
                </section>
                <section class="member-detail-card"><h6><i class="fas fa-info-circle"></i> Informacion del cobro</h6>
                    <div class="form-row">
                        <div class="form-group col-md-3"><label>Codigo</label><input type="text" class="form-control form-control-sm" name="payment_number" readonly></div>
                        <div class="form-group col-md-3"><label><span id="paymentDateLabel">Fecha pago</span> <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="payment_date" required></div>
                        <div class="form-group col-md-3"><label>Socio <span class="text-danger">*</span></label><select class="form-control form-control-sm" id="paymentMemberId" name="member_id"><option value="">Seleccione</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
                        <div class="form-group col-md-3"><label>Prestamo <span class="text-danger">*</span></label><select class="form-control form-control-sm" id="paymentLoanId" name="loan_id" required><option value="">Seleccione socio</option></select></div>
                    </div>
                    <div class="cash-detail-summary"><div><span>Prestamo</span><strong id="paymentLoanCode">-</strong></div><div><span>Saldo actual</span><strong id="paymentLoanBalance">S/ 0.00</strong></div><div><span>Total pagado</span><strong id="paymentLoanPaid">S/ 0.00</strong></div><div><span>Estado</span><strong id="paymentLoanStatus">-</strong></div></div>
                </section>

                <section class="member-detail-card"><h6><i class="fas fa-list-check"></i> Tipo y cuotas</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Tipo de cobro <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="payment_type" required><option value="cuota">Pago normal de cuota</option><option value="parcial">Pago parcial</option><option value="adelanto_cuotas">Adelanto de cuotas futuras</option><option value="abono_capital">Amortizacion a capital</option><option value="liquidacion">Liquidacion total</option></select></div>
                        <div class="form-group col-md-3"><label>Monto pagado <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" required></div>
                    </div>
                    <div id="paymentInstallmentWrap" class="table-responsive loan-payment-installments"><table class="table table-sm table-hover text-center mb-0"><thead><tr><th></th><th>Cuota</th><th>Vencimiento</th><th>Capital</th><th>Interés</th><th>Pendiente cuota</th><th>Días mora</th><th>Mora</th><th>Total a cobrar</th><th>Estado</th></tr></thead><tbody id="paymentInstallmentRows"><tr><td colspan="10">Seleccione un préstamo.</td></tr></tbody></table></div>
                    <div id="lateFeeWaiverBox" class="card card-outline card-warning mt-2 d-none"><div class="card-body py-2"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="waiveLateFee" name="waive_late_fee" value="1"><label class="custom-control-label" for="waiveLateFee">Exonerar mora</label></div><div id="lateFeeReasonBox" class="mt-2 d-none"><label>Motivo obligatorio</label><textarea name="late_fee_reason" class="form-control" maxlength="500"></textarea></div></div></div>
                </section>

                <section class="loan-payment-preview member-detail-card"><h6><i class="fas fa-calculator"></i> Resumen antes de confirmar</h6><div class="cash-detail-summary"><div><span>Capital a pagar</span><strong id="paymentPreviewCapital">S/ 0.00</strong></div><div><span>Interés a pagar</span><strong id="paymentPreviewInterest">S/ 0.00</strong></div><div class="border-warning"><span class="text-warning">Mora a pagar</span><strong id="paymentPreviewLateFee" class="text-warning">S/ 0.00</strong></div><div><span>Mora exonerada</span><strong id="paymentPreviewLateFeeWaived">S/ 0.00</strong></div><div><span>Interés futuro exonerado</span><strong id="paymentPreviewExonerated">S/ 0.00</strong></div><div class="bg-light"><span>Total a pagar</span><strong id="paymentPreviewTotal" class="text-primary h5 mb-0">S/ 0.00</strong></div><div><span>Nuevo saldo estimado</span><strong id="paymentPreviewBalance">S/ 0.00</strong></div><div><span>Cuotas adelantadas</span><strong id="paymentPreviewCount">0</strong></div></div><div id="paymentAdvanceWarning" class="alert alert-warning mt-3 mb-0 d-none"><i class="fas fa-exclamation-triangle mr-1"></i> Primero debe regularizar las cuotas vencidas antes de adelantar cuotas futuras.</div></section>
                <div id="paymentCreditWarning" class="alert alert-info d-none"><i class="fas fa-info-circle mr-1"></i> <strong id="paymentCreditWarningTitle"></strong><span id="paymentCreditWarningText" class="d-block small mt-1"></span></div>

                <section class="member-detail-card mb-0"><h6><i class="fas fa-receipt"></i> Pago y comprobante</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Metodo pago <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="payment_method" required><option value="">Seleccione</option><option value="efectivo">Efectivo</option><option value="yape">Yape</option><option value="plin">Plin</option><option value="transferencia">Transferencia</option><option value="cheque">Cheque</option><option value="otro">Otro</option></select></div>
                        <div class="form-group col-md-4 d-none" id="paymentReferenceGroup"><label>Referencia <span id="paymentReferenceRequired" class="text-danger d-none">*</span></label><input type="text" class="form-control form-control-sm" name="payment_reference" maxlength="100" placeholder="Numero de operacion o cheque"></div>
                        <div class="form-group col-12"><label>Comprobante</label><div class="loan-voucher-layout"><label class="loan-voucher-dropzone" for="paymentVoucher"><i class="fas fa-cloud-upload-alt"></i><strong id="paymentVoucherName">Seleccionar comprobante</strong><span>JPG, PNG, WEBP o PDF. Maximo 4 MB.</span></label><input type="file" class="d-none" id="paymentVoucher" name="voucher_path" accept="image/jpeg,image/png,image/webp,application/pdf"><div class="loan-voucher-preview is-empty" id="paymentVoucherPreview"><div class="loan-voucher-placeholder"><i class="far fa-file-alt"></i><strong>Sin comprobante seleccionado</strong><span>La vista previa aparecera aqui.</span></div></div></div></div>
                        <div class="form-group col-12"><label>Observacion</label><textarea class="form-control form-control-sm" name="observation" rows="2"></textarea></div>
                    </div>
                </section>
            </div>
            <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <span id="loanPaymentSaveText">Guardar cobro</span></button></div>
        </form>
    </div></div>
</div>
