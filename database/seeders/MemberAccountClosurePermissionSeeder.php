<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MemberAccountClosurePermissionSeeder extends Seeder
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

            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissions(): array
    {
        return [
            'retiros.index' => 'Ver retiros de socios',
            'retiros.create' => 'Crear cierres de cuenta de socios',
            'retiros.edit' => 'Editar cierres de cuenta de socios',
            'retiros.show' => 'Ver detalle de cierres de cuenta de socios',
            'retiros.calculate' => 'Calcular cierre de cuenta de socios',
            'retiros.close' => 'Confirmar cierre de cuenta de socios',
            'retiros.anular' => 'Anular cierre de cuenta de socios',
            'retiros.receipt' => 'Ver constancia de cierre de cuenta de socios',
            'retiros.receipt_pdf' => 'Descargar constancia de cierre de cuenta de socios',
            'retiros.voucher' => 'Ver comprobante de cierre de cuenta de socios',
            'retiros.report' => 'Reportar cierre de cuenta de socios',
        ];
    }
}
