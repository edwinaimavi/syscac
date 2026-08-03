<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\ActivityMovementController;
use App\Http\Controllers\Admin\CashMovementController;
use App\Http\Controllers\Admin\GuarantorController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanInstallmentController;
use App\Http\Controllers\Admin\LoanPaymentController;
use App\Http\Controllers\Admin\LoanPaymentDetailController;
use App\Http\Controllers\Admin\LoanRefinancingController;
use App\Http\Controllers\Admin\LoanSimulationController;
use App\Http\Controllers\Admin\MemberAccountClosureController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberEnrollmentController;
use App\Http\Controllers\Admin\MemberRelativeController;
use App\Http\Controllers\Admin\MemberShareController;
use App\Http\Controllers\Admin\ProfitDistributionController;
use App\Http\Controllers\Admin\ProfitSourceController;
use App\Http\Controllers\Admin\ProfitDistributionDetailController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SolidarityMovementController;
use App\Http\Controllers\Admin\AdministrativeFundController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\LateFeeSettingController;
use Illuminate\Support\Facades\Route;

Route::get('users/list', [UserController::class, 'list'])->name('users.list');
Route::resource('users', UserController::class)->except(['create', 'show']);

Route::get('roles/list', [RoleController::class, 'list'])->name('roles.list');
Route::get('roles/{role}/permissions', [RoleController::class, 'getPermissions'])->name('roles.permissions');
Route::resource('roles', RoleController::class)->except(['create', 'show']);
Route::get('mora/list', [LateFeeSettingController::class, 'list'])->name('mora.list');
Route::get('mora/summary', [LateFeeSettingController::class, 'summary'])->name('mora.summary');
Route::post('mora/{mora}/activar', [LateFeeSettingController::class, 'activate'])->name('mora.activate');
Route::get('mora/{mora}/edit', [LateFeeSettingController::class, 'edit'])->name('mora.edit');
Route::get('mora-reporte', [LateFeeSettingController::class,'report'])->name('mora.report');
Route::resource('mora', LateFeeSettingController::class)->except(['create', 'edit'])->parameters(['mora' => 'mora']);

Route::get('socios/list', [MemberController::class, 'list'])->name('socios.list');
Route::get('socios/next-code', [MemberController::class, 'nextCode'])->name('socios.next-code');
Route::get('socios/consultar-dni/{dni}', [MemberController::class, 'consultarDni'])->name('socios.consultar-dni');
Route::get('socios/verificar-dni/{dni}', [MemberController::class, 'verifyDni'])->name('socios.verificar-dni');
Route::get('socios/buscar-avales', [GuarantorController::class, 'select2'])->name('socios.buscar-avales');
Route::get('socios/{member}/edit', [MemberController::class, 'edit'])->name('socios.edit');
Route::resource('socios', MemberController::class)->except(['create', 'edit'])->parameters(['socios' => 'member']);
Route::post('socios/{member}/inscripcion', [MemberEnrollmentController::class, 'store'])->name('inscripciones.store');
Route::post('inscripciones/{enrollment}/anular', [MemberEnrollmentController::class, 'annul'])->name('inscripciones.annul');
Route::get('inscripciones/{enrollment}/comprobante', [MemberEnrollmentController::class, 'voucher'])->name('inscripciones.voucher');
Route::get('inscripciones/{enrollment}/comprobante/ver', [MemberEnrollmentController::class, 'viewVoucher'])->name('inscripciones.voucher.view');

Route::get('familiares/list', [MemberRelativeController::class, 'list'])->name('familiares.list');
Route::resource('familiares', MemberRelativeController::class)->except(['create', 'edit']);

