<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanSimulationPermissionSeeder extends Seeder
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
            'admin.simulaciones.index' => 'Ver simulaciones de prestamo',
            'admin.simulaciones.create' => 'Crear simulaciones de prestamo',
            'admin.simulaciones.edit' => 'Editar simulaciones de prestamo',
            'admin.simulaciones.show' => 'Ver detalle de simulaciones de prestamo',
            'admin.simulaciones.delete' => 'Eliminar simulaciones de prestamo',
            'admin.simulaciones.anular' => 'Anular simulaciones de prestamo',
            'admin.simulaciones.print' => 'Imprimir simulaciones de prestamo',
        ];
    }
}
