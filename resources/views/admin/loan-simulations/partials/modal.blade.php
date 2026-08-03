<div class="modal fade share-modal loan-simulation-modal" id="loanSimulationModal" tabindex="-1" role="dialog" aria-labelledby="loanSimulationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-calculator"></i></div>
                    <div>
                        <h5 class="modal-title mb-0" id="loanSimulationModalLabel">Nuevo simulador de prestamo</h5>
                        <small>Cronograma simulado por metodo aleman</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="loanSimulationForm" autocomplete="off">
                @csrf

                <div class="modal-body member-modal-body">
                    <div id="loan-simulation-error-messages" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-4">
                            <aside class="share-side-card">
                                <span class="share-side-kicker">Resumen de simulacion</span>
                                <strong id="loanSimulationSideCode">{{ $nextCode }}</strong>
                                <div class="share-side-total">
                                    <span>Total a pagar</span>
                                    <strong id="loanSimulationSideTotal">S/ 0.00</strong>
                                </div>
                                <div class="share-side-metrics">
                                    <div><span>Capital fijo</span><strong id="loanSimulationSidePrincipal">S/ 0.00</strong></div>
                                    <div><span>Total interes</span><strong id="loanSimulationSideInterest">S/ 0.00</strong></div>
                                </div>
                                <p>El cronograma se recalcula en el backend antes de guardar. Esta simulacion no mueve caja ni crea deuda real.</p>
                            </aside>
                        </div>

                        <div class="col-lg-8">
                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-file-signature"></i> Datos de simulacion</h6>
                                        <p>Codigo automatico, fecha, socio vigente y estado.</p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Codigo</label>
                                        <input type="text" class="form-control form-control-sm" name="code" value="{{ $nextCode }}" readonly>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha de simulacion <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="simulation_date" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Estado <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="status" required>
                                            <option value="simulada">Simulada</option>
                                            <option value="anulada">Anulada</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label>Socio vigente <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" name="member_id" required>
                                        <option value="">Seleccione socio vigente</option>
                                        @foreach ($members as $member)
                                            <option value="{{ $member->id }}" data-minor="{{ $member->isMinor() ? '1' : '0' }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="alert alert-warning py-2 mt-2 mb-0 d-none" id="loanSimulationMinorWarning"><i class="fas fa-exclamation-triangle mr-1"></i> Advertencia: este socio es menor de edad.</div>
                                <div class="loan-simulation-guarantor mt-3" id="loanSimulationGuarantorGroup">
                                    <label>Garante socio <span id="loanSimulationGuarantorRequired" class="text-danger d-none">*</span></label>
                                    <select class="form-control form-control-sm" name="guarantor_member_id">
                                        <option value="">Sin garante</option>
                                        @foreach ($guarantors as $member)
                                            <option value="{{ $member->id }}" data-contributions="{{ (float) ($member->registered_contributions ?? 0) }}">{{ $member->code }} - {{ $member->full_name }} - Aportes: S/ {{ number_format((float) ($member->registered_contributions ?? 0), 2) }}</option>
                                        @endforeach
                                    </select>
                                    @if($guarantors->isEmpty())<small class="text-muted d-block mt-1">No hay socios vigentes aptos para ser aval/garante.</small>@endif
                                    <small id="loanSimulationGuarantorHelp">Opcional mientras el monto no supere el limite permitido.</small>
                                </div>
                                <div class="loan-simulation-eligibility d-none mt-3" id="loanSimulationEligibility"></div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-hand-holding-usd"></i> Condiciones del prestamo</h6>
                                        <p>Monto, tasa mensual, plazo y fechas del cronograma.</p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Monto del prestamo <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">S/</span></div>
                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" placeholder="0.00" required>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Tasa mensual <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="interest_rate" placeholder="0.00" required>
                                            <div class="input-group-append"><span class="input-group-text">%</span></div>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Plazo en meses <span class="text-danger">*</span></label>
                                        <input type="number" step="1" min="1" class="form-control form-control-sm" name="term_months" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha de inicio <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm" name="start_date" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Fecha primera cuota</label>
                                        <input type="date" class="form-control form-control-sm" name="first_payment_date">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Metodo amortizacion <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="amortization_method" required>
                                            <option value="aleman">Aleman</option>
                                        </select>
                                        <input type="hidden" name="interest_type" value="mensual">
                                    </div>
                                </div>
                            </section>

                            <section class="member-section">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-chart-line"></i> Resumen calculado</h6>
                                        <p>Vista rapida antes de guardar la simulacion.</p>
                                    </div>
                                    <button type="button" class="btn btn-light border btn-sm" id="btnCalculateLoanSimulation">
                                        <i class="fas fa-sync-alt mr-1"></i> Calcular
                                    </button>
                                </div>
                                <div class="loan-simulation-mini-summary">
                                    <div><span>Capital fijo</span><strong id="loanSimulationPreviewPrincipal">S/ 0.00</strong></div>
                                    <div><span>Total interes</span><strong id="loanSimulationPreviewInterest">S/ 0.00</strong></div>
                                    <div><span>Total a pagar</span><strong id="loanSimulationPreviewTotal">S/ 0.00</strong></div>
                                    <div><span>Primera cuota</span><strong id="loanSimulationPreviewFirst">S/ 0.00</strong></div>
                                    <div><span>Ultima cuota</span><strong id="loanSimulationPreviewLast">S/ 0.00</strong></div>
                                </div>
                            </section>

                            <section class="member-section mb-0">
                                <div class="member-section-header">
                                    <div>
                                        <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                                        <p>Nota administrativa opcional para esta simulacion.</p>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm" name="observation" rows="3" placeholder="Observacion general"></textarea>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="modal-footer member-modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="loanSimulationSaveButton">
                        <i class="fas fa-save mr-1"></i> <span id="loanSimulationSaveText">Generar simulacion</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
