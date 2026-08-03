<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GuarantorPermissionSeeder extends Seeder
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
            'avales.index' => 'Ver avales y garantes',
            'avales.create' => 'Crear avales y garantes',
            'avales.edit' => 'Editar avales y garantes',
            'avales.show' => 'Ver detalle de avales y garantes',
            'avales.delete' => 'Eliminar avales y garantes',
            'avales.anular' => 'Anular avales y garantes',
        ];
    }
}
