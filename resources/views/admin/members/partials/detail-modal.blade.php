<div class="modal fade member-detail-modal" id="memberDetailModal" tabindex="-1" role="dialog" aria-labelledby="memberDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="memberDetailModalLabel">Detalle del socio</h5>
                        <small>Ficha consolidada de informacion administrativa</small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body member-modal-body">
                <div id="detailReentryNotice" class="alert alert-info d-none"></div>
                <div id="detailMinorWarning" class="alert alert-warning d-none"><span class="badge badge-warning mr-2">Menor de edad</span> Este socio tiene menos de 18 años. Puede estar registrado como socio, pero no puede ser aval/garante.</div>
                <div class="member-detail-profile">
                    <div class="member-detail-photo-wrap">
                        <img id="detailPhoto" src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg" alt="Foto del socio">
                    </div>
                    <div class="member-detail-main">
                        <span class="member-profile-kicker">Socio registrado</span>
                        <h4 id="detailFullName">-</h4>
                        <div class="member-profile-chips">
                            <span><i class="fas fa-hashtag"></i> <strong id="detailCode">-</strong></span>
                            <span><i class="fas fa-id-card"></i> DNI <strong id="detailDni">-</strong></span>
                            <span id="detailStatusBadge"></span>
                        </div>
                    </div>
                    <div class="member-profile-meta">
                        <div>
                            <span>Telefono</span>
                            <strong id="detailPhone">-</strong>
                        </div>
                        <div>
                            <span>Ingreso</span>
                            <strong id="detailAdmissionDate">-</strong>
                        </div>
                    </div>
                </div>

                <div class="row member-detail-sections">
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-user"></i> Datos personales</h6>
                            <div class="member-detail-grid">
                                <div><span>Nombres</span><strong id="detailFirstName">-</strong></div>
                                <div><span>Apellidos</span><strong id="detailLastName">-</strong></div>
                                <div><span>Edad</span><strong id="detailAge">-</strong></div>
                                <div><span>Nacimiento</span><strong id="detailBirthDate">-</strong></div>
                                <div class="span-2"><span>Direccion</span><strong id="detailAddress">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card" id="detailCreditHistoryCard">
                            <div class="member-detail-card-heading">
                                <div><h6><i class="fas fa-chart-line"></i> Historial crediticio</h6><p>Comportamiento calculado con la fecha real de pago y 5 días de tolerancia.</p></div>
                                <div><span class="badge badge-secondary px-3 py-2" id="detailCreditStatus">Sin calcular</span><button type="button" class="btn btn-light border btn-sm ml-1 d-none" id="detailCreditEventsButton"><i class="fas fa-list"></i> Ver detalle</button><button type="button" class="btn btn-light border btn-sm ml-1 d-none" id="detailCreditRecalculateButton"><i class="fas fa-sync-alt"></i> Recalcular</button></div>
                            </div>
                            <div class="cash-detail-summary">
                                <div><span>Puntaje</span><strong id="detailCreditScore">-</strong></div>
                                <div><span>Préstamos / pagados</span><strong id="detailCreditLoans">-</strong></div>
                                <div><span>Pagos puntuales</span><strong id="detailCreditOnTime">-</strong></div>
                                <div><span>Atrasos leves / graves</span><strong id="detailCreditLate">-</strong></div>
                                <div><span>Máximo atraso</span><strong id="detailCreditMaxLate">-</strong></div>
                                <div><span>Deuda vencida activa</span><strong id="detailCreditOverdue">-</strong></div>
                            </div>
                            <div class="alert alert-light border mt-3 mb-0"><strong>Recomendación:</strong> <span id="detailCreditRecommendation">No aplica</span><small class="d-block text-muted mt-1" id="detailCreditCalculatedAt"></small></div>
                            <div class="table-responsive mt-3 d-none" id="detailCreditEventsWrap"><table class="table table-sm table-hover text-center mb-0"><thead><tr><th>Evento</th><th>Préstamo / cuota</th><th>Vencimiento</th><th>Fecha pago</th><th>Registro / usuario</th><th>Días atraso</th><th>Monto</th></tr></thead><tbody id="detailCreditEventsRows"></tbody></table></div>
                        </section>
                    </div>

                    <div class="col-12 d-none" id="detailAccountClosureCard">
                        <section class="member-detail-card border-danger">
                            <div class="member-detail-card-heading">
                                <div><h6><i class="fas fa-user-slash text-danger"></i> Estado de cuenta: Retirado</h6><p>Cierre confirmado e información histórica de devolución.</p></div>
                                <div class="btn-group btn-group-sm"><a id="detailAccountClosureConstancy" class="btn btn-light border" target="_blank"><i class="fas fa-file-pdf"></i> Ver constancia</a><a id="detailAccountClosureReceipt" class="btn btn-light border d-none" target="_blank"><i class="fas fa-receipt"></i> Ver recibo</a></div>
                            </div>
                            <div class="member-detail-grid">
                                <div><span>Estado</span><strong class="text-danger">Retirado</strong></div>
                                <div><span>Fecha de retiro</span><strong id="detailAccountClosureDate">-</strong></div>
                                <div><span>Código de cierre</span><strong id="detailAccountClosureCode">-</strong></div>
                                <div><span>Saldo devuelto</span><strong id="detailAccountClosureBalance">-</strong></div>
                                <div><span>Forma de devolución</span><strong id="detailAccountClosureMethod">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-hands-helping"></i> Beneficiarios en caso de fallecimiento</h6>
                            <div id="detailBeneficiaries" class="table-responsive"></div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-briefcase"></i> Informacion socio</h6>
                            <div class="member-detail-grid">
                                <div><span>Tiempo como socio</span><strong id="detailMembershipTime">-</strong></div>
                                <div><span>Tipo seleccionado</span><strong id="detailMemberTypeSelected">-</strong></div>
                                <div><span>Tipo calculado</span><strong id="detailMemberTypeCalculated">-</strong></div>
                                <div><span>Estado</span><strong id="detailMemberStatus">-</strong></div>
                                <div><span>Retiro</span><strong id="detailRetirementDate">-</strong></div>
                                <div><span>Trabajo actual</span><strong id="detailCurrentJob">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-heart"></i> Estado civil y familia</h6>
                            <div class="member-detail-grid mb-2">
                                <div><span>Estado civil</span><strong id="detailCivilStatus">-</strong></div>
                                <div><span>Pareja</span><strong id="detailSpouse">-</strong></div>
                            </div>
                            <div class="member-detail-subtitle">Hijos</div>
                            <div id="detailChildren" class="member-detail-children">-</div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-user-shield"></i> Aval / garante</h6><p class="small text-muted">Solo socios vigentes pueden ser garantes.</p>
                            <div id="detailGuarantorEmpty" class="member-detail-empty">No se registro aval o garante.</div>
                            <div id="detailGuarantorData" class="member-detail-grid d-none">
                                <div><span>Tipo</span><strong id="detailGuarantorType">-</strong></div>
                                <div><span>Codigo</span><strong id="detailGuarantorCode">-</strong></div>
                                <div><span>DNI</span><strong id="detailGuarantorDni">-</strong></div>
                                <div><span>Estado</span><strong id="detailGuarantorStatus">-</strong></div>
                                <div class="span-2"><span>Nombre</span><strong id="detailGuarantorName">-</strong></div>
                                <div><span>Telefono</span><strong id="detailGuarantorPhone">-</strong></div>
                                <div><span>Direccion</span><strong id="detailGuarantorAddress">-</strong></div>
                                <div class="span-2"><span>Total aportado</span><strong id="detailGuarantorContributions">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-chart-line"></i> Resumen financiero</h6>
                            <div class="member-detail-grid">
                                <div><span>Total aportado</span><strong id="detailFinancialAmount">-</strong></div>
                                <div><span>Acciones</span><strong id="detailFinancialShares">-</strong></div>
                                <div><span>Aportes registrados</span><strong id="detailFinancialContributionCount">-</strong></div>
                                <div><span>Prestamos activos</span><strong id="detailFinancialActiveLoans">-</strong></div>
                                <div><span>Deuda pendiente</span><strong id="detailFinancialDebt">-</strong></div>
                                <div class="span-2"><span>Utilidades pendientes</span><strong id="detailFinancialUtilities">-</strong></div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12">
                        <section class="member-detail-card member-enrollment-detail" id="detailEnrollmentCard">
                            <div class="member-detail-card-heading">
                                <div><h6><i class="fas fa-file-invoice-dollar"></i> Inscripcion</h6><p>Pago de inscripcion y comprobantes relacionados.</p></div>
                                <a id="detailEnrollmentReceipt" class="btn btn-light border btn-sm d-none" target="_blank"><i class="fas fa-receipt"></i> Ver recibo</a>
                            </div>
                            <div id="detailEnrollmentNotApplicable" class="member-detail-empty d-none">No aplica inscripcion por ser socio antiguo.</div>
                            <div id="detailEnrollmentMissing" class="member-detail-empty d-none">Inscripcion pendiente.</div>
                            <div id="detailEnrollmentData" class="d-none">
                                <div class="member-enrollment-detail-layout">
                                    <div>
                                        <div class="member-detail-grid">
                                            <div><span>Codigo</span><strong id="detailEnrollmentCode">-</strong></div>
                                            <div><span>Fecha</span><strong id="detailEnrollmentDate">-</strong></div>
                                            <div><span>Monto</span><strong id="detailEnrollmentAmount">-</strong></div>
                                            <div><span>Metodo</span><strong id="detailEnrollmentMethod">-</strong></div>
                                            <div><span>Referencia</span><strong id="detailEnrollmentReference">-</strong></div>
                                            <div><span>Estado</span><strong id="detailEnrollmentStatus">-</strong></div>
                                            <div><span>Movimiento Caja</span><strong id="detailEnrollmentCash">-</strong></div>
                                            <div><span>Saldo posterior</span><strong id="detailEnrollmentCashBalance">-</strong></div>
                                            <div class="span-2"><span>Observacion</span><strong id="detailEnrollmentObservation">-</strong></div>
                                        </div>
                                    </div>
                                    <div id="detailEnrollmentVoucher" class="member-enrollment-detail-voucher"></div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-lg-0">
                            <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                            <div id="detailObservation" class="member-detail-note">-</div>
                        </section>
                    </div>

                    <div class="col-lg-6">
                        <section class="member-detail-card mb-0">
                            <h6><i class="fas fa-history"></i> Auditoria</h6>
                            <div class="member-detail-grid">
                                <div><span>Registrado el</span><strong id="detailCreatedAt">-</strong></div>
                                <div><span>Usuario que registro</span><strong id="detailCreatedBy">-</strong></div>
                                <div><span>Ultima edicion</span><strong id="detailUpdatedAt">-</strong></div>
                                <div><span>Ultima edicion por</span><strong id="detailUpdatedBy">-</strong></div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
