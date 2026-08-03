<div class="modal fade" id="closureModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <form id="closureForm" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="closureModalLabel">Nuevo cierre de cuenta</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="closure-error-messages" class="alert alert-danger d-none"></div>
                <div id="closurePendingBanner" class="alert alert-warning d-none">
                    <div class="font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Cierre pendiente de regularización</div>
                    <div>Saldo en contra: <strong id="closurePendingBalance">S/ 0.00</strong></div>
                    <small>Debe regularizar la deuda antes de confirmar el retiro.</small>
                </div>

                <div class="form-group"><label>Tratamiento de la utilidad proporcional</label><select name="utility_mode" class="form-control form-control-sm"><option value="pending">Retiro dejando utilidad pendiente para el cierre anual</option><option value="provisional">Retiro con utilidad provisional, si existe utilidad real disponible</option></select><small class="text-muted">Los aportes determinan la participación; no representan utilidad disponible.</small></div>
                <div class="form-row">
                    <div class="form-group col-md-2"><label>Codigo</label><input type="text" name="code" class="form-control" value="{{ $nextCode }}" readonly></div>
                    <div class="form-group col-md-2"><label>Fecha cierre</label><input type="date" name="closure_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                    <div class="form-group col-md-2"><label>Fecha retiro</label><input type="date" name="retirement_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                    <div class="form-group col-md-4" id="closureMemberSelectGroup"><label>Socio</label><select name="member_id" id="closureMemberId" class="form-control" required><option value="">Seleccione...</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->full_name }} - {{ $member->dni }}</option>@endforeach</select></div>
                    <div class="form-group col-md-4 d-none" id="closureMemberReadonlyGroup"><label>Socio</label><div class="form-control bg-light text-truncate" id="closureMemberReadonly">-</div><small class="text-muted"><i class="fas fa-lock mr-1"></i>El socio no puede cambiarse.</small></div>
                    <div class="form-group col-md-2"><label>Estado</label><input type="text" id="closureStatusLabel" class="form-control" value="Calculado" readonly></div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-2"><small class="text-muted">Codigo socio</small><div class="font-weight-bold" id="closureMemberCode">-</div></div>
                    <div class="col-md-2"><small class="text-muted">DNI</small><div class="font-weight-bold" id="closureMemberDni">-</div></div>
                    <div class="col-md-3"><small class="text-muted">Nombre</small><div class="font-weight-bold" id="closureMemberName">-</div></div>
                    <div class="col-md-2"><small class="text-muted">Ingreso</small><div class="font-weight-bold" id="closureMemberAdmission">-</div></div>
                    <div class="col-md-2"><small class="text-muted">Tiempo</small><div class="font-weight-bold" id="closureMemberTime">-</div></div>
                    <div class="col-md-1"><small class="text-muted">Estado</small><div class="font-weight-bold" id="closureMemberStatus">-</div></div>
                </div>

                <div class="cash-summary-grid mb-3">
                    <div class="cash-summary-card"><span>Acciones acumuladas</span><strong id="closureCalcShares">0</strong></div>
                    <div class="cash-summary-card"><span>Aportes realizados</span><strong id="closureCalcContributions">S/ 0.00</strong></div>
                    <div class="cash-summary-card"><span>Prestamos activos</span><strong id="closureCalcLoansCount">0</strong></div>
                    <div class="cash-summary-card"><span>Deuda pendiente</span><strong id="closureCalcLoans">S/ 0.00</strong></div>
                    <div class="cash-summary-card closure-utilities-card"><span>Utilidades pendientes</span><strong id="closureCalcUtilities">S/ 0.00</strong><small id="closureCalcUtilitiesNote">Sin utilidades pendientes. Las utilidades se calculan desde el mes siguiente del aporte y segun los cierres de utilidad registrados.</small></div>
                    <div class="cash-summary-card"><span>Total a favor</span><strong id="closureCalcFavor">S/ 0.00</strong></div>
                    <div class="cash-summary-card"><span>Total en contra</span><strong id="closureCalcAgainst">S/ 0.00</strong></div>
                    <div class="cash-summary-card primary"><span>Saldo final</span><strong id="closureCalcFinal">S/ 0.00</strong></div>
                </div>

                <section class="closure-proportional-utility mb-3"><div class="closure-proportional-title"><div><i class="fas fa-chart-line"></i><strong>Utilidad proporcional acumulada</strong></div><span id="closureUtilityStatus" class="badge badge-warning">No calculada</span></div><div class="closure-proportional-grid"><div><span>Acciones consideradas</span><strong id="closureUtilityActions">0</strong></div><div><span>Meses productivos cerrados</span><strong id="closureUtilityMonths">0</strong></div><div><span>Acción-mes del socio</span><strong id="closureUtilityActionMonth">0</strong></div><div><span>Utilidad disponible</span><strong id="closureUtilityAvailable">S/ 0.00</strong></div><div><span>Utilidad estimada</span><strong id="closureUtilityEstimated">S/ 0.00</strong></div><div><span>Utilidad pagada ahora</span><strong id="closureUtilityPaidNow">S/ 0.00</strong></div><div><span>Pendiente para cierre anual</span><strong id="closureUtilityPending">S/ 0.00</strong></div></div><div id="closureUtilityNote" class="alert alert-light border mt-2 mb-0 py-2">Seleccione un socio y calcule.</div></section>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Tipo</th><th>Descripcion</th><th>A favor</th><th>En contra</th><th>Origen</th></tr></thead>
                        <tbody id="closureCalcRows"><tr><td colspan="5">Seleccione un socio y calcule.</td></tr></tbody>
                    </table>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6"><label>Motivo del retiro</label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
                    <div class="form-group col-md-6"><label>Observacion</label><textarea name="observation" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-dark" id="btnCalculateClosure"><i class="fas fa-calculator"></i> <span id="closureCalculateText">Calcular</span></button>
                <button type="submit" class="btn btn-dark"><i class="fas fa-save"></i> <span id="closureSaveText">Guardar calculo</span></button>
            </div>
        </form>
    </div>
</div>
