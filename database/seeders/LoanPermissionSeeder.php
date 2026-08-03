<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);

        foreach ($this->permissions() as $name => $description) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );

            if ($permission->description !== $description) {
                $permission->forceFill(['description' => $description])->save();
            }

            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissions(): array
    {
        return [
            'admin.prestamos.index' => 'Ver prestamos',
            'admin.prestamos.create' => 'Crear prestamos',
            'admin.prestamos.edit' => 'Editar prestamos',
            'admin.prestamos.show' => 'Ver detalle de prestamos',
            'admin.prestamos.delete' => 'Eliminar prestamos',
            'admin.prestamos.approve' => 'Aprobar prestamos',
            'admin.prestamos.disburse' => 'Desembolsar prestamos',
            'admin.prestamos.disbursement_receipt' => 'Ver recibo de desembolso de prestamos',
            'admin.prestamos.disbursement_voucher' => 'Ver comprobante de desembolso de prestamos',
            'admin.prestamos.annul' => 'Anular prestamos',
            'admin.prestamos.schedule' => 'Ver cronograma de prestamos',
            'admin.prestamos.schedule_print' => 'Imprimir cronograma de prestamos',
            'admin.prestamos.schedule_pdf' => 'Descargar cronograma de prestamos',
            'admin.prestamos.report' => 'Reportar prestamos',
        ];
    }
}
