<div class="modal fade cash-detail-modal" id="loanSimulationDetailModal" tabindex="-1" role="dialog" aria-labelledby="loanSimulationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-calculator"></i></div>
                    <div>
                        <h5 class="modal-title mb-0" id="loanSimulationDetailModalLabel">Vista previa de simulacion</h5>
                        <small>Cronograma simulado por metodo aleman</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body member-modal-body">
                <div class="cash-detail-hero">
                    <div>
                        <span>Simulacion de prestamo</span>
                        <h4 id="detailLoanSimulationCode">-</h4>
                        <p id="detailLoanSimulationMember">-</p>
                    </div>
                    <div id="detailLoanSimulationStatus">-</div>
                </div>

                <div class="cash-detail-summary">
                    <div><span>Monto</span><strong id="detailLoanSimulationAmount">S/ 0.00</strong></div>
                    <div><span>Total interes</span><strong id="detailLoanSimulationInterest">S/ 0.00</strong></div>
                    <div><span>Total a pagar</span><strong id="detailLoanSimulationTotal">S/ 0.00</strong></div>
                </div>

                <section class="member-detail-card">
                    <h6><i class="fas fa-history"></i> Estado de la simulación</h6>
                    <div class="member-detail-grid">
                        <div><span>Estado actual</span><strong id="detailLoanSimulationAuditStatus">-</strong></div>
                        <div><span>Responsable del cambio</span><strong id="detailLoanSimulationEffectedBy">-</strong></div>
                        <div><span>Fecha y hora</span><strong id="detailLoanSimulationEffectedAt">-</strong></div>
                        <div class="span-2"><span>Motivo</span><strong id="detailLoanSimulationEffectReason">-</strong></div>
                    </div>
                </section>

                <div class="row">
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-id-card"></i> Datos principales</h6>
                            <div class="member-detail-grid">
                                <div><span>DNI</span><strong id="detailLoanSimulationDni">-</strong></div>
                                <div><span>Tasa</span><strong id="detailLoanSimulationRate">-</strong></div>
                                <div><span>Plazo</span><strong id="detailLoanSimulationTerm">-</strong></div>
                                <div><span>Metodo</span><strong id="detailLoanSimulationMethod">Aleman</strong></div>
                            </div>
                        </section>
                    </div>
                    <div class="col-12">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-user-check"></i> Evaluacion del prestamo</h6>
                            <div class="member-detail-grid">
                                <div><span>Tipo socio</span><strong id="detailLoanSimulationMemberType">-</strong></div>
                                <div><span>Aportes registrados</span><strong id="detailLoanSimulationContributionCount">-</strong></div>
                                <div><span>Total aportado</span><strong id="detailLoanSimulationContributions">-</strong></div>
                                <div><span>Limite sin garante</span><strong id="detailLoanSimulationLimit">-</strong></div>
                                <div><span>Requiere garante</span><strong id="detailLoanSimulationRequiresGuarantor">-</strong></div>
                                <div><span>Garante</span><strong id="detailLoanSimulationGuarantor">No aplica</strong></div>
                                <div class="span-2 d-none" id="detailLoanSimulationReasonBox"><span>Motivo</span><strong id="detailLoanSimulationReason">-</strong></div>
                            </div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-chart-line"></i> Resumen financiero</h6>
                            <div class="member-detail-grid">
                                <div><span>Capital fijo</span><strong id="detailLoanSimulationPrincipal">-</strong></div>
                                <div><span>Cantidad cuotas</span><strong id="detailLoanSimulationCount">-</strong></div>
                                <div><span>Fecha inicio</span><strong id="detailLoanSimulationStart">-</strong></div>
                                <div><span>Primera cuota</span><strong id="detailLoanSimulationFirstDate">-</strong></div>
                            </div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="member-detail-card" id="detailLoanSimulationConversionCard">
                            <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                            <div id="detailLoanSimulationObservation" class="member-detail-note">Sin observaciones</div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                <h6><i class="fas fa-exchange-alt"></i> Conversion a prestamo</h6>
                                <a href="#" id="detailLoanSimulationLoanLink" class="btn btn-light border btn-sm d-none">
                                    <i class="fas fa-file-invoice-dollar mr-1"></i> Ver prestamo
                                </a>
                            </div>
                            <div class="member-detail-grid">
                                <div><span>Prestamo generado</span><strong id="detailLoanSimulationLoanCode">-</strong></div>
                                <div><span>Fecha conversion</span><strong id="detailLoanSimulationConvertedAt">-</strong></div>
                                <div class="span-2"><span>Usuario que convirtio</span><strong id="detailLoanSimulationConvertedBy">-</strong></div>
                            </div>
                            <div class="alert alert-light border mb-0 d-none" id="detailLoanSimulationNotConverted">Esta simulacion aun no fue convertida a prestamo.</div>
                        </section>
                    </div>
                    <div class="col-12">
                        <section class="member-detail-card mb-0">
                            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                                <h6><i class="fas fa-table"></i> Cronograma simulado</h6>
                                <a href="#" id="detailLoanSimulationPrintLink" class="btn btn-light border btn-sm" target="_blank" rel="noopener">
                                    <i class="fas fa-print mr-1"></i> Imprimir
                                </a>
                            </div>
                            <div class="table-responsive loan-simulation-schedule-wrap">
                                <table class="table table-sm table-hover mb-0 text-center">
                                    <thead>
                                        <tr>
                                            <th>Cuota</th>
                                            <th>Fecha vencimiento</th>
                                            <th>Saldo inicial</th>
                                            <th>Capital</th>
                                            <th>Interes</th>
                                            <th>Monto cuota</th>
                                            <th>Saldo final</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailLoanSimulationSchedule"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
