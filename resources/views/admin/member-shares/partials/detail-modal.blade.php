<div class="modal fade share-detail-modal" id="memberShareDetailModal" tabindex="-1" role="dialog" aria-labelledby="memberShareDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="memberShareDetailModalLabel">Detalle del aporte</h5>
                        <small>Ficha de acciones, pago y recibo del socio</small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body member-modal-body">
                <div class="share-detail-hero">
                    <div>
                        <span>Aporte registrado</span>
                        <h4 id="detailShareCode">-</h4>
                        <p id="detailShareMember">-</p>
                    </div>
                    <div class="share-detail-hero-badge" id="detailShareStatus">-</div>
                </div>

                <div class="share-detail-summary">
                    <div>
                        <span>Total pagado</span>
                        <strong id="detailShareAmount">S/ 0.00</strong>
                    </div>
                    <div><span>Capital acciones</span><strong id="detailShareCapital">S/ 0.00</strong></div>
                    <div><span>Solidaridad</span><strong id="detailShareSolidarity">S/ 0.00</strong></div>
                    <div><span>Gasto administrativo</span><strong id="detailShareAdministrative">S/ 0.00</strong></div>
                    <div>
                        <span>Cantidad acciones</span>
                        <strong id="detailShareQuantity">0</strong>
                    </div>
                    <div>
                        <span>Valor por accion</span>
                        <strong id="detailShareValue">S/ 0.00</strong>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-user"></i> Socio</h6>
                            <div class="member-detail-grid">
                                <div><span>Codigo socio</span><strong id="detailMemberCode">-</strong></div>
                                <div><span>DNI</span><strong id="detailMemberDni">-</strong></div>
                                <div class="span-2"><span>Nombre</span><strong id="detailMemberName">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-calendar-check"></i> Aporte</h6>
                            <div class="member-detail-grid">
                                <div><span>Fecha</span><strong id="detailShareDate">-</strong></div>
                                <div><span>Numero recibo</span><strong id="detailReceiptNumber">-</strong></div>
                                <div><span>Metodo pago</span><strong id="detailPaymentMethod">-</strong></div>
                                <div><span>Referencia</span><strong id="detailPaymentReference">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-lg-0">
                            <h6><i class="fas fa-paperclip"></i> Comprobante y recibo</h6>
                            <div id="detailVoucherPreview" class="share-voucher-preview mb-3"></div>
                            <div id="detailVoucherStatus" class="text-muted small mb-2">Sin comprobante registrado</div>
                            <div class="share-detail-links">
                                <a href="#" id="detailVoucherLink" class="btn btn-light border disabled" target="_blank" rel="noopener">
                                    <i class="fas fa-file-alt mr-1"></i> Sin comprobante
                                </a>
                                <a href="#" id="detailVoucherDownloadLink" class="btn btn-light border disabled" target="_blank" rel="noopener">
                                    <i class="fas fa-download mr-1"></i> Descargar
                                </a>
                                <a href="#" id="detailReceiptLink" class="btn btn-primary" target="_blank" rel="noopener">
                                    <i class="fas fa-receipt mr-1"></i> Ver recibo
                                </a>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-0">
                            <h6><i class="fas fa-history"></i> Auditoria</h6>
                            <div class="member-detail-grid">
                                <div><span>Registrado el</span><strong id="detailCreatedAt">-</strong></div>
                                <div><span>Usuario</span><strong id="detailCreatedBy">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card mt-3">
                            <h6><i class="fas fa-cash-register"></i> Movimientos de caja asociados</h6>
                            <div id="detailCashMovements"></div>
                            <div class="member-detail-grid">
                                <div><span>Codigo caja</span><strong id="detailCashMovementCode">-</strong></div>
                                <div><span>Estado caja</span><strong id="detailCashMovementStatus">-</strong></div>
                                <div><span>Saldo posterior</span><strong id="detailCashMovementBalance">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card mt-3 mb-0">
                            <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                            <div id="detailShareObservation" class="member-detail-note">-</div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="voucherPreviewModal" tabindex="-1" role="dialog" aria-labelledby="voucherPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <h5 class="modal-title" id="voucherPreviewModalLabel">Comprobante de pago</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center bg-light">
                <img id="voucherPreviewImage" src="" class="img-fluid rounded shadow-sm" alt="Comprobante de pago">
            </div>
            <div class="modal-footer">
                <a id="voucherPreviewDownloadLink" href="#" class="btn btn-sm btn-primary">
                    <i class="fas fa-download mr-1"></i> Descargar
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
