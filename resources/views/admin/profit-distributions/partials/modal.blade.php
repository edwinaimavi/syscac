<div class="modal fade cash-modal" id="profitModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-chart-pie"></i></div><div><h5 class="modal-title mb-0" id="profitModalLabel">Nueva distribución de utilidades</h5><small>Cálculo proporcional por acción-mes</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <form id="profitForm" autocomplete="off">@csrf
            <input type="hidden" name="distribution_id" value="">
            <div class="modal-body member-modal-body">
                <div id="profit-error-messages" class="alert alert-danger d-none"></div>
                <div id="profitNoAvailabilityWarning" class="alert alert-warning d-none"><i class="fas fa-exclamation-circle mr-1"></i> <strong>No hay utilidad disponible para distribuir.</strong> El saldo se genera automáticamente desde los intereses y moras efectivamente cobrados en el periodo.</div>
                <div id="profitNoDataWarning" class="alert alert-warning d-none"><div><i class="fas fa-exclamation-triangle mr-1"></i> <strong>No hay aportes válidos para calcular utilidades en este periodo.</strong></div><ul class="small mb-0 mt-2 pl-3"><li>No existen aportes con utilidad generada dentro del periodo.</li><li>Los aportes son del mismo mes y recién generan desde el mes siguiente.</li><li>Los socios están retirados o sus utilidades ya fueron liquidadas.</li><li>Los aportes están anulados.</li></ul></div>
                <section class="member-section"><div class="member-section-header"><div><h6><i class="fas fa-calendar-alt"></i> Información del periodo</h6><p>Seleccione el periodo para actualizar la utilidad desde los cobros reales.</p></div></div>
                    <div class="profit-availability-grid mb-3">
                        <div class="profit-financial-card participation"><span>Total aportes / acciones</span><strong id="profitContributionsAmount">S/ 0.00 · 0 acciones</strong><small>Solo se usa para calcular la participación; no es utilidad.</small></div>
                        <div class="profit-financial-card available"><span>Utilidad disponible</span><strong id="profitAvailableAmount">S/ 0.00</strong><small id="profitAvailableSources">Intereses y moras cobradas, menos distribuciones del mismo periodo.</small></div>
                        <div class="profit-financial-card distributing"><span>Monto a distribuir</span><strong id="profitDistributingAmount">S/ 0.00</strong><small>Importe ingresado para esta distribución.</small></div>
                        <div class="profit-financial-card remaining"><span>Saldo pendiente por distribuir</span><strong id="profitRemainingAmount">S/ 0.00</strong><small id="profitRemainingText">Quedará disponible para una futura distribución.</small></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="btnRefreshProfit"><i class="fas fa-sync-alt mr-1"></i> Actualizar desde cobros</button>
                    @can('admin.utilidades.sources')<button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="btnViewProfitSources"><i class="fas fa-list-alt mr-1"></i> Ver detalle</button>@endcan
                    <div class="cash-detail-summary mb-3"><div><span>Intereses cobrados</span><strong id="profitInterestCollected">S/ 0.00</strong></div><div><span>Moras cobradas</span><strong id="profitLateFeesCollected">S/ 0.00</strong></div><div><span>Ajustes positivos</span><strong id="profitPositiveAdjustments">S/ 0.00</strong></div><div><span>Ajustes negativos</span><strong id="profitNegativeAdjustments">S/ 0.00</strong></div><div><span>Utilidad neta</span><strong id="profitGeneratedAmount">S/ 0.00</strong></div><div><span>Ya distribuido</span><strong id="profitAlreadyDistributed">S/ 0.00</strong></div></div>
                    <div id="profitFinancialWarning" class="alert alert-danger py-2 d-none" role="alert"></div>
                    <div class="form-row">
                        <div class="form-group col-md-2"><label>Codigo</label><input type="text" class="form-control form-control-sm" name="code" readonly></div>
                        <div class="form-group col-md-2"><label>Anio <span class="text-danger">*</span></label><input type="number" class="form-control form-control-sm" name="period_year" min="2000" required></div>
                        <input type="hidden" name="period_month" value="">
                        <div class="form-group col-md-2"><label>Inicio <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="start_date" required></div>
                        <div class="form-group col-md-2"><label>Fin <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="end_date" required></div>
                        <div class="form-group col-md-2"><label>Estado</label><select class="form-control form-control-sm" name="status"><option value="calculado">Calculado</option></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Monto a distribuir <span class="text-danger">*</span></label><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">S/</span></div><input type="number" class="form-control form-control-sm" name="total_profit" min="0.01" step="0.01" required></div></div>
                        <div class="form-group col-md-8"><label>Observacion</label><input type="text" class="form-control form-control-sm" name="observation"></div>
                    </div>
                </section>
                <section class="member-section"><div class="member-section-header"><div><h6><i class="fas fa-calculator"></i> Resumen del cálculo</h6><p>Las utilidades se calculan por acción-mes. Cada aporte empieza a generar utilidad desde el mes siguiente de su registro.</p></div></div>
                    <div class="cash-detail-summary"><div><span>Utilidad total</span><strong id="profitPreviewTotal">S/ 0.00</strong></div><div><span>Total acción-mes</span><strong id="profitPreviewShares">0</strong></div><div><span>Utilidad por acción-mes</span><strong id="profitPreviewPerShare">S/ 0.00</strong></div><div><span>Socios considerados</span><strong id="profitPreviewMembers">0</strong></div></div>
                    <small class="text-muted">Origen: exclusivamente intereses y moras cobradas en el rango seleccionado.</small>
                </section>
                <section class="member-section mb-0"><div class="member-section-header"><div><h6><i class="fas fa-users"></i> Vista previa</h6><p>Participación auditada de cada socio según sus aportes y meses productivos.</p></div></div>
                    <div class="profit-preview-scroll"><table class="table table-sm table-hover text-center mb-0 profit-preview-table"><thead><tr><th class="profit-member-column">Socio</th><th>DNI</th><th>Código</th><th>Aportes</th><th>Acciones consideradas</th><th>Meses</th><th>Acción-mes</th><th>Participación</th><th>Utilidad</th><th>Estado</th></tr></thead><tbody id="profitPreviewRows"><tr><td colspan="10">Calcule para ver la vista previa.</td></tr></tbody></table></div>
                </section>
            </div>
            <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-info" id="btnCalculateProfit"><i class="fas fa-calculator mr-1"></i> Calcular</button><button type="submit" class="btn btn-primary" id="profitSaveButton" disabled><i class="fas fa-save mr-1"></i><span id="profitSaveText">Guardar distribución</span></button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="profitSourceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title mb-0"><i class="fas fa-coins text-success mr-2"></i>Fuentes manuales de utilidad</h5><small class="text-muted">Registre únicamente dinero real disponible para distribuir.</small></div><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <form id="profitSourceForm">@csrf<div class="modal-body">
            <div id="profit-source-errors" class="alert alert-danger d-none"></div>
            <div class="form-row">
                <div class="form-group col-md-2"><label>Fecha *</label><input type="date" name="source_date" class="form-control form-control-sm" required></div>
                <div class="form-group col-md-3"><label>Tipo de ajuste *</label><select name="adjustment_type" class="form-control form-control-sm" required><option value="previous_year_discount">Descuento año anterior</option><option value="previously_paid">Utilidad pagada previamente</option><option value="negative">Ajuste manual negativo</option><option value="positive">Ajuste manual positivo</option><option value="administrative_correction">Corrección administrativa</option></select></div>
                <div class="form-group col-md-2"><label>Monto *</label><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">S/</span></div><input type="number" name="amount" class="form-control" min="0.01" step="0.01" required></div></div>
                <div class="form-group col-md-5"><label>Motivo *</label><input type="text" name="reason" class="form-control form-control-sm" maxlength="180" placeholder="Ej. Descuento año anterior" required></div>
            </div>
            <div class="form-group"><label>Observación</label><textarea name="observation" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea></div>
            <div class="table-responsive profit-source-history"><table class="table table-sm table-hover mb-0"><thead><tr><th>Fecha</th><th>Código</th><th>Motivo</th><th>Monto</th><th>Responsable</th><th>Estado</th><th></th></tr></thead><tbody id="profitSourceRows"><tr><td colspan="7" class="text-center text-muted">Cargando...</td></tr></tbody></table></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cerrar</button><button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Registrar utilidad</button></div></form>
    </div></div>
</div>