Route::get('avales/list', [GuarantorController::class, 'list'])->name('avales.list');
Route::get('avales/summary', [GuarantorController::class, 'summary'])->name('avales.summary');
Route::get('avales/next-code', [GuarantorController::class, 'nextCode'])->name('avales.next-code');
Route::get('avales/buscar-select2', [GuarantorController::class, 'select2'])->name('avales.buscar-select2');
Route::get('avales/verificar-dni/{dni}', [GuarantorController::class, 'verifyDni'])->name('avales.verificar-dni');
Route::get('avales/{avale}/edit', [GuarantorController::class, 'edit'])->name('avales.edit');
Route::post('avales/{avale}/anular', [GuarantorController::class, 'annul'])->name('avales.anular');
Route::resource('avales', GuarantorController::class)->except(['create', 'edit', 'destroy'])->parameters(['avales' => 'avale']);

Route::get('acciones/list', [MemberShareController::class, 'list'])->name('acciones.list');
Route::get('acciones/next-code', [MemberShareController::class, 'nextCode'])->name('acciones.next-code');
Route::get('acciones/summary', [MemberShareController::class, 'summary'])->name('acciones.summary');
Route::get('acciones/historial/socio/{member}', [MemberShareController::class, 'historyByMember'])->name('acciones.history.member');
Route::get('acciones/{memberShare}/edit', [MemberShareController::class, 'edit'])->name('acciones.edit');
Route::post('acciones/{memberShare}/anular', [MemberShareController::class, 'annul'])->name('acciones.annul');
Route::get('acciones/{memberShare}/recibo', [MemberShareController::class, 'receipt'])->name('acciones.receipt');
Route::get('acciones/{memberShare}/recibo/pdf', [MemberShareController::class, 'receiptPdf'])->name('acciones.receipt.pdf');
Route::get('acciones/{memberShare}/comprobante/ver', [MemberShareController::class, 'voucherView'])->name('acciones.voucher.view');
Route::get('acciones/{memberShare}/comprobante', [MemberShareController::class, 'voucher'])->name('acciones.voucher');
Route::resource('acciones', MemberShareController::class)->except(['create', 'edit'])->parameters(['acciones' => 'memberShare']);

Route::get('prestamos/simulador/list', [LoanSimulationController::class, 'list'])->name('loan-simulations.list');
Route::get('prestamos/simulador/summary', [LoanSimulationController::class, 'summary'])->name('loan-simulations.summary');
Route::get('prestamos/simulador/next-code', [LoanSimulationController::class, 'nextCode'])->name('loan-simulations.next-code');
Route::post('prestamos/simulador/calculate', [LoanSimulationController::class, 'calculate'])->name('loan-simulations.calculate');
Route::get('prestamos/simulador/{loanSimulation}/edit', [LoanSimulationController::class, 'edit'])->name('loan-simulations.edit');
Route::post('prestamos/simulador/{loanSimulation}/anular', [LoanSimulationController::class, 'annul'])->name('loan-simulations.annul');
Route::post('prestamos/simulador/{loanSimulation}/sin-efecto', [LoanSimulationController::class, 'withoutEffect'])->name('loan-simulations.without-effect');
Route::get('prestamos/simulador/{loanSimulation}/print', [LoanSimulationController::class, 'print'])->name('loan-simulations.print');
Route::resource('prestamos/simulador', LoanSimulationController::class)
    ->except(['create', 'edit'])
    ->names('loan-simulations')
    ->parameters(['simulador' => 'loanSimulation']);

