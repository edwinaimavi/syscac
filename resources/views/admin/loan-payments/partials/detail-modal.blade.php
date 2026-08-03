<div class="modal fade cash-detail-modal" id="loanPaymentDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-receipt"></i></div><div><h5 class="modal-title mb-0">Detalle del cobro</h5><small>Desglose financiero y trazabilidad del préstamo</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body member-modal-body">
            <div class="cash-detail-hero"><div><span>Cobro</span><h4 id="detailPaymentCode">-</h4><p id="detailPaymentMember">-</p></div><div><span id="detailPaymentHistorical" class="badge badge-light">Normal</span> <span id="detailPaymentStatus">-</span></div></div>

            <section class="member-detail-card">
                <h6><i class="fas fa-chart-pie"></i> Detalle financiero del cobro</h6>
                <div class="cash-detail-summary">
                    <div class="border-primary"><span class="text-primary">Capital pagado</span><strong id="detailPaymentCapital" class="text-primary">S/ 0.00</strong></div>
                    <div><span>Interés pagado</span><strong id="detailPaymentInterest">S/ 0.00</strong></div>
                    <div class="border-warning"><span class="text-warning">Mora pagada</span><strong id="detailPaymentLateFee" class="text-warning">S/ 0.00</strong></div>
                    <div><span>Mora exonerada</span><strong id="detailPaymentLateFeeWaived">S/ 0.00</strong></div>
                    <div><span>Interés futuro exonerado</span><strong id="detailPaymentExonerated">S/ 0.00</strong></div>
                    <div class="bg-light"><span>Total pagado</span><strong id="detailPaymentAmount" class="text-primary h5 mb-0">S/ 0.00</strong></div>
                    <div><span>Saldo anterior préstamo</span><strong id="detailPaymentPreviousLoanBalance">S/ 0.00</strong></div>
                    <div><span>Saldo posterior préstamo</span><strong id="detailPaymentNewLoanBalance">S/ 0.00</strong></div>
                </div>
                <div class="alert alert-light border mt-3 mb-0 py-2"><i class="fas fa-info-circle text-primary mr-1"></i> Total cobrado = capital + interés + mora. El saldo del préstamo suma capital e interés pendientes; la mora no integra el saldo futuro.</div>
            </section>

            <div class="row">
                <div class="col-lg-6"><section class="member-detail-card"><h6><i class="fas fa-info-circle"></i> Información del cobro</h6><div class="member-detail-grid"><div><span>Préstamo</span><strong id="detailPaymentLoan">-</strong></div><div><span>Fecha real de pago</span><strong id="detailPaymentDate">-</strong></div><div><span>Fecha de registro</span><strong id="detailPaymentRegisteredAt">-</strong></div><div><span>Tipo de cobro</span><strong id="detailPaymentType">-</strong></div><div><span>Método de pago</span><strong id="detailPaymentMethod">-</strong></div><div><span>Referencia</span><strong id="detailPaymentReference">-</strong></div><div><span>Usuario que registró</span><strong id="detailPaymentUser">-</strong></div><div><span>Estado</span><strong id="detailPaymentStatusText">-</strong></div><div><span>Afectó Caja</span><strong id="detailPaymentAffectsCash">-</strong></div><div><span>Afectó utilidad</span><strong id="detailPaymentAffectsProfit">-</strong></div><div><span>Tratamiento utilidad</span><strong id="detailPaymentProfitTreatment">-</strong></div><div><span>Afectó historial</span><strong id="detailPaymentAffectsCredit">-</strong></div><div><span>Recibo generado</span><strong id="detailPaymentReceipt">-</strong></div></div></section></div>
                <div class="col-lg-6"><section class="member-detail-card"><h6><i class="fas fa-user"></i> Socio y documentos</h6><div class="member-detail-grid mb-3"><div><span>DNI</span><strong id="detailPaymentDni">-</strong></div><div><span>Código socio</span><strong id="detailPaymentMemberCode">-</strong></div></div><div class="btn-group btn-group-sm mb-3"><a href="#" target="_blank" class="btn btn-light border disabled" id="detailPaymentReceiptLink"><i class="fas fa-eye mr-1"></i> Ver recibo</a><a href="#" target="_blank" class="btn btn-light border disabled" id="detailPaymentPrintLink"><i class="fas fa-print mr-1"></i> Imprimir</a><a href="#" target="_blank" class="btn btn-light border disabled" id="detailPaymentReceiptPdfLink"><i class="fas fa-download mr-1"></i> Descargar PDF</a><a href="#" target="_blank" class="btn btn-light border disabled" id="detailPaymentVoucher"><i class="fas fa-paperclip mr-1"></i> Comprobante</a></div><div class="member-detail-note"><strong>Observación</strong><br><span id="detailPaymentObservation">-</span></div></section></div>
                <div class="col-12"><section class="member-detail-card mb-0"><h6><i class="fas fa-list"></i> Detalle de cuotas cobradas</h6><div class="table-responsive"><table class="table table-sm table-hover text-center mb-0"><thead><tr><th>Cuota</th><th>Vencimiento</th><th>Capital</th><th>Interés</th><th>Días mora</th><th>Mora</th><th>Mora exonerada</th><th>Total cobrado</th><th>Estado</th></tr></thead><tbody id="detailPaymentRows"></tbody></table></div></section></div>
            </div>
        </div>
        <div class="modal-footer member-modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cerrar</button></div>
    </div></div>
</div>
