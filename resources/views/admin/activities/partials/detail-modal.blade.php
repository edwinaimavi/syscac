<div class="modal fade cash-detail-modal" id="activityDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-calendar-check"></i></div><div><h5 class="modal-title mb-0">Detalle de actividad</h5><small>Resumen y movimientos asociados</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body member-modal-body">
            <div class="cash-detail-hero"><div><span>Actividad</span><h4 id="detailActivityCode">-</h4><p id="detailActivityName">-</p></div><div id="detailActivityStatus">-</div></div>
            <div class="cash-detail-summary">
                <div><span>Ingresos</span><strong id="detailActivityIncome">S/ 0.00</strong></div>
                <div><span>Egresos</span><strong id="detailActivityExpense">S/ 0.00</strong></div>
                <div><span>Utilidad</span><strong id="detailActivityProfit">S/ 0.00</strong></div>
            </div>
            <div class="row">
                <div class="col-lg-6"><section class="member-detail-card"><h6><i class="fas fa-info-circle"></i> Datos</h6><div class="member-detail-grid"><div><span>Fecha</span><strong id="detailActivityDate">-</strong></div><div><span>Cerrado el</span><strong id="detailActivityClosedAt">-</strong></div><div><span>Cerrado por</span><strong id="detailActivityClosedBy">-</strong></div><div><span>Registrado por</span><strong id="detailActivityCreatedBy">-</strong></div></div></section></div>
                <div class="col-lg-6"><section class="member-detail-card"><h6><i class="fas fa-clipboard-list"></i> Descripcion</h6><div id="detailActivityDescription" class="member-detail-note">-</div></section></div>
            </div>
            <section class="member-detail-card mb-0">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                    <h6 class="mb-2"><i class="fas fa-exchange-alt"></i> Movimientos</h6>
                    <div class="btn-group btn-group-sm mb-2">
                        @can('admin.actividades.movement_create')<button type="button" class="btn btn-primary" id="btnNewActivityMovement"><i class="fas fa-plus mr-1"></i> Nuevo movimiento</button>@endcan
                        @can('admin.actividades.report')<a href="#" target="_blank" id="detailActivityReport" class="btn btn-light border"><i class="fas fa-file-alt mr-1"></i> Reporte</a>@endcan
                        @can('admin.actividades.close')<button type="button" class="btn btn-light border" id="btnCloseActivityFromDetail"><i class="fas fa-lock mr-1"></i> Cerrar</button>@endcan
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover text-center mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Socio</th><th>Concepto</th><th>Monto</th><th>Estado</th><th></th></tr></thead><tbody id="detailActivityMovementRows"><tr><td colspan="7">Sin movimientos.</td></tr></tbody></table>
                </div>
            </section>
        </div>
    </div></div>
</div>