Route::get('prestamos/next-code', [LoanController::class, 'nextCode'])->name('prestamos.next-code');
Route::get('prestamos/summary', [LoanController::class, 'summary'])->name('prestamos.summary');
Route::get('prestamos/cash-balance', [LoanController::class, 'cashBalance'])->name('prestamos.cash-balance');
Route::post('prestamos/calculate', [LoanController::class, 'calculate'])->name('prestamos.calculate');
Route::get('prestamos/list', [LoanController::class, 'list'])->name('prestamos.list');
Route::get('prestamos/{loan}/edit', [LoanController::class, 'edit'])->name('prestamos.edit');
Route::get('prestamos/{loan}/cronograma', [LoanController::class, 'schedule'])->name('prestamos.schedule');
Route::get('prestamos/simulaciones-pendientes/{member}', [LoanController::class, 'pendingSimulations'])->name('prestamos.pending-simulations');
Route::get('prestamos/{loan}/cronograma/imprimir', [LoanController::class, 'schedulePrint'])->name('prestamos.schedule.print');
Route::get('prestamos/{loan}/cronograma/pdf', [LoanController::class, 'schedulePdf'])->name('prestamos.schedule.pdf');
Route::get('prestamos/{loan}/recibo-desembolso', [LoanController::class, 'disbursementReceipt'])->name('prestamos.disbursement.receipt');
Route::get('prestamos/{loan}/recibo-desembolso/pdf', [LoanController::class, 'disbursementReceiptPdf'])->name('prestamos.disbursement.receipt.pdf');
Route::get('prestamos/{loan}/comprobante-desembolso', [LoanController::class, 'disbursementVoucher'])->name('prestamos.disbursement.voucher');
Route::get('prestamos/{loan}/comprobante-desembolso/ver', [LoanController::class, 'viewDisbursementVoucher'])->name('prestamos.disbursement.voucher.view');
Route::post('prestamos/{loan}/aprobar', [LoanController::class, 'approve'])->name('prestamos.approve');
Route::post('prestamos/{loan}/desembolsar', [LoanController::class, 'disburse'])->name('prestamos.disburse');
Route::post('prestamos/{loan}/anular', [LoanController::class, 'annul'])->name('prestamos.annul');
Route::resource('prestamos', LoanController::class)->except(['create', 'edit'])->parameters(['prestamos' => 'loan']);

Route::get('cuotas/list', [LoanInstallmentController::class, 'list'])->name('cuotas.list');
Route::resource('cuotas', LoanInstallmentController::class)
    ->only(['index', 'show', 'update'])
    ->parameters(['cuotas' => 'loanInstallment']);

Route::get('cobros/list', [LoanPaymentController::class, 'list'])->name('cobros.list');
Route::get('cobros/summary', [LoanPaymentController::class, 'summary'])->name('cobros.summary');
Route::get('cobros/next-code', [LoanPaymentController::class, 'nextCode'])->name('cobros.next-code');
Route::get('cobros/socio/{member}/prestamos', [LoanPaymentController::class, 'loansByMember'])->name('cobros.member.loans');
Route::get('cobros/prestamo/{loan}/cuotas', [LoanPaymentController::class, 'installmentsByLoan'])->name('cobros.loan.installments');
Route::get('cobros/{cobro}/edit', [LoanPaymentController::class, 'edit'])->name('cobros.edit');
Route::post('cobros/{cobro}/anular', [LoanPaymentController::class, 'annul'])->name('cobros.annul');
Route::get('cobros/{cobro}/recibo', [LoanPaymentController::class, 'receipt'])->name('cobros.receipt');
Route::get('cobros/{cobro}/recibo/pdf', [LoanPaymentController::class, 'receiptPdf'])->name('cobros.receipt.pdf');
Route::get('cobros/{cobro}/comprobante', [LoanPaymentController::class, 'voucher'])->name('cobros.voucher');
Route::resource('cobros', LoanPaymentController::class)->except(['create', 'edit'])->parameters(['cobros' => 'cobro']);

Route::get('detalle-cobros/list', [LoanPaymentDetailController::class, 'list'])->name('detalle-cobros.list');
Route::resource('detalle-cobros', LoanPaymentDetailController::class)->except(['create', 'edit']);

