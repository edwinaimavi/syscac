<div class="modal fade member-modal" id="guarantorModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <h5 class="modal-title mb-0" id="guarantorModalLabel">Nuevo aval</h5>
                        <small>Ficha completa del aval o garante</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <form id="guarantorForm" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body member-modal-body">
                    <div id="guarantor-error-messages" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-4 col-xl-3 mb-3 mb-lg-0">
                            <aside class="member-photo-card">
                                <div class="member-photo-frame">
                                    <img id="guarantorImgPreview" src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg" alt="Foto del aval">
                                </div>
                                <label for="guarantor_photo_path" class="btn member-upload-btn">
                                    <i class="fas fa-camera mr-1"></i> Subir foto
                                </label>
                                <input type="file" id="guarantor_photo_path" name="photo_path" accept="image/png,image/jpeg,image/jpg,image/webp" class="d-none">
                                <p class="member-file-help">Formatos permitidos: JPG, PNG o WEBP. Tamano maximo 2 MB.</p>
                                <div class="member-summary-list">
                                    <div><span>Codigo</span><strong id="guarantorSideCode">Pendiente</strong></div>
                                    <div><span>Estado</span><strong id="guarantorSideStatus" class="badge badge-success">Activo</strong></div>
                                </div>
                            </aside>
                        </div>

                        <div class="col-lg-8 col-xl-9">
                            <section class="member-section">
                                <div class="member-section-header"><div><h6><i class="fas fa-id-card"></i> Informacion del aval</h6><p>Codigo automatico, tipo y datos principales.</p></div></div>
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label>Codigo</label>
                                        <input type="text" class="form-control form-control-sm" name="code" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Tipo <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="type" required>
                                            <option value="externo">Aval externo</option>
                                            <option value="socio">Socio interno</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6" id="guarantorMemberBox">
                                        <label>Socio relacionado</label>
                                        <select class="form-control form-control-sm" name="member_id" id="guarantor_member_id">
                                            <option value="">Seleccione socio</option>
                                            @foreach ($members as $member)
                                                <option value="{{ $member->id }}" data-dni="{{ $member->dni }}" data-first-name="{{ $member->first_name }}" data-last-name="{{ $member->last_name }}">
                                                    {{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($members->isEmpty())<small class="text-muted d-block mt-1">No hay socios vigentes aptos para ser aval/garante.</small>@endif
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label>DNI <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" name="dni" maxlength="8" required>
                                            <div class="input-group-append">
                                                <button class="btn btn-light border" type="button" id="btnSearchGuarantorDniMain"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Nombres <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="first_name" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Apellidos <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="last_name" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Telefono</label>
                                        <input type="text" class="form-control form-control-sm" name="phone" maxlength="20">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-3 mb-md-0">
                                        <label>Estado <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="status" required>
                                            <option value="activo">Activo</option>
                                            <option value="inactivo">Inactivo</option>
                                            <option value="anulado">Anulado</option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section class="member-section mb-0">
                                <div class="member-section-header"><div><h6><i class="fas fa-clipboard-list"></i> Datos complementarios</h6><p>Direccion, ocupacion, relacion y observaciones.</p></div></div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Direccion</label>
                                        <input type="text" class="form-control form-control-sm" name="address">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Ocupacion</label>
                                        <input type="text" class="form-control form-control-sm" name="occupation">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Relacion / parentesco</label>
                                        <input type="text" class="form-control form-control-sm" name="relationship">
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label>Observacion</label>
                                    <textarea class="form-control form-control-sm" name="observation" rows="3"></textarea>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="modal-footer member-modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <span id="guarantorSaveText">Guardar aval</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
