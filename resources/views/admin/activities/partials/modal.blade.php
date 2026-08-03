<div class="modal fade cash-modal" id="activityModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-calendar-check"></i></div><div><h5 class="modal-title mb-0" id="activityModalLabel">Nueva actividad</h5><small>Actividad economica anual</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <form id="activityForm" autocomplete="off">@csrf
            <div class="modal-body member-modal-body">
                <div id="activity-error-messages" class="alert alert-danger d-none"></div>
                <section class="member-section">
                    <div class="member-section-header"><div><h6><i class="fas fa-file-signature"></i> Informacion de la actividad</h6><p>Codigo automatico, nombre, fecha y estado.</p></div></div>
                    <div class="form-row">
                        <div class="form-group col-md-3"><label>Codigo</label><input type="text" class="form-control form-control-sm" name="code" readonly></div>
                        <div class="form-group col-md-5"><label>Nombre <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" name="name" maxlength="255" required></div>
                        <div class="form-group col-md-2"><label>Fecha <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="activity_date" required></div>
                        <div class="form-group col-md-2"><label>Estado</label><select class="form-control form-control-sm" name="status"><option value="abierta">Abierta</option><option value="cerrada">Cerrada</option><option value="anulada">Anulada</option></select></div>
                    </div>
                </section>
                <section class="member-section mb-0">
                    <div class="member-section-header"><div><h6><i class="fas fa-clipboard-list"></i> Descripcion</h6><p>Detalle administrativo de la actividad.</p></div></div>
                    <textarea class="form-control form-control-sm" name="description" rows="4"></textarea>
                </section>
            </div>
            <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i><span id="activitySaveText">Guardar actividad</span></button></div>
        </form>
    </div></div>
</div>