Route::get('refinanciamientos/list', [LoanRefinancingController::class, 'list'])->name('refinanciamientos.list');
Route::get('refinanciamientos/summary', [LoanRefinancingController::class, 'summary'])->name('refinanciamientos.summary');
Route::get('refinanciamientos/next-code', [LoanRefinancingController::class, 'nextCode'])->name('refinanciamientos.next-code');
Route::get('refinanciamientos/socio/{member}/prestamos', [LoanRefinancingController::class, 'loansByMember'])->name('refinanciamientos.member.loans');
Route::get('refinanciamientos/prestamo/{loan}/saldo', [LoanRefinancingController::class, 'loanBalance'])->name('refinanciamientos.loan.balance');
Route::post('refinanciamientos/calculate', [LoanRefinancingController::class, 'calculate'])->name('refinanciamientos.calculate');
Route::get('refinanciamientos/{refinanciamiento}/edit', [LoanRefinancingController::class, 'edit'])->name('refinanciamientos.edit');
Route::post('refinanciamientos/{refinanciamiento}/anular', [LoanRefinancingController::class, 'annul'])->name('refinanciamientos.annul');
Route::get('refinanciamientos/{refinanciamiento}/cronograma', [LoanRefinancingController::class, 'schedule'])->name('refinanciamientos.schedule');
Route::get('refinanciamientos/{refinanciamiento}/constancia', [LoanRefinancingController::class, 'print'])->name('refinanciamientos.print');
Route::get('refinanciamientos/{refinanciamiento}/pdf', [LoanRefinancingController::class, 'pdf'])->name('refinanciamientos.pdf');
Route::resource('refinanciamientos', LoanRefinancingController::class)->except(['create', 'edit'])->parameters(['refinanciamientos' => 'refinanciamiento']);

Route::get('caja/list', [CashMovementController::class, 'list'])->name('caja.list');
Route::get('caja/summary', [CashMovementController::class, 'summary'])->name('caja.summary');
Route::get('caja/next-code', [CashMovementController::class, 'nextCode'])->name('caja.next-code');
Route::get('caja/{cashMovement}/edit', [CashMovementController::class, 'edit'])->name('caja.edit');
Route::post('caja/{cashMovement}/anular', [CashMovementController::class, 'annul'])->name('caja.annul');
Route::get('caja/{cashMovement}/comprobante', [CashMovementController::class, 'voucher'])->name('caja.voucher');
Route::resource('caja', CashMovementController::class)->except(['create', 'edit'])->parameters(['caja' => 'cashMovement']);

Route::get('solidaridad/list', [SolidarityMovementController::class, 'list'])->name('solidaridad.list');
Route::get('solidaridad/summary', [SolidarityMovementController::class, 'summary'])->name('solidaridad.summary');
Route::get('solidaridad/next-code', [SolidarityMovementController::class, 'nextCode'])->name('solidaridad.next-code');
Route::get('solidaridad/{solidaridad}/edit', [SolidarityMovementController::class, 'edit'])->name('solidaridad.edit');
Route::post('solidaridad/{solidaridad}/anular', [SolidarityMovementController::class, 'annul'])->name('solidaridad.annul');
Route::get('solidaridad/{solidaridad}/recibo', [SolidarityMovementController::class, 'receipt'])->name('solidaridad.receipt');
Route::get('solidaridad/{solidaridad}/recibo/pdf', [SolidarityMovementController::class, 'receiptPdf'])->name('solidaridad.receipt.pdf');
Route::get('solidaridad/{solidaridad}/comprobante', [SolidarityMovementController::class, 'voucher'])->name('solidaridad.voucher');
Route::resource('solidaridad', SolidarityMovementController::class)->except(['create', 'edit'])->parameters(['solidaridad' => 'solidaridad']);
Route::get('fondo-administrativo/list', [AdministrativeFundController::class, 'list'])->name('fondo-administrativo.list');
Route::get('fondo-administrativo/summary', [AdministrativeFundController::class, 'summary'])->name('fondo-administrativo.summary');
Route::get('fondo-administrativo/next-code', [AdministrativeFundController::class, 'nextCode'])->name('fondo-administrativo.next-code');
Route::get('fondo-administrativo/{fondo_administrativo}/edit', [AdministrativeFundController::class, 'edit'])->name('fondo-administrativo.edit');
Route::post('fondo-administrativo/{fondo_administrativo}/anular', [AdministrativeFundController::class, 'annul'])->name('fondo-administrativo.annul');
Route::get('fondo-administrativo/{fondo_administrativo}/comprobante', [AdministrativeFundController::class, 'voucher'])->name('fondo-administrativo.voucher');
Route::resource('fondo-administrativo', AdministrativeFundController::class)->except(['create','edit','destroy'])->parameters(['fondo-administrativo'=>'fondo_administrativo']);

