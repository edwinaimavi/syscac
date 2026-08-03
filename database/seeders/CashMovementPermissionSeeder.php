<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CashMovementPermissionSeeder extends Seeder
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
            'admin.caja.index' => 'Ver caja',
            'admin.caja.create' => 'Crear movimientos de caja',
            'admin.caja.edit' => 'Editar movimientos de caja',
            'admin.caja.show' => 'Ver detalle de caja',
            'admin.caja.delete' => 'Eliminar movimientos de caja',
            'admin.caja.anular' => 'Anular movimientos de caja',
            'admin.caja.report' => 'Reportar caja',
        ];
    }
}
