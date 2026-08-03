<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanPaymentPermissionSeeder extends Seeder
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
            'admin.cobros.index' => 'Ver cobros',
            'admin.cobros.create' => 'Crear cobros',
            'admin.cobros.edit' => 'Editar cobros',
            'admin.cobros.show' => 'Ver detalle de cobros',
            'admin.cobros.delete' => 'Eliminar cobros',
            'admin.cobros.anular' => 'Anular cobros',
            'admin.cobros.receipt' => 'Ver recibo de cobros',
            'admin.cobros.receipt_pdf' => 'Descargar recibo de cobros',
            'admin.cobros.voucher' => 'Ver comprobante de cobros',
            'admin.cobros.report' => 'Reportar cobros',
            'admin.cobros.liquidate' => 'Liquidar prestamos',
            'admin.cobros.capital_payment' => 'Registrar abonos a capital',
        ];
    }
}