Route::get('actividades/list', [ActivityController::class, 'list'])->name('actividades.list');
Route::get('actividades/summary', [ActivityController::class, 'summary'])->name('actividades.summary');
Route::get('actividades/next-code', [ActivityController::class, 'nextCode'])->name('actividades.next-code');
Route::get('actividades/{actividade}/edit', [ActivityController::class, 'edit'])->name('actividades.edit');
Route::post('actividades/{actividade}/cerrar', [ActivityController::class, 'close'])->name('actividades.close');
Route::post('actividades/{actividade}/anular', [ActivityController::class, 'annul'])->name('actividades.annul');
Route::get('actividades/{actividade}/reporte', [ActivityController::class, 'report'])->name('actividades.report');
Route::get('actividades/{actividade}/reporte/pdf', [ActivityController::class, 'reportPdf'])->name('actividades.report.pdf');
Route::get('actividades/{activity}/movimientos', [ActivityMovementController::class, 'listByActivity'])->name('actividades.movimientos.list');
Route::get('actividades/{activity}/movimientos/next-code', [ActivityMovementController::class, 'nextCode'])->name('actividades.movimientos.next-code');
Route::post('actividades/{activity}/movimientos', [ActivityMovementController::class, 'store'])->name('actividades.movimientos.store');
Route::get('actividades/movimientos/{activityMovement}', [ActivityMovementController::class, 'show'])->name('actividades.movimientos.show');
Route::get('actividades/movimientos/{activityMovement}/edit', [ActivityMovementController::class, 'edit'])->name('actividades.movimientos.edit');
Route::put('actividades/movimientos/{activityMovement}', [ActivityMovementController::class, 'update'])->name('actividades.movimientos.update');
Route::patch('actividades/movimientos/{activityMovement}', [ActivityMovementController::class, 'update'])->name('actividades.movimientos.patch');
Route::post('actividades/movimientos/{activityMovement}/anular', [ActivityMovementController::class, 'annul'])->name('actividades.movimientos.annul');
Route::get('actividades/movimientos/{activityMovement}/recibo', [ActivityMovementController::class, 'receipt'])->name('actividades.movimientos.receipt');
Route::get('actividades/movimientos/{activityMovement}/recibo/pdf', [ActivityMovementController::class, 'receiptPdf'])->name('actividades.movimientos.receipt.pdf');
Route::get('actividades/movimientos/{activityMovement}/comprobante', [ActivityMovementController::class, 'voucher'])->name('actividades.movimientos.voucher');
Route::resource('actividades', ActivityController::class)->except(['create', 'edit'])->parameters(['actividades' => 'actividade']);

