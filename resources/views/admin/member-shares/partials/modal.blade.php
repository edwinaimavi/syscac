<div class="modal fade share-modal" id="memberShareModal" tabindex="-1" role="dialog" aria-labelledby="memberShareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="memberShareModalLabel">Nuevo aporte</h5>
                        <small>Registro de acciones economicas del socio</small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="memberShareForm" enctype="multipart/form-data" autocomplete="off">
                @csrf

                <div class="modal-body member-modal-body">
                    <div id="share-error-messages" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-4">
                            <aside class="share-side-card">
                                <span class="share-side-kicker">Resumen del aporte</span>
                                <strong id="shareSideCode">{{ $nextCode }}</strong>
                                <div class="share-side-total">
                                    <span>Total pagado</span>
                                    <strong id="shareSideAmount">S/ 0.00</strong>
                                </div>
                                <div class="share-side-metrics">
                                    <div>
                                        <span>Acciones</span>
                                        <strong id="shareSideQuantity">0</strong>
                                    </div>
                                    <div>
                                        <span>Valor</span>
                                        <strong id="shareSideValue">S/ {{ $defaultShareValue }}</strong>
                                    </div>
                                </div>
                                <div class="share-side-metrics"><div><span>Capital acciones</span><strong id="shareSideCapital">S/ 0.00</strong></div><div><span>Solidaridad</span><strong id="shareSideSolidarity">S/ 0.00</strong></div><div><span>Gasto admin.</span><strong id="shareSideAdministrative">S/ 0.00</strong></div></div>
                                <p>Solo el capital para acciones genera acciones y es reembolsable.</p>
                            </aside>
                        </div>

                        <div class="col-lg-8">
                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-file-signature"></i> Informacion del aporte</h6>
                                        <p>Codigo, fecha, socio y estado administrativo.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Codigo</label>
                                        <input type="text" class="form-control form-control-sm" name="code" value="{{ $nextCode }}" readonly>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha de aporte <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="date" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Estado <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="status" required>
                                            <option value="registrado">Registrado</option>
                                            <option value="anulado">Anulado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <label>Socio vigente <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" name="member_id" id="share_member_id" required>
                                        <option value="">Buscar por codigo, DNI o nombres</option>
                                        @foreach ($members as $member)
                                            <option value="{{ $member->id }}">
                                                {{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-calculator"></i> Monto y acciones</h6>
                                        <p>La cantidad de acciones se calcula automaticamente.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Monto total pagado <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">S/</span></div>
                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="total_paid" placeholder="50.00" required>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Valor por accion <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">S/</span></div>
                                            <input type="number" step="0.01" class="form-control form-control-sm" name="share_value" value="{{ $defaultShareValue }}" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Cantidad de acciones</label>
                                        <input type="number" step="1" min="1" class="form-control form-control-sm" name="shares_quantity" readonly>
                                    </div>
                                </div>
                                <div class="form-row"><div class="form-group col-md-4"><label>Capital para acciones</label><input name="share_capital_amount" class="form-control form-control-sm" readonly></div><div class="form-group col-md-4"><label>Solidaridad</label><input type="number" step="0.01" min="0" name="solidarity_amount" value="0.00" class="form-control form-control-sm"></div><div class="form-group col-md-4"><label>Gastos administrativos</label><input type="number" step="0.01" min="0" name="administrative_fee_amount" value="0.00" class="form-control form-control-sm"></div></div>
                                <div id="shareBreakdownWarning" class="alert alert-warning d-none py-2 mb-0">El monto no cuadra con acciones exactas. Ajuste solidaridad/gastos administrativos o el monto total.</div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-receipt"></i> Pago y comprobante</h6>
                                        <p>Metodo de pago, referencia y archivo de sustento.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Metodo de pago <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="payment_method" required>
                                            <option value="">Seleccione</option>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="yape">Yape</option>
                                            <option value="plin">Plin</option>
                                            <option value="transferencia">Transferencia</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4" id="sharePaymentReferenceGroup">
                                        <label>Referencia de pago <span id="sharePaymentReferenceRequired" class="text-danger d-none">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="payment_reference" maxlength="100" placeholder="Operación, código o número de referencia">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label id="shareVoucherLabel">Comprobante</label>
                                        <label class="share-upload-box mb-0" for="shareVoucher">
                                            <i class="fas fa-paperclip"></i>
                                            <span id="shareVoucherName">JPG, PNG, WEBP o PDF - max. 4 MB</span>
                                        </label>
                                        <input type="file" class="d-none" id="shareVoucher" name="voucher_path" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf">
                                    </div>
                                </div>

                                <div id="currentVoucherBox" class="share-current-file d-none">
                                    <div id="currentVoucherPreview" class="share-voucher-preview"></div>
                                    <div class="share-voucher-meta">
                                        <strong id="currentVoucherTitle">Comprobante actual</strong>
                                        <span id="currentVoucherStatus">Sin comprobante registrado</span>
                                        <div class="share-detail-links mt-2">
                                            <a href="#" id="currentVoucherLink" class="btn btn-light border btn-sm disabled" target="_blank" rel="noopener">
                                                <i class="fas fa-eye mr-1"></i> Ver comprobante
                                            </a>
                                            <a href="#" id="currentVoucherDownloadLink" class="btn btn-light border btn-sm disabled" target="_blank" rel="noopener">
                                                <i class="fas fa-download mr-1"></i> Descargar
                                            </a>
                                            <button type="button" id="btnChangeShareVoucher" class="btn btn-light border btn-sm d-none">
                                                <i class="fas fa-sync-alt mr-1"></i> Cambiar comprobante
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="member-section mb-0">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                                        <p>Nota administrativa opcional para el aporte.</p>
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
                        <i class="fas fa-save mr-1"></i> <span id="memberShareSaveText">Guardar aporte</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
