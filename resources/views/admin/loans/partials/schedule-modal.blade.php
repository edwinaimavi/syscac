<div class="modal fade cash-detail-modal" id="loanScheduleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header member-modal-header"><div class="member-modal-titlebar"><div class="member-modal-icon"><i class="fas fa-calendar-alt"></i></div><div><h5 class="modal-title mb-0">Cronograma de cuotas</h5><small>Cuotas reales del prestamo</small></div></div><button type="button" class="close ml-3" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body member-modal-body">
            <div class="cash-detail-hero"><div><span>Prestamo</span><h4 id="scheduleLoanCode">-</h4><p id="scheduleLoanMember">-</p></div><div id="scheduleLoanStatus">-</div></div>
            <div class="text-right mb-3">
                <a href="#" target="_blank" class="btn btn-light border btn-sm disabled" id="scheduleLoanPrintLink"><i class="fas fa-print mr-1"></i> Imprimir</a>
                <a href="#" target="_blank" class="btn btn-light border btn-sm disabled" id="scheduleLoanPdfLink"><i class="fas fa-file-pdf mr-1"></i> PDF</a>
            </div>
            <section class="member-detail-card mb-0">
                <div class="table-responsive loan-simulation-schedule-wrap"><table class="table table-sm table-hover mb-0 text-center"><thead><tr><th>Cuota</th><th>Vencimiento</th><th>Saldo inicial</th><th>Capital</th><th>Interes</th><th>Interes exonerado</th><th>Monto cuota</th><th>Pagado</th><th>Pendiente</th><th>Saldo final</th><th>Fecha pago</th><th>Días atraso</th><th>Estado crediticio</th></tr></thead><tbody id="loanScheduleRows"></tbody></table></div>
            </section>
        </div>
    </div></div>
</div>