Route::get('utilidades/list', [ProfitDistributionController::class, 'list'])->name('utilidades.list');
Route::get('utilidades/summary', [ProfitDistributionController::class, 'summary'])->name('utilidades.summary');
Route::get('utilidades/disponibilidad', [ProfitDistributionController::class, 'availability'])->name('utilidades.availability');
Route::get('utilidades/origenes-cobros', [ProfitDistributionController::class, 'sources'])->name('utilidades.sources');
Route::get('utilidades-fuentes', [ProfitSourceController::class, 'index'])->name('utilidades.sources.index');
Route::post('utilidades-fuentes', [ProfitSourceController::class, 'store'])->name('utilidades.sources.store');
Route::post('utilidades-fuentes/{source}/anular', [ProfitSourceController::class, 'annul'])->name('utilidades.sources.annul');
Route::get('utilidades/next-code', [ProfitDistributionController::class, 'nextCode'])->name('utilidades.next-code');
Route::get('utilidades-mensuales', [ProfitDistributionController::class, 'monthlyList'])->name('utilidades.monthly.list');
Route::post('utilidades-mensuales/calcular', [ProfitDistributionController::class, 'monthlyCalculate'])->name('utilidades.monthly.calculate');
Route::post('utilidades-mensuales', [ProfitDistributionController::class, 'monthlyStore'])->name('utilidades.monthly.store');
Route::get('utilidades-mensuales/{monthly}', [ProfitDistributionController::class, 'monthlyShow'])->name('utilidades.monthly.show');
Route::post('utilidades-mensuales/{monthly}/aprobar', [ProfitDistributionController::class, 'monthlyApprove'])->name('utilidades.monthly.approve');
Route::post('utilidades/calculate', [ProfitDistributionController::class, 'calculate'])->name('utilidades.calculate');
Route::post('utilidades/detalle/{detail}/pagar', [ProfitDistributionController::class, 'payDetail'])->name('utilidades.detail.pay');
Route::get('utilidades/detalle/{detail}/recibo', [ProfitDistributionController::class, 'receipt'])->name('utilidades.receipt');
Route::get('utilidades/detalle/{detail}/recibo/pdf', [ProfitDistributionController::class, 'receiptPdf'])->name('utilidades.receipt.pdf');
Route::get('utilidades/detalle/{detail}/comprobante', [ProfitDistributionController::class, 'voucher'])->name('utilidades.voucher');
Route::get('utilidades/{utilidade}/edit', [ProfitDistributionController::class, 'edit'])->name('utilidades.edit');
Route::post('utilidades/{utilidade}/aprobar', [ProfitDistributionController::class, 'approve'])->name('utilidades.approve');
Route::post('utilidades/{utilidade}/anular', [ProfitDistributionController::class, 'annul'])->name('utilidades.annul');
Route::get('utilidades/{utilidade}/reporte', [ProfitDistributionController::class, 'report'])->name('utilidades.report');
Route::get('utilidades/{utilidade}/reporte/pdf', [ProfitDistributionController::class, 'reportPdf'])->name('utilidades.report.pdf');
Route::resource('utilidades', ProfitDistributionController::class)->except(['create', 'edit'])->parameters(['utilidades' => 'utilidade']);

Route::get('retiros-socios/list', [MemberAccountClosureController::class, 'list'])->name('retiros-socios.list');
Route::get('retiros-socios/summary', [MemberAccountClosureController::class, 'summary'])->name('retiros-socios.summary');
Route::get('retiros-socios/next-code', [MemberAccountClosureController::class, 'nextCode'])->name('retiros-socios.next-code');
Route::get('retiros-socios/members', [MemberAccountClosureController::class, 'members'])->name('retiros-socios.members');
Route::post('retiros-socios/calculate', [MemberAccountClosureController::class, 'calculate'])->name('retiros-socios.calculate');
Route::get('retiros-socios/{retiros_socio}/edit', [MemberAccountClosureController::class, 'edit'])->name('retiros-socios.edit');
Route::post('retiros-socios/{retiros_socio}/cerrar', [MemberAccountClosureController::class, 'close'])->name('retiros-socios.close');
Route::post('retiros-socios/{retiros_socio}/anular', [MemberAccountClosureController::class, 'annul'])->name('retiros-socios.annul');
Route::get('retiros-socios/{retiros_socio}/recibo', [MemberAccountClosureController::class, 'receipt'])->name('retiros-socios.receipt');
Route::get('retiros-socios/{retiros_socio}/recibo/pdf', [MemberAccountClosureController::class, 'receiptPdf'])->name('retiros-socios.receipt.pdf');
Route::get('retiros-socios/{retiros_socio}/comprobante', [MemberAccountClosureController::class, 'voucher'])->name('retiros-socios.voucher');
Route::get('retiros-socios/{retiros_socio}/comprobante/ver', [MemberAccountClosureController::class, 'voucherView'])->name('retiros-socios.voucher.view');
Route::get('retiros-socios/{retiros_socio}/reporte', [MemberAccountClosureController::class, 'report'])->name('retiros-socios.report');
Route::get('retiros-socios/{retiros_socio}/pdf', [MemberAccountClosureController::class, 'pdf'])->name('retiros-socios.pdf');
Route::resource('retiros-socios', MemberAccountClosureController::class)->except(['create', 'edit'])->parameters(['retiros-socios' => 'retiros_socio']);

