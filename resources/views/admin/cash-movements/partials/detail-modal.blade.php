<div class="modal fade cash-detail-modal" id="cashMovementDetailModal" tabindex="-1" role="dialog" aria-labelledby="cashMovementDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <h5 class="modal-title mb-0" id="cashMovementDetailModalLabel">Detalle del movimiento</h5>
                        <small>Ficha de ingreso o egreso de caja</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body member-modal-body">
                <div class="cash-detail-hero">
                    <div>
                        <span>Movimiento de caja</span>
                        <h4 id="detailCashCode">-</h4>
                        <p id="detailCashConcept">-</p>
                    </div>
                    <div id="detailCashStatus">-</div>
                </div>

                <div class="cash-detail-summary">
                    <div><span>Tipo</span><strong id="detailCashType">-</strong></div>
                    <div><span>Monto</span><strong id="detailCashAmount">S/ 0.00</strong></div>
                    <div><span>Saldo posterior</span><strong id="detailCashBalanceAfter">S/ 0.00</strong></div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-calendar-check"></i> Movimiento</h6>
                            <div class="member-detail-grid">
                                <div><span>Fecha</span><strong id="detailCashDate">-</strong></div>
                                <div><span>Categoria</span><strong id="detailCashCategory">-</strong></div>
                                <div><span>Metodo pago</span><strong id="detailCashPaymentMethod">-</strong></div>
                                <div><span>Referencia</span><strong id="detailCashReference">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-balance-scale"></i> Saldos</h6>
                            <div class="member-detail-grid">
                                <div><span>Saldo anterior</span><strong id="detailCashBalanceBefore">-</strong></div>
                                <div><span>Saldo posterior</span><strong id="detailCashBalanceAfterCard">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-lg-0">
                            <h6><i class="fas fa-paperclip"></i> Comprobante</h6>
                            <div class="share-detail-links">
                                <a href="#" id="detailCashVoucherLink" class="btn btn-light border disabled" target="_blank" rel="noopener">
                                    <i class="fas fa-file-alt mr-1"></i> Sin comprobante registrado
                                </a>
                                <span id="detailCashVoucherText" class="text-muted small d-none">Sin comprobante registrado</span>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-0">
                            <h6><i class="fas fa-history"></i> Auditoria</h6>
                            <div class="member-detail-grid">
                                <div><span>Registrado el</span><strong id="detailCashCreatedAt">-</strong></div>
                                <div><span>Usuario</span><strong id="detailCashCreatedBy">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card mt-3 d-none" id="detailCashPaymentBreakdown">
                            <h6><i class="fas fa-calculator"></i> Desglose del cobro relacionado</h6>
                            <div class="cash-detail-summary">
                                <div><span>Capital cobrado</span><strong id="detailCashPaymentCapital">S/ 0.00</strong></div>
                                <div><span>Interés cobrado</span><strong id="detailCashPaymentInterest">S/ 0.00</strong></div>
                                <div><span class="text-warning">Mora cobrada</span><strong class="text-warning" id="detailCashPaymentLateFee">S/ 0.00</strong></div>
                                <div><span>Mora exonerada</span><strong id="detailCashPaymentLateFeeWaived">S/ 0.00</strong></div>
                                <div class="bg-light"><span>Total ingresado a Caja</span><strong class="text-primary" id="detailCashPaymentTotal">S/ 0.00</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card mt-3">
                            <h6><i class="fas fa-project-diagram"></i> Origen del movimiento</h6>
                            <div class="member-detail-grid">
                                <div><span>Tipo de origen</span><strong id="detailCashOriginType">-</strong></div>
                                <div><span>Codigo origen</span><strong id="detailCashOriginCode">-</strong></div>
                                <div><span>Socio relacionado</span><strong id="detailCashOriginMember">-</strong></div>
                                <div><span>Prestamo</span><strong id="detailCashOriginLoan">-</strong></div>
                                <div><span>Modulo origen</span><strong id="detailCashOriginModule">-</strong></div>
                                <div><span>Relacion tecnica</span><strong id="detailCashOriginTechnical">-</strong></div>
                            </div>
                            <div class="mt-3">
                                <a href="#" id="detailCashOriginLink" class="btn btn-light border disabled" target="_blank" rel="noopener">
                                    <i class="fas fa-external-link-alt mr-1"></i> Ver registro origen
                                </a>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card mt-3 mb-0">
                            <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                            <div id="detailCashObservation" class="member-detail-note">-</div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
