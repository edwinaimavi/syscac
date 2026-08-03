<div class="modal fade member-detail-modal" id="guarantorDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header member-modal-header">
                <div class="member-modal-titlebar">
                    <div class="member-modal-icon"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <h5 class="modal-title mb-0">Detalle del aval</h5>
                        <small>Ficha consolidada del aval o garante</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body member-modal-body">
                <div class="member-detail-profile">
                    <div class="member-detail-photo-wrap">
                        <img id="detailGuarantorPhoto" src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg" alt="Foto del aval">
                    </div>
                    <div class="member-detail-main">
                        <span class="member-profile-kicker">Aval / garante</span>
                        <h4 id="detailGuarantorFullName">-</h4>
                        <div class="member-profile-chips">
                            <span><i class="fas fa-hashtag"></i> <strong id="detailGuarantorCode">-</strong></span>
                            <span><i class="fas fa-id-card"></i> DNI <strong id="detailGuarantorDni">-</strong></span>
                            <span id="detailGuarantorStatusBadge"></span>
                        </div>
                    </div>
                    <div class="member-profile-meta">
                        <div><span>Tipo</span><strong id="detailGuarantorType">-</strong></div>
                        <div><span>Telefono</span><strong id="detailGuarantorPhone">-</strong></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-address-card"></i> Datos del aval</h6>
                            <div class="member-detail-grid">
                                <div class="span-2"><span>Direccion</span><strong id="detailGuarantorAddress">-</strong></div>
                                <div><span>Ocupacion</span><strong id="detailGuarantorOccupation">-</strong></div>
                                <div><span>Relacion</span><strong id="detailGuarantorRelationship">-</strong></div>
                            </div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="member-detail-card">
                            <h6><i class="fas fa-users"></i> Socios a los que avala</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead><tr><th>Codigo</th><th>Socio</th><th>DNI</th><th>Estado</th></tr></thead>
                                    <tbody id="detailGuarantorMembers"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="member-detail-card mb-lg-0">
                            <h6><i class="fas fa-clipboard-list"></i> Observacion</h6>
                            <div id="detailGuarantorObservation" class="member-detail-note">-</div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="member-detail-card mb-0">
                            <h6><i class="fas fa-history"></i> Auditoria</h6>
                            <div class="member-detail-grid">
                                <div><span>Creado el</span><strong id="detailGuarantorCreatedAt">-</strong></div>
                                <div><span>Actualizado el</span><strong id="detailGuarantorUpdatedAt">-</strong></div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