Route::get('recibos/list', [ReceiptController::class, 'list'])->name('recibos.list');
Route::get('recibos/summary', [ReceiptController::class, 'summary'])->name('recibos.summary');
Route::get('recibos/{recibo}/imprimir', [ReceiptController::class, 'print'])->name('recibos.print');
Route::get('recibos/{recibo}/pdf', [ReceiptController::class, 'pdf'])->name('recibos.pdf');
Route::get('recibos/{recibo}/comprobante', [ReceiptController::class, 'voucher'])->name('recibos.voucher');
Route::get('recibos/{recibo}/download', [ReceiptController::class, 'download'])->name('recibos.download');
Route::resource('recibos', ReceiptController::class)->only(['index', 'show', 'destroy'])->parameters(['recibos' => 'recibo']);

Route::get('reportes', [ReportController::class, 'index'])->name('reportes.index');
Route::get('reportes/socios-vigentes', [ReportController::class, 'activeMembers'])->name('reportes.socios-vigentes');
Route::get('reportes/socios-retirados', [ReportController::class, 'retiredMembers'])->name('reportes.socios-retirados');
Route::get('reportes/acciones-por-socio', [ReportController::class, 'sharesByMember'])->name('reportes.acciones-por-socio');
Route::get('reportes/acciones-mensual', [ReportController::class, 'sharesMonthly'])->name('reportes.acciones-mensual');
Route::get('reportes/acciones-anual', [ReportController::class, 'sharesAnnual'])->name('reportes.acciones-anual');
Route::get('reportes/socio-mayoritario', [ReportController::class, 'majorityMember'])->name('reportes.socio-mayoritario');
Route::get('reportes/acciones-general', [ReportController::class, 'sharesGeneral'])->name('reportes.acciones-general');
Route::get('reportes/prestamos-activos', [ReportController::class, 'activeLoans'])->name('reportes.prestamos-activos');
Route::get('reportes/prestamos-pagados', [ReportController::class, 'paidLoans'])->name('reportes.prestamos-pagados');
Route::get('reportes/prestamos-vencidos', [ReportController::class, 'overdueLoans'])->name('reportes.prestamos-vencidos');
Route::get('reportes/historial-socio', [ReportController::class, 'memberHistory'])->name('reportes.historial-socio');
Route::get('reportes/historial-crediticio', [ReportController::class, 'creditHistory'])->name('reportes.historial-crediticio');
Route::get('historial-crediticio/{member}', [\App\Http\Controllers\Admin\CreditHistoryController::class, 'show'])->name('historial-crediticio.show');
Route::post('historial-crediticio/{member}/recalcular', [\App\Http\Controllers\Admin\CreditHistoryController::class, 'recalculate'])->name('historial-crediticio.recalculate');
Route::get('reportes/cobros-diarios', [ReportController::class, 'dailyPayments'])->name('reportes.cobros-diarios');
Route::get('reportes/cobros-mensuales', [ReportController::class, 'monthlyPayments'])->name('reportes.cobros-mensuales');
Route::get('reportes/caja-general', [ReportController::class, 'cashGeneral'])->name('reportes.caja-general');
Route::get('reportes/solidaridad', [ReportController::class, 'solidarityReport'])->name('reportes.solidaridad');
Route::get('reportes/actividades', [ReportController::class, 'activitiesReport'])->name('reportes.actividades');
Route::get('reportes/actividades/{activity}/detalle', [ReportController::class, 'activityDetail'])->name('reportes.actividades.detalle');
Route::get('reportes/utilidades-socio', [ReportController::class, 'profitsByMember'])->name('reportes.utilidades-socio');
Route::get('reportes/{tipo}/imprimir', [ReportController::class, 'print'])->name('reportes.print');
Route::get('reportes/{tipo}/pdf', [ReportController::class, 'pdf'])->name('reportes.pdf');
Route::get('reportes/{tipo}/excel', [ReportController::class, 'excel'])->name('reportes.excel');
