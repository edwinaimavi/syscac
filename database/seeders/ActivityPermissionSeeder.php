<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActivityPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
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
