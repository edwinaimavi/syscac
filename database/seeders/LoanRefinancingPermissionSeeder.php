<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanRefinancingPermissionSeeder extends Seeder
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
            'admin.refinanciamientos.index' => 'Ver refinanciamientos',
            'admin.refinanciamientos.create' => 'Crear refinanciamientos',
            'admin.refinanciamientos.edit' => 'Editar refinanciamientos',
            'admin.refinanciamientos.show' => 'Ver detalle de refinanciamientos',
            'admin.refinanciamientos.anular' => 'Anular refinanciamientos',
            'admin.refinanciamientos.schedule' => 'Ver cronograma de refinanciamientos',
            'admin.refinanciamientos.print' => 'Imprimir constancia de refinanciamientos',
            'admin.refinanciamientos.pdf' => 'Descargar constancia de refinanciamientos',
            'admin.refinanciamientos.report' => 'Reportar refinanciamientos',
        ];
    }
}
