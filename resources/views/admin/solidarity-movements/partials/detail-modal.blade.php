<div class="modal fade cash-detail-modal" id="solidarityDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-hands-helping"></i></div>
                    <div>
                        <h5 class="modal-title mb-0">Detalle de solidaridad</h5>
                        <small>Ficha del movimiento solidario</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body member-modal-body">
                <div class="cash-detail-hero">
                    <div>
                        <span>Movimiento solidario</span>
                        <h4 id="detailSolidarityCode">-</h4>
                        <p id="detailSolidarityConcept">-</p>
                    </div>
                    <div id="detailSolidarityStatus">-</div>
                </div>

                <div class="cash-detail-summary">
                    <div><span>Tipo</span><strong id="detailSolidarityType">-</strong></div>
                    <div><span>Monto</span><strong id="detailSolidarityAmount">S/ 0.00</strong></div>
                    <div><span>Impacto</span><strong id="detailSolidarityImpact">-</strong></div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-calendar-check"></i> Movimiento</h6>
                            <div class="member-detail-grid">
                                <div><span>Fecha</span><strong id="detailSolidarityDate">-</strong></div>
                                <div><span>Metodo pago</span><strong id="detailSolidarityPaymentMethod">-</strong></div>
                                <div><span>Referencia</span><strong id="detailSolidarityReference">-</strong></div>
                                <div><span>Caja</span><strong id="detailSolidarityCash">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-user"></i> Socio relacionado</h6>
                            <div class="member-detail-grid">
                                <div><span>Socio</span><strong id="detailSolidarityMember">-</strong></div>
                                <div><span>DNI</span><strong id="detailSolidarityDni">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-paperclip"></i> Comprobante</h6>
                            <div class="share-detail-links">
                                <a href="#" id="detailSolidarityVoucherView" class="btn btn-light border disabled" target="_blank" rel="noopener"><i class="fas fa-eye mr-1"></i> Sin comprobante</a>
                                <a href="#" id="detailSolidarityVoucherDownload" class="btn btn-light border disabled"><i class="fas fa-download mr-1"></i> Descargar comprobante</a>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-receipt"></i> Recibo</h6>
                            <div class="share-detail-links">
                                <a href="#" id="detailSolidarityReceipt" class="btn btn-light border disabled" target="_blank" rel="noopener"><i class="fas fa-print mr-1"></i> Sin recibo</a>
                                <a href="#" id="detailSolidarityReceiptPdf" class="btn btn-light border disabled" target="_blank" rel="noopener"><i class="fas fa-file-pdf mr-1"></i> PDF</a>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-lg-0">
                            <h6><i class="fas fa-history"></i> Auditoria</h6>
                            <div class="member-detail-grid">
                                <div><span>Registrado el</span><strong id="detailSolidarityCreatedAt">-</strong></div>
                                <div><span>Usuario</span><strong id="detailSolidarityCreatedBy">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-lg-0">
                            <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                            <div id="detailSolidarityObservation" class="member-detail-note">-</div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
