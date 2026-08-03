<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SolidarityMovementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.solidaridad.index' => 'Ver solidaridad',
            'admin.solidaridad.create' => 'Crear movimientos de solidaridad',
            'admin.solidaridad.edit' => 'Editar movimientos de solidaridad',
            'admin.solidaridad.show' => 'Ver detalle de solidaridad',
            'admin.solidaridad.delete' => 'Eliminar movimientos de solidaridad',
            'admin.solidaridad.anular' => 'Anular movimientos de solidaridad',
            'admin.solidaridad.receipt' => 'Ver recibos de solidaridad',
            'admin.solidaridad.receipt_pdf' => 'Descargar recibos de solidaridad',
            'admin.solidaridad.voucher' => 'Ver comprobantes de solidaridad',
            'admin.solidaridad.report' => 'Reportar solidaridad',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }

        $role = Role::where('name', 'Administrador')->where('guard_name', 'web')->first();

        if ($role) {
            $role->givePermissionTo(array_keys($permissions));
        }
    }
}
