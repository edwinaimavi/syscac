<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MemberSharePermissionSeeder extends Seeder
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
            'admin.acciones.index' => 'Ver acciones y aportes',
            'admin.acciones.create' => 'Crear acciones y aportes',
            'admin.acciones.edit' => 'Editar acciones y aportes',
            'admin.acciones.show' => 'Ver detalle de acciones y aportes',
            'admin.acciones.delete' => 'Eliminar acciones y aportes',
            'admin.acciones.anular' => 'Anular acciones y aportes',
            'admin.acciones.receipt' => 'Ver recibos de acciones y aportes',
            'admin.acciones.report' => 'Reportar acciones y aportes',
        ];
    }
}
