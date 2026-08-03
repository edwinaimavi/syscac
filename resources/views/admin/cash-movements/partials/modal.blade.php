<div class="modal fade cash-modal" id="cashMovementModal" tabindex="-1" role="dialog" aria-labelledby="cashMovementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-cash-register"></i></div>
                    <div>
                        <h5 class="modal-title mb-0" id="cashMovementModalLabel">Nuevo movimiento</h5>
                        <small>Registro y control de ingresos y egresos</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="cashMovementForm" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body member-modal-body">
                    <div id="cash-error-messages" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-4">
                            <aside class="cash-side-card">
                                <span class="cash-side-kicker">Impacto en caja</span>
                                <strong id="cashSideCode">{{ $nextCode }}</strong>
                                <div class="cash-side-total">
                                    <span>Monto</span>
                                    <strong id="cashSideAmount">S/ 0.00</strong>
                                </div>
                                <div class="cash-side-metrics">
                                    <div><span>Tipo</span><strong id="cashSideType">Ingreso</strong></div>
                                    <div><span>Estado</span><strong id="cashSideStatus">Registrado</strong></div>
                                </div>
                                <p>Los ingresos aumentan el saldo de caja y los egresos lo disminuyen. No se permite caja negativa.</p>
                            </aside>
                        </div>

                        <div class="col-lg-8">
                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-file-signature"></i> Informacion del movimiento</h6>
                                        <p>Codigo automatico, fecha, tipo, categoria y estado.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Codigo</label>
                                        <input type="text" class="form-control form-control-sm" name="movement_number" value="{{ $nextCode }}" readonly>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="movement_date" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Estado <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="status" required>
                                            <option value="registrado">Registrado</option>
                                            <option value="anulado">Anulado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4 mb-md-0">
                                        <label>Tipo <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="type" required>
                                            <option value="ingreso">Ingreso</option>
                                            <option value="egreso">Egreso</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-8 mb-0">
                                        <label>Categoria <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="category" required></select>
                                    </div>
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-coins"></i> Detalle economico</h6>
                                        <p>Concepto, monto, metodo de pago y referencia.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Concepto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="concept" maxlength="255" placeholder="Concepto del movimiento" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Monto <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">S/</span></div>
                                            <input type="number" class="form-control form-control-sm" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Metodo pago <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="payment_method" required>
                                            <option value="">Seleccione</option>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="yape">Yape</option>
                                            <option value="plin">Plin</option>
                                            <option value="transferencia">Transferencia</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <label>Referencia</label>
                                    <input type="text" class="form-control form-control-sm" name="reference" maxlength="100" placeholder="Operacion, codigo o nota">
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-paperclip"></i> Comprobante</h6>
                                        <p>Archivo de sustento del movimiento.</p>
                                    </div>
                                </div>
                                <label class="share-upload-box mb-0" for="cashVoucher">
                                    <i class="fas fa-paperclip"></i>
                                    <span id="cashVoucherName">JPG, PNG, WEBP o PDF - max. 4 MB</span>
                                </label>
                                <input type="file" class="d-none" id="cashVoucher" name="voucher_path" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">

                                <div id="cashCurrentVoucherBox" class="share-current-file d-none mt-2">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>Comprobante actual disponible.</span>
                                    <a href="#" id="cashCurrentVoucherLink" target="_blank" rel="noopener">Ver comprobante</a>
                                </div>
                            </section>

                            <section class="member-section mb-0">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                                        <p>Nota administrativa opcional.</p>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm" name="observation" rows="3" placeholder="Observacion general"></textarea>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="modal-footer member-modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> <span id="cashMovementSaveText">Guardar movimiento</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
