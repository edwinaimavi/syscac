@once
<style>
    #closureDetailModal .retirement-detail-hero { position:relative; display:flex!important; align-items:center!important; justify-content:space-between!important; gap:16px!important; min-height:105px!important; margin:0 0 16px!important; padding:18px 54px 18px 22px!important; border-radius:18px!important; color:#fff!important; background:linear-gradient(135deg,#123456 0%,#1f4e79 100%)!important; box-shadow:0 14px 32px rgba(15,35,65,.20)!important; }
    #closureDetailModal .retirement-hero-kicker { margin-bottom:5px!important; color:rgba(255,255,255,.82)!important; font-size:11px!important; font-weight:800!important; letter-spacing:.08em!important; text-transform:uppercase!important; }
    #closureDetailModal .retirement-hero-title { margin-bottom:6px!important; color:#fff!important; background:transparent!important; font-size:15px!important; font-weight:800!important; line-height:1.2!important; }
    #closureDetailModal .retirement-hero-code { color:#fff!important; background:transparent!important; font-size:28px!important; font-weight:900!important; line-height:1!important; letter-spacing:.03em!important; }
    #closureDetailModal .retirement-status-badge { display:inline-flex!important; align-items:center!important; justify-content:center!important; padding:7px 14px!important; border-radius:999px!important; font-size:12px!important; font-weight:900!important; white-space:nowrap!important; box-shadow:0 8px 18px rgba(0,0,0,.15)!important; }
    #closureDetailModal .retirement-status-badge.status-confirmed { color:#166534!important; background:#dcfce7!important; }
    #closureDetailModal .retirement-status-badge.status-pending { color:#92400e!important; background:#fef3c7!important; }
    #closureDetailModal .retirement-status-badge.status-cancelled, #closureDetailModal .retirement-status-badge.status-annulled { color:#991b1b!important; background:#fee2e2!important; }
    @media (max-width:768px) { #closureDetailModal .retirement-detail-hero { flex-direction:column!important; align-items:flex-start!important; min-height:auto!important; } #closureDetailModal .retirement-hero-code { font-size:22px!important; } }
</style>
@endonce

<div class="modal fade" id="closureDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content closure-detail-modal">
            <div class="modal-body closure-detail-body">
                <div class="retirement-detail-hero">
                    <div class="retirement-hero-left">
                        <div class="retirement-hero-kicker"><i class="fas fa-university mr-1" aria-hidden="true"></i> SysCaC · Gestión de socios</div>
                        <div class="retirement-hero-title">Ficha financiera de cierre de cuenta</div>
                        <div id="detailClosureCode" class="retirement-hero-code">No aplica</div>
                    </div>
                    <div id="detailClosureStatus" class="retirement-hero-right"></div>
                    <button type="button" class="retirement-hero-close" data-dismiss="modal" aria-label="Cerrar detalle"><span aria-hidden="true">&times;</span></button>
                </div>
                <div id="detailClosureAlert" class="alert mb-3"></div>

                <section class="closure-detail-section closure-identity-section">
                    <div class="closure-detail-section-title"><div><i class="fas fa-user-circle"></i><span>Socio y cierre</span></div><strong id="detailClosureSettlement">No aplica</strong></div>
                    <div class="closure-detail-grid closure-detail-grid-4">
                        <div><span>Nombre del socio</span><strong id="detailClosureMember">No aplica</strong></div>
                        <div><span>DNI</span><strong id="detailClosureDni">No aplica</strong></div>
                        <div><span>Código de socio</span><strong id="detailClosureMemberCode">No aplica</strong></div>
                        <div><span>Fecha de ingreso</span><strong id="detailClosureAdmission">No aplica</strong></div>
                        <div><span>Fecha de retiro</span><strong id="detailClosureRetirement">No aplica</strong></div>
                        <div><span>Fecha de cierre</span><strong id="detailClosureDate">No aplica</strong></div>
                        <div class="span-2"><span>Motivo del retiro</span><strong id="detailClosureReason">No aplica</strong></div>
                    </div>
                </section>

                <section class="closure-detail-section"><div class="closure-detail-section-title"><div><i class="fas fa-chart-line"></i><span>Utilidad proporcional acumulada</span></div><span id="detailClosureUtilityStatus" class="badge badge-warning">No calculada</span></div><div class="closure-detail-grid closure-detail-grid-4"><div><span>Acciones consideradas</span><strong id="detailClosureUtilityActions">0</strong></div><div><span>Meses productivos cerrados</span><strong id="detailClosureUtilityMonths">0</strong></div><div><span>Acción-mes del socio</span><strong id="detailClosureUtilityActionMonth">0</strong></div><div><span>Utilidad disponible del periodo</span><strong id="detailClosureUtilityAvailable">S/ 0.00</strong></div><div><span>Utilidad estimada</span><strong id="detailClosureUtilityEstimated">S/ 0.00</strong></div><div><span>Utilidad pagada ahora</span><strong id="detailClosureUtilityPaidNow">S/ 0.00</strong></div><div><span>Pendiente para cierre anual</span><strong id="detailClosureUtilityPending">S/ 0.00</strong></div><div><span>Modalidad</span><strong id="detailClosureUtilityMode">Pendiente</strong></div></div><div id="detailClosureUtilityNote" class="alert alert-light border mt-3 mb-0"></div></section>

                <section class="closure-detail-section">
                    <div class="closure-detail-section-title"><div><i class="fas fa-chart-pie"></i><span>Resumen financiero</span></div></div>
                    <div class="closure-financial-grid">
                        <div class="info"><span>Acciones acumuladas</span><strong id="detailClosureShares">0</strong></div>
                        <div class="info"><span>Total aportes</span><strong id="detailClosureContributions">S/ 0.00</strong></div>
                        <div class="danger"><span>Deudas pendientes</span><strong id="detailClosureLoans">S/ 0.00</strong></div>
                        <div class="info closure-utilities-card"><span>Utilidades pendientes</span><strong id="detailClosureUtilities">S/ 0.00</strong><small id="detailClosureUtilitiesNote">Sin utilidades pendientes.</small></div>
                        <div class="success"><span>Total a favor</span><strong id="detailClosureFavor">S/ 0.00</strong></div>
                        <div class="danger"><span>Total en contra</span><strong id="detailClosureAgainst">S/ 0.00</strong></div>
                        <div class="balance" id="detailClosureFinalCard"><span>Saldo final</span><strong id="detailClosureFinal">S/ 0.00</strong></div>
                    </div>
                </section>

                <section class="closure-detail-section">
                    <div class="closure-detail-section-title"><div><i class="fas fa-route"></i><span>Trazabilidad del cierre</span></div></div>
                    <div class="closure-audit-grid">
                        <div><i class="fas fa-calculator"></i><span>Calculado por</span><strong id="detailClosureCreatedBy">No aplica</strong><small id="detailClosureCreatedAt">No aplica</small></div>
                        <div><i class="fas fa-check-circle"></i><span>Confirmado por</span><strong id="detailClosureClosedBy">No aplica</strong><small id="detailClosureClosedAt">No aplica</small></div>
                        <div><i class="fas fa-wallet"></i><span>Método de pago</span><strong id="detailClosurePayment">No aplica</strong><small id="detailClosureReference">No aplica</small></div>
                        <div><i class="fas fa-receipt"></i><span>Recibo generado</span><strong id="detailClosureReceiptState">No aplica</strong><small id="detailClosureReceiptNumber">No aplica</small></div>
                        <div><i class="fas fa-cash-register"></i><span>Movimiento de Caja</span><strong id="detailClosureCashState">No aplica</strong><small id="detailClosureCashNumber">No aplica</small></div>
                        <div><i class="fas fa-paperclip"></i><span>Comprobante</span><strong id="detailClosureVoucherState">Sin comprobante registrado</strong><small>Pago o devolución</small></div>
                    </div>
                </section>

                <section class="closure-detail-section d-none" id="detailClosureCashSection">
                    <div class="closure-detail-section-title"><div><i class="fas fa-cash-register"></i><span>Movimiento de Caja generado</span></div><a id="detailClosureCashLink" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> Ver movimiento</a></div>
                    <div class="closure-detail-grid closure-detail-grid-4">
                        <div><span>Tipo</span><strong id="detailClosureCashType">No aplica</strong></div>
                        <div><span>Monto</span><strong id="detailClosureCashAmount">No aplica</strong></div>
                        <div><span>Método de pago</span><strong id="detailClosureCashMethod">No aplica</strong></div>
                        <div><span>Referencia</span><strong id="detailClosureCashReference">No aplica</strong></div>
                        <div><span>Fecha</span><strong id="detailClosureCashDate">No aplica</strong></div>
                        <div><span>Estado</span><strong id="detailClosureCashStatus">No aplica</strong></div>
                        <div class="span-2"><span>Saldo posterior de Caja</span><strong id="detailClosureCashBalance">No aplica</strong></div>
                    </div>
                </section>

                <section class="closure-detail-section">
                    <div class="closure-detail-section-title"><div><i class="fas fa-file-image"></i><span>Comprobante y documentos</span></div></div>
                    <div class="closure-document-layout">
                        <div id="detailClosureVoucherPreview" class="closure-voucher-preview"><div><i class="fas fa-file-circle-xmark"></i><strong>Sin comprobante registrado</strong></div></div>
                        <div class="closure-document-actions">
                            <a id="detailClosurePdf" class="btn btn-primary" target="_blank"><i class="fas fa-file-pdf mr-1"></i> Constancia PDF</a>
                            <a id="detailClosureReport" class="btn btn-light border" target="_blank"><i class="fas fa-eye mr-1"></i> Vista del documento</a>
                            <a id="detailClosureReceipt" class="btn btn-light border d-none" target="_blank"><i class="fas fa-receipt mr-1"></i> Ver recibo</a>
                            <a id="detailClosureReceiptPdf" class="btn btn-light border d-none" target="_blank"><i class="fas fa-file-pdf mr-1"></i> Recibo PDF</a>
                            <a id="detailClosureVoucherView" class="btn btn-light border d-none" target="_blank"><i class="fas fa-eye mr-1"></i> Ver comprobante</a>
                            <a id="detailClosureVoucherDownload" class="btn btn-light border d-none"><i class="fas fa-download mr-1"></i> Descargar</a>
                        </div>
                    </div>
                </section>

                <section class="closure-detail-section">
                    <div class="closure-detail-section-title"><div><i class="fas fa-list-alt"></i><span>Detalle del cálculo</span></div></div>
                    <div class="table-responsive">
                        <table class="table table-sm closure-detail-table mb-0"><thead><tr><th>Tipo</th><th>Descripción</th><th class="text-right">A favor</th><th class="text-right">En contra</th><th>Origen</th><th>Código relacionado</th></tr></thead><tbody id="detailClosureRows"></tbody></table>
                    </div>
                </section>

                <section class="closure-detail-section">
                    <div class="closure-detail-section-title"><div><i class="fas fa-clipboard"></i><span>Observaciones</span></div></div>
                    <p id="detailClosureObservation" class="mb-0">No aplica</p>
                </section>

                <section class="closure-annulment-section d-none" id="detailClosureAnnulmentSection">
                    <div class="closure-detail-section-title"><div><i class="fas fa-ban"></i><span>Información de anulación</span></div></div>
                    <div class="closure-detail-grid closure-detail-grid-4">
                        <div><span>Anulado por</span><strong id="detailClosureAnnulledBy">No aplica</strong></div>
                        <div><span>Fecha de anulación</span><strong id="detailClosureAnnulledAt">No aplica</strong></div>
                        <div><span>Movimiento de reversa</span><strong id="detailClosureReversal">No aplica</strong></div>
                        <div class="span-4"><span>Motivo de anulación</span><strong id="detailClosureAnnulmentReason">No aplica</strong></div>
                    </div>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cerrar</button></div>
        </div>
    </div>
</div>
