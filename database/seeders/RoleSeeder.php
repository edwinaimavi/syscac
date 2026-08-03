<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);

        foreach ($this->permissions() as $name => $description) {
            $permission = Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );

            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissions(): array
    {
        return [
            'credit-history.index' => 'Ver historial crediticio',
            'credit-history.show' => 'Ver detalle del historial crediticio',
            'credit-history.recalculate' => 'Recalcular historial crediticio',
            'credit-history.report' => 'Ver reporte de historial crediticio',
            'admin.users.index' => 'Ver los usuarios',
            'admin.users.store' => 'Crear usuarios',
            'admin.users.update' => 'Actualizar usuarios',
            'admin.users.destroy' => 'Eliminar usuarios',
            'admin.roles.index' => 'Ver los roles',
            'admin.roles.store' => 'Crear roles',
            'admin.roles.update' => 'Actualizar roles',
            'admin.roles.destroy' => 'Eliminar roles',
            'admin.socios.index' => 'Ver socios',
            'admin.socios.create' => 'Crear socios',
            'admin.socios.edit' => 'Editar socios',
            'admin.socios.show' => 'Ver detalle de socios',
            'admin.socios.delete' => 'Eliminar socios',
            'admin.socios.retire' => 'Retirar socios',
            'admin.acciones.index' => 'Ver acciones y aportes',
            'admin.acciones.create' => 'Crear acciones y aportes',
            'admin.acciones.edit' => 'Editar acciones y aportes',
            'admin.acciones.show' => 'Ver detalle de acciones y aportes',
            'admin.acciones.delete' => 'Eliminar acciones y aportes',
            'admin.acciones.anular' => 'Anular acciones y aportes',
            'admin.acciones.receipt' => 'Ver recibos de acciones y aportes',
            'admin.acciones.report' => 'Reportar acciones y aportes',
            'admin.prestamos.index' => 'Ver prestamos',
            'admin.prestamos.create' => 'Crear prestamos',
            'admin.prestamos.edit' => 'Editar prestamos',
            'admin.prestamos.show' => 'Ver detalle de prestamos',
            'admin.prestamos.delete' => 'Eliminar prestamos',
            'admin.prestamos.simulate' => 'Simular prestamos',
            'admin.prestamos.approve' => 'Aprobar prestamos',
            'admin.prestamos.disburse' => 'Desembolsar prestamos',
            'admin.prestamos.disbursement_receipt' => 'Ver recibo de desembolso de prestamos',
            'admin.prestamos.disbursement_voucher' => 'Ver comprobante de desembolso de prestamos',
            'admin.prestamos.annul' => 'Anular prestamos',
            'admin.prestamos.schedule' => 'Ver cronograma de prestamos',
            'admin.prestamos.schedule_print' => 'Imprimir cronograma de prestamos',
            'admin.prestamos.schedule_pdf' => 'Descargar cronograma de prestamos',
            'admin.prestamos.report' => 'Reportar prestamos',
            'admin.simulaciones.index' => 'Ver simulaciones de prestamo',
            'admin.simulaciones.create' => 'Crear simulaciones de prestamo',
            'admin.simulaciones.edit' => 'Editar simulaciones de prestamo',
            'admin.simulaciones.show' => 'Ver detalle de simulaciones de prestamo',
            'admin.simulaciones.delete' => 'Eliminar simulaciones de prestamo',
            'admin.simulaciones.anular' => 'Anular simulaciones de prestamo',
            'admin.simulaciones.print' => 'Imprimir simulaciones de prestamo',
            'admin.cobros.index' => 'Ver cobros',
            'admin.cobros.create' => 'Crear cobros',
            'admin.cobros.edit' => 'Editar cobros',
            'admin.cobros.show' => 'Ver detalle de cobros',
            'admin.cobros.delete' => 'Eliminar cobros',
            'admin.cobros.anular' => 'Anular cobros',
            'admin.cobros.receipt' => 'Generar recibos de cobros',
            'admin.cobros.receipt_pdf' => 'Descargar recibos de cobros',
            'admin.cobros.voucher' => 'Ver comprobantes de cobros',
            'admin.cobros.report' => 'Reportar cobros',
            'admin.cobros.liquidate' => 'Liquidar prestamos',
            'admin.cobros.capital_payment' => 'Registrar abonos a capital',
            'admin.refinanciamientos.index' => 'Ver refinanciamientos',
            'admin.refinanciamientos.create' => 'Crear refinanciamientos',
            'admin.refinanciamientos.edit' => 'Editar refinanciamientos',
            'admin.refinanciamientos.show' => 'Ver detalle de refinanciamientos',
            'admin.refinanciamientos.delete' => 'Eliminar refinanciamientos',
            'admin.refinanciamientos.anular' => 'Anular refinanciamientos',
            'admin.refinanciamientos.schedule' => 'Ver cronograma de refinanciamientos',
            'admin.refinanciamientos.print' => 'Imprimir constancia de refinanciamientos',
            'admin.refinanciamientos.pdf' => 'Descargar constancia de refinanciamientos',
            'admin.refinanciamientos.report' => 'Reportar refinanciamientos',
            'admin.caja.index' => 'Ver caja',
            'admin.caja.create' => 'Crear movimientos de caja',
            'admin.caja.edit' => 'Editar movimientos de caja',
            'admin.caja.show' => 'Ver detalle de caja',
            'admin.caja.delete' => 'Eliminar movimientos de caja',
            'admin.caja.anular' => 'Anular movimientos de caja',
            'admin.caja.report' => 'Reportar caja',
            'admin.solidaridad.index' => 'Ver solidaridad',
            'admin.solidaridad.create' => 'Crear movimientos de solidaridad',
            'admin.solidaridad.edit' => 'Editar solidaridad',
            'admin.solidaridad.show' => 'Ver detalle de solidaridad',
            'admin.solidaridad.delete' => 'Eliminar solidaridad',
            'admin.solidaridad.anular' => 'Anular solidaridad',
            'admin.solidaridad.receipt' => 'Ver recibos de solidaridad',
            'admin.solidaridad.receipt_pdf' => 'Descargar recibos de solidaridad',
            'admin.solidaridad.voucher' => 'Ver comprobantes de solidaridad',
            'admin.solidaridad.report' => 'Reportar solidaridad',
            'admin.fondo-administrativo.index' => 'Ver fondo administrativo',
            'admin.fondo-administrativo.create' => 'Crear movimientos del fondo administrativo',
            'admin.fondo-administrativo.show' => 'Ver fondo administrativo',
            'admin.fondo-administrativo.edit' => 'Editar fondo administrativo',
            'admin.fondo-administrativo.anular' => 'Anular fondo administrativo',
            'admin.fondo-administrativo.voucher' => 'Ver comprobantes del fondo administrativo',
            'admin.actividades.index' => 'Ver actividades',
            'admin.actividades.create' => 'Crear actividades',
            'admin.actividades.edit' => 'Editar actividades',
            'admin.actividades.show' => 'Ver detalle de actividades',
            'admin.actividades.delete' => 'Eliminar actividades',
            'admin.actividades.anular' => 'Anular actividades',
            'admin.actividades.close' => 'Cerrar actividades',
            'admin.actividades.movements' => 'Ver movimientos de actividades',
            'admin.actividades.movement_create' => 'Crear movimientos de actividades',
            'admin.actividades.movement_edit' => 'Editar movimientos de actividades',
            'admin.actividades.movement_show' => 'Ver detalle de movimientos de actividades',
            'admin.actividades.movement_anular' => 'Anular movimientos de actividades',
            'admin.actividades.receipt' => 'Ver recibos de actividades',
            'admin.actividades.receipt_pdf' => 'Descargar recibos de actividades',
            'admin.actividades.voucher' => 'Ver comprobantes de actividades',
            'admin.actividades.report' => 'Reportar actividades',
            'admin.actividades.report_pdf' => 'Descargar reportes de actividades',
            'admin.utilidades.index' => 'Ver utilidades',
            'admin.utilidades.create' => 'Crear distribuciones de utilidades',
            'admin.utilidades.edit' => 'Editar distribuciones de utilidades',
            'admin.utilidades.calculate' => 'Calcular utilidades',
            'admin.utilidades.sources' => 'Ver cobros que originan utilidades',
            'admin.utilidades.show' => 'Ver detalle de utilidades',
            'admin.utilidades.approve' => 'Aprobar utilidades',
            'admin.utilidades.pay' => 'Pagar utilidades',
            'admin.utilidades.anular' => 'Anular utilidades',
            'admin.utilidades.receipt' => 'Ver recibos de utilidades',
            'admin.utilidades.receipt_pdf' => 'Descargar recibos de utilidades',
            'admin.utilidades.voucher' => 'Ver comprobantes de utilidades',
            'admin.utilidades.report' => 'Reportar utilidades',
            'admin.utilidades.report_pdf' => 'Descargar reportes de utilidades',
            'retiros.index' => 'Ver retiros de socios',
            'retiros.create' => 'Crear cierres de cuenta de socios',
            'retiros.edit' => 'Editar cierres de cuenta de socios',
            'retiros.show' => 'Ver detalle de cierres de cuenta de socios',
            'retiros.calculate' => 'Calcular cierre de cuenta de socios',
            'retiros.close' => 'Confirmar cierre de cuenta de socios',
            'retiros.anular' => 'Anular cierre de cuenta de socios',
            'retiros.receipt' => 'Ver constancias de cierre de cuenta de socios',
            'retiros.receipt_pdf' => 'Descargar constancias de cierre de cuenta de socios',
            'retiros.voucher' => 'Ver comprobantes de cierre de cuenta de socios',
            'retiros.report' => 'Reportar cierres de cuenta de socios',
            'admin.recibos.index' => 'Ver recibos',
            'admin.recibos.show' => 'Ver detalle de recibos',
            'admin.recibos.download' => 'Descargar recibos',
            'admin.recibos.print' => 'Imprimir recibos',
            'admin.recibos.pdf' => 'Descargar recibos',
            'admin.recibos.voucher' => 'Ver comprobantes de recibos',
            'admin.recibos.delete' => 'Eliminar recibos',
            'admin.recibos.report' => 'Reportar recibos',
        ];
    }
}
