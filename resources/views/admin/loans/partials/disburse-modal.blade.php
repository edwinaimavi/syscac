<div class="modal fade" id="loanDisburseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content loan-disburse-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-money-bill-wave"></i></div><div><h5 class="modal-title mb-0">Desembolsar prestamo</h5><small>Genera egreso en Caja</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <form id="loanDisburseForm" enctype="multipart/form-data">@csrf
            <div class="modal-body member-modal-body">
                <div id="loan-disburse-error-messages" class="alert alert-danger d-none"></div>
                <section class="loan-disburse-summary"><div class="cash-detail-summary"><div><span>Prestamo</span><strong id="disburseLoanCode">-</strong></div><div><span>Socio</span><strong id="disburseLoanMember">-</strong></div><div><span>Monto</span><strong id="disburseLoanAmount">S/ 0.00</strong></div><div><span>Saldo Caja</span><strong id="disburseCashBalance">S/ 0.00</strong></div></div></section>
                <section class="member-section"><div class="member-section-header"><div><h6><i class="fas fa-credit-card"></i> Datos del desembolso</h6><p>Indique la fecha y el medio por el que se entrega el prestamo.</p></div></div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Fecha desembolso <span class="text-danger">*</span></label><input type="date" class="form-control" name="disbursed_at" required></div>
                        <div class="form-group col-md-4"><label>Metodo de pago <span class="text-danger">*</span></label><select class="form-control" name="payment_method" required><option value="">Seleccione</option><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="yape">Yape</option><option value="plin">Plin</option><option value="cheque">Cheque</option><option value="otro">Otro</option></select></div>
                        <div class="form-group col-md-4 d-none" id="loanDisbursementReferenceGroup"><label>Referencia <span id="loanDisbursementReferenceRequired" class="text-danger">*</span></label><input type="text" class="form-control" name="reference" maxlength="100" placeholder="Numero de operacion o cheque"></div>
                    </div>
                </section>
                <section class="member-section mb-0"><div class="member-section-header"><div><h6><i class="fas fa-paperclip"></i> Comprobante</h6><p id="loanDisbursementVoucherHelp">JPG, PNG, WEBP o PDF. Tamano maximo: 4 MB.</p></div></div>
                    <div class="loan-voucher-layout">
                        <label class="loan-voucher-dropzone" for="loanDisbursementVoucher"><i class="fas fa-cloud-upload-alt"></i><strong id="loanDisbursementVoucherName">Seleccionar comprobante</strong><span>Haz clic para elegir o reemplazar el archivo</span></label>
                        <input type="file" class="d-none" id="loanDisbursementVoucher" name="voucher_path" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <div class="loan-voucher-preview is-empty" id="loanDisbursementVoucherPreview"><div class="loan-voucher-placeholder"><i class="far fa-file-alt"></i><strong>No se ha seleccionado comprobante</strong><span>La vista previa aparecera aqui.</span></div></div>
                    </div>
                </section>
            </div>
            <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Confirmar desembolso</button></div>
        </form>
    </div></div>
</div>
