<div class="modal fade share-modal loan-modal" id="loanModal" tabindex="-1" role="dialog" aria-labelledby="loanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-hand-holding-usd"></i></div><div><h5 class="modal-title mb-0" id="loanModalLabel">Nuevo prestamo</h5><small>Registro real con cronograma aleman</small></div></div>
                <button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="loanForm" autocomplete="off">@csrf
                <div class="modal-body member-modal-body">
                    <div id="loan-error-messages" class="alert alert-danger d-none"></div>
                    <div class="row">
                        <div class="col-lg-4">
                            <aside class="share-side-card">
                                <span class="share-side-kicker">Resumen del prestamo</span>
                                <strong id="loanSideCode">{{ $nextCode }}</strong>
                                <div class="share-side-total"><span>Total a pagar</span><strong id="loanSideTotal">S/ 0.00</strong></div>
                                <div class="share-side-metrics"><div><span>Capital fijo</span><strong id="loanSidePrincipal">S/ 0.00</strong></div><div><span>Interes</span><strong id="loanSideInterest">S/ 0.00</strong></div></div>
                                <p>El desembolso sera el unico momento en que se generara egreso en Caja.</p>
                            </aside>
                        </div>
                        <div class="col-lg-8">
                            <section class="member-section">
                                <div class="member-section-header"><div><h6><i class="fas fa-file-signature"></i> Informacion del prestamo</h6><p>Codigo, socio, simulacion opcional y estado.</p></div></div>
                                <div class="form-row">
                                    <div class="form-group col-md-4"><label>Codigo</label><input type="text" class="form-control form-control-sm" name="loan_number" value="{{ $nextCode }}" readonly></div>
                                    <div class="form-group col-md-4"><label>Estado</label><select class="form-control form-control-sm" name="status"><option value="pendiente">Pendiente</option><option value="aprobado">Aprobado</option></select></div>
                                    <div class="form-group col-md-4"><label>Simulacion</label><select class="form-control form-control-sm" name="loan_simulation_id"><option value="">Sin simulacion</option>@foreach($simulations as $simulation)<option value="{{ $simulation->id }}" data-member-id="{{ $simulation->member_id }}" data-amount="{{ $simulation->amount }}" data-rate="{{ $simulation->interest_rate }}" data-term="{{ $simulation->term_months }}" data-start="{{ optional($simulation->start_date)->format('Y-m-d') }}" data-first="{{ optional($simulation->first_payment_date)->format('Y-m-d') }}">{{ $simulation->code }} - {{ $simulation->member?->full_name }}</option>@endforeach</select></div>
                                </div>
                                <div class="form-group mb-0"><label>Socio vigente <span class="text-danger">*</span></label><select class="form-control form-control-sm" name="member_id" required><option value="">Seleccione socio vigente</option>@foreach($members as $member)<option value="{{ $member->id }}" data-minor="{{ $member->isMinor() ? '1' : '0' }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select></div>
                                <div class="alert alert-warning py-2 mt-2 mb-0 d-none" id="loanMinorWarning"><i class="fas fa-exclamation-triangle mr-1"></i> Advertencia: este socio es menor de edad.</div>
                                <div class="form-group mt-3 mb-0"><label>Garante socio</label><select class="form-control form-control-sm" name="guarantor_member_id"><option value="">Sin garante</option>@foreach($guarantors as $member)<option value="{{ $member->id }}">{{ $member->code }} - {{ $member->dni }} - {{ $member->full_name }}</option>@endforeach</select><small>{{ $guarantors->isEmpty() ? 'No hay socios vigentes aptos para ser aval/garante.' : 'Solo se muestran socios aptos para ser aval/garante.' }}</small></div>
                                <div class="loan-eligibility-card is-neutral mt-3 mb-0" id="loanEligibility">
                                    <div class="loan-eligibility-empty"><i class="fas fa-user-check"></i><span>Seleccione un socio y complete los datos para calcular la evaluacion.</span></div>
                                </div>
                                <div class="alert alert-info mt-3 mb-0 d-none" id="loanPendingSimulationsCard">
                                    <div class="font-weight-bold mb-1"><i class="fas fa-info-circle mr-1"></i> Simulaciones pendientes</div>
                                    <p class="small mb-2">Este socio tiene una o más simulaciones pendientes. Puede tomar una simulación para convertirla en préstamo o dejarla sin efecto y registrar un préstamo directo.</p>
                                    <div id="loanPendingSimulationsList"></div>
                                </div>
                            </section>
                            <section class="member-section">
                                <div class="member-section-header"><div><h6><i class="fas fa-calculator"></i> Condiciones</h6><p>Monto, tasa mensual, plazo y fechas.</p></div></div>
                                <div class="form-row">
                                    <div class="form-group col-md-4"><label>Monto solicitado <span class="text-danger">*</span></label><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">S/</span></div><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="requested_amount" required></div></div>
                                    <div class="form-group col-md-4"><label>Monto aprobado <span class="text-danger">*</span></label><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">S/</span></div><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="approved_amount" required></div></div>
                                    <div class="form-group col-md-4"><label>Tasa mensual <span class="text-danger">*</span></label><div class="input-group input-group-sm"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="interest_rate" required><div class="input-group-append"><span class="input-group-text">%</span></div></div></div>
                                    <div class="form-group col-md-4"><label>Plazo meses <span class="text-danger">*</span></label><input type="number" step="1" min="1" class="form-control form-control-sm" name="term_months" required></div>
                                    <div class="form-group col-md-4"><label>Fecha inicio <span class="text-danger">*</span></label><input type="date" class="form-control form-control-sm" name="start_date" required></div>
                                    <div class="form-group col-md-4"><label>Primera cuota</label><input type="date" class="form-control form-control-sm" name="first_payment_date"></div>
                                    <input type="hidden" name="interest_type" value="mensual"><input type="hidden" name="payment_frequency" value="mensual"><input type="hidden" name="amortization_method" value="aleman">
                                </div>
                            </section>
                            <section class="member-section">
                                <div class="member-section-header"><div><h6><i class="fas fa-chart-line"></i> Resumen calculado</h6><p>Vista previa del cronograma real.</p></div><button type="button" class="btn btn-light border btn-sm" id="btnCalculateLoan"><i class="fas fa-sync-alt mr-1"></i> Calcular</button></div>
                                <div class="loan-simulation-mini-summary">
                                    <div><span>Capital fijo</span><strong id="loanPreviewPrincipal">S/ 0.00</strong></div>
                                    <div><span>Total interes</span><strong id="loanPreviewInterest">S/ 0.00</strong></div>
                                    <div><span>Total a pagar</span><strong id="loanPreviewTotal">S/ 0.00</strong></div>
                                    <div><span>Primera cuota</span><strong id="loanPreviewFirst">S/ 0.00</strong></div>
                                    <div><span>Ultima cuota</span><strong id="loanPreviewLast">S/ 0.00</strong></div>
                                </div>
                            </section>
                            <section class="member-section mb-0">
                                <div class="member-section-header"><div><h6><i class="fas fa-clipboard-list"></i> Motivo y observacion</h6><p>Informacion administrativa.</p></div></div>
                                <div class="form-group"><label>Motivo</label><input type="text" class="form-control form-control-sm" name="purpose" maxlength="255"></div>
                                <textarea class="form-control form-control-sm" name="observation" rows="3" placeholder="Observacion general"></textarea>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cancelar</button><button type="submit" class="btn btn-primary" id="loanSaveButton"><i class="fas fa-save mr-1"></i> <span id="loanSaveText">Guardar prestamo</span></button></div>
            </form>
        </div>
    </div>
</div>
