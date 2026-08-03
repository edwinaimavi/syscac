<div class="modal fade member-modal" id="memberModal" tabindex="-1" role="dialog" aria-labelledby="memberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="memberModalLabel">Nuevo socio</h5>
                        <small>Ficha integral del socio y datos familiares</small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="memberForm" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="reentry_from_member_id">
                <input type="hidden" name="reentry_confirmed" value="0">
                @csrf

                <div class="modal-body member-modal-body">
                    <div class="row">
                        <div class="col-lg-4 col-xl-3 mb-3 mb-lg-0">
                            <aside class="member-photo-card">
                                <div class="member-photo-frame">
                                    <img id="memberImgPreview" src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg" alt="Foto del socio">
                                </div>

                                <label for="photo_path" class="btn member-upload-btn">
                                    <i class="fas fa-camera mr-1"></i> Subir foto
                                </label>
                                <input type="file" id="photo_path" name="photo_path" accept="image/png,image/jpeg,image/jpg,image/webp" class="d-none" onchange="previewMemberImage(event)">
                                <p class="member-file-help">Formatos permitidos: JPG, PNG o WEBP. Tamano maximo 2 MB.</p>

                                <div class="member-summary-list">
                                    <div>
                                        <span>Codigo</span>
                                        <strong id="left_member_code">Pendiente</strong>
                                    </div>
                                    <div>
                                        <span>Estado</span>
                                        <strong id="left_member_status" class="badge badge-success">Vigente</strong>
                                    </div>
                                </div>
                            </aside>
                        </div>

                        <div class="col-lg-8 col-xl-9">
                            <div id="member-error-messages" class="alert alert-danger d-none"></div>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-id-card"></i> Datos personales</h6>
                                        <p>Identificacion y contacto principal del socio.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Codigo</label>
                                        <input type="text" class="form-control form-control-sm" name="code" placeholder="Automatico" readonly>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>DNI <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" name="dni" placeholder="00000000" maxlength="8" required>
                                            <div class="input-group-append">
                                                <button class="btn btn-light border" type="button" id="btnSearchDni" title="Buscar DNI">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha nacimiento <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="birth_date" required>
                                    </div>
                                </div>
                                <div id="memberMinorWarning" class="alert alert-warning py-2 d-none"><i class="fas fa-exclamation-triangle mr-1"></i> <strong>Advertencia:</strong> este socio es menor de edad.</div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Nombres <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="first_name" placeholder="Nombres del socio" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Apellidos <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="last_name" placeholder="Apellidos del socio" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4 mb-0">
                                        <label>Telefono</label>
                                        <input type="text" class="form-control form-control-sm" name="phone" placeholder="9XXXXXXXX" maxlength="20">
                                    </div>
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header align-items-center">
                                    <div>
                                        <h6><i class="fas fa-hands-helping"></i> Beneficiarios en caso de fallecimiento</h6>
                                        <p>Registre a las personas autorizadas para recibir el saldo correspondiente. Si agrega beneficiarios, la suma debe ser 100%.</p>
                                    </div>
                                    <button type="button" class="btn member-add-child-btn" id="btnAddBeneficiary"><i class="fas fa-plus-circle mr-1"></i> Agregar beneficiario</button>
                                </div>
                                <div id="beneficiariesEmptyState" class="member-empty-state">No se registraron beneficiarios.</div>
                                <div id="beneficiariesContainer"></div>
                                <div id="beneficiarySummary" class="alert alert-warning py-2 mt-3 mb-0">
                                    <strong>Total asignado: <span id="beneficiaryTotal">0.00%</span></strong>
                                    <span class="ml-3">Pendiente: <span id="beneficiaryPending">100.00%</span></span>
                                    <small id="beneficiaryMessage" class="d-block mt-1">Agregue beneficiarios para definir la distribución.</small>
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-briefcase"></i> Informacion del socio</h6>
                                        <p>Situacion dentro de la cooperativa y datos laborales.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Fecha ingreso <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="admission_date" required>
                                    </div>
                                    <input type="hidden" name="retirement_date">
                                    <input type="hidden" name="status" value="vigente">
                                    <div class="form-group col-md-8"><label>Estado</label><div class="form-control form-control-sm bg-light">Vigente</div><small class="text-muted">El retiro se gestiona únicamente desde el módulo Retiro de socios.</small></div>
                                    <div class="form-group col-md-4">
                                        <label>Tipo seleccionado <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="member_type_selected" required><option value="nuevo">Nuevo</option><option value="antiguo">Antiguo</option></select>
                                        <small>Tipo calculado: <strong id="memberTypeCalculated">Nuevo</strong></small>
                                    </div>
                                </div>

                                <div id="memberTypeWarning" class="alert alert-warning py-2 d-none"></div>

                                <div class="form-row">
                                    <div class="form-group col-md-5 mb-md-0">
                                        <label>Trabajo actual</label>
                                        <input type="text" class="form-control form-control-sm" name="current_job" placeholder="Trabajo actual">
                                    </div>
                                    <div class="form-group col-md-7 mb-0">
                                        <label>Direccion</label>
                                        <input type="text" class="form-control form-control-sm" name="address" placeholder="Direccion del socio">
                                    </div>
                                </div>
                            </section>

                            <section class="member-section" id="memberEnrollmentSection">
                                <div class="member-section-header"><div><h6><i class="fas fa-file-invoice-dollar"></i> Inscripcion obligatoria</h6><p>Pago independiente de los aportes para socios nuevos. <strong id="memberEnrollmentStatus"></strong></p></div></div>
                                <div class="form-row">
                                    <div class="form-group col-md-3"><label>Fecha <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="enrollment_date"></div>
                                    <div class="form-group col-md-2"><label>Monto</label><input type="number" class="form-control form-control-sm" name="enrollment_amount" value="50.00" readonly></div>
                                    <div class="form-group col-md-3"><label>Metodo <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="enrollment_payment_method"><option value="">Seleccione</option><option value="efectivo">Efectivo</option><option value="yape">Yape</option><option value="plin">Plin</option><option value="transferencia">Transferencia</option><option value="otro">Otro</option></select></div>
                                    <div class="form-group col-md-4"><label>Referencia</label><input class="form-control form-control-sm" name="enrollment_payment_reference"></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-7">
                                        <label for="enrollment_voucher">Voucher</label>
                                        <input type="file" class="d-none" id="enrollment_voucher" name="enrollment_voucher" accept="image/jpeg,image/png,image/webp,application/pdf">
                                        <label for="enrollment_voucher" class="member-enrollment-dropzone">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span><strong id="enrollmentUploadTitle">Subir voucher / comprobante</strong><small>JPG, PNG, WEBP o PDF - Max. 4 MB</small></span>
                                        </label>
                                        <div id="enrollmentNoCurrentVoucher" class="member-voucher-empty mt-2"><i class="fas fa-paperclip"></i><span>No hay comprobante registrado.</span></div>
                                        <div id="enrollmentCurrentVoucher" class="member-enrollment-current d-none">
                                            <div id="enrollmentCurrentVoucherPreview"></div>
                                            <div class="member-enrollment-current-info"><strong>Comprobante actual</strong><div class="member-voucher-actions mt-2"><a id="enrollmentCurrentVoucherView" class="btn btn-light border btn-sm" target="_blank"><i class="fas fa-eye"></i> Ver comprobante</a><a id="enrollmentCurrentVoucherDownload" class="btn btn-light border btn-sm"><i class="fas fa-download"></i> Descargar</a><label for="enrollment_voucher" class="btn btn-outline-info btn-sm mb-0"><i class="fas fa-sync-alt"></i> Cambiar</label></div></div>
                                        </div>
                                        <div id="enrollmentVoucherPreview" class="member-enrollment-preview"></div>
                                    </div>
                                    <div class="form-group col-md-5"><label>Observacion</label><textarea class="form-control form-control-sm" name="enrollment_observation" rows="4" placeholder="Observacion de la inscripcion"></textarea></div>
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header align-items-center">
                                    <div>
                                        <h6><i class="fas fa-heart"></i> Estado civil y familia</h6>
                                        <p>Datos familiares para la ficha social.</p>
                                    </div>
                                    <button type="button" class="btn member-add-child-btn" id="btnAddChild">
                                        <i class="fas fa-plus-circle mr-1"></i> Agregar hijo
                                    </button>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Estado civil <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="civil_status" required>
                                            <option value="">Seleccione</option>
                                            <option value="soltero">Soltero</option>
                                            <option value="casado">Casado</option>
                                            <option value="conviviente">Conviviente</option>
                                            <option value="divorciado">Divorciado</option>
                                            <option value="viudo">Viudo</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label>Nombre de esposa o pareja</label>
                                        <input type="text" class="form-control form-control-sm" name="spouse_name" placeholder="Nombre de pareja">
                                    </div>
                                </div>

                                <div id="childrenEmptyState" class="member-empty-state">
                                    No se agregaron hijos todavia.
                                </div>
                                <div id="childrenContainer"></div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-user-shield"></i> Aval / garante</h6>
                                        <p>Solo se muestran socios mayores de edad, vigentes y sin proceso de retiro.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12 mb-0">
                                        <label>Aval o garante</label>
                                        <select class="form-control form-control-sm" name="guarantor_option" id="guarantor_option">
                                            <option value="">Sin aval o garante registrado</option>
                                            @foreach ($guarantorOptions as $option)
                                                <option value="{{ $option['value'] }}" data-type="{{ $option['type'] }}">
                                                    {{ $option['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(empty($guarantorOptions))<small class="text-muted d-block mt-1">No hay socios vigentes aptos para ser aval/garante.</small>@endif
                                    </div>
                                </div>
                            </section>

                            <section class="member-section mb-0">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                                        <p>Notas administrativas visibles en la ficha del socio.</p>
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <textarea class="form-control form-control-sm" name="observation" rows="3" placeholder="Observacion general"></textarea>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="modal-footer member-modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> <span id="memberSaveText">Guardar socio</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade d-none" id="externalGuarantorModal" tabindex="-1" role="dialog" aria-labelledby="externalGuarantorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="externalGuarantorModalLabel">Nuevo aval externo</h5>
                        <small>Registro rapido de garante no socio</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="externalGuarantorForm" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <div id="guarantor-error-messages" class="alert alert-danger d-none"></div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>DNI <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" name="dni" maxlength="8" required>
                                <div class="input-group-append">
                                    <button class="btn btn-light border" type="button" id="btnSearchGuarantorDni" title="Buscar DNI">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Nombres <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="first_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Apellidos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4 mb-md-0">
                            <label>Telefono</label>
                            <input type="text" class="form-control form-control-sm" name="phone" maxlength="20">
                        </div>
                        <div class="form-group col-md-8 mb-0">
                            <label>Direccion</label>
                            <input type="text" class="form-control form-control-sm" name="address">
                        </div>
                    </div>

                    <div class="form-group mt-3 mb-0">
                        <label>Observacion</label>
                        <textarea class="form-control form-control-sm" name="observation" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer member-modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Guardar aval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
