<div class="modal fade" id="closureCloseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="closureCloseForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Confirmar retiro / Cerrar cuenta</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="closure-close-error-messages" class="alert alert-danger d-none"></div>
                <input type="hidden" id="closureCloseId">
                <div class="alert alert-light border" id="closureCloseMessage">Cuenta sin saldo pendiente.</div>
                <div id="closureClosePaymentFields">
                    <div class="form-group"><label>Método de pago</label><select name="payment_method" class="form-control"><option value="">Seleccione</option>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="form-group" id="closureCloseReferenceGroup"><label>Referencia <span id="closureReferenceRequired" class="text-danger d-none">*</span></label><input type="text" name="payment_reference" class="form-control" maxlength="100"></div>
                    <div class="form-group" id="closureCloseVoucherGroup"><label>Comprobante</label><input type="file" name="voucher_path" id="closureVoucher" class="form-control-file" accept=".jpg,.jpeg,.png,.webp,.pdf"><small class="text-muted d-block" id="closureVoucherName">JPG, PNG, WEBP o PDF - max. 4 MB</small><div id="closureVoucherPreview" class="mt-2 d-none"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" id="closureCloseCancel" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark" id="closureCloseSubmit"><i class="fas fa-check-circle"></i> Confirmar retiro / Cerrar cuenta</button>
            </div>
        </form>
    </div>
</div>
