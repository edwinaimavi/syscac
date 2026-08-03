<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ReceiptPermissionSeeder extends Seeder
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
            'admin.recibos.index' => 'Ver recibos',
            'admin.recibos.show' => 'Ver detalle de recibos',
            'admin.recibos.print' => 'Imprimir recibos',
            'admin.recibos.pdf' => 'Descargar recibos',
            'admin.recibos.download' => 'Descargar archivos de recibos',
            'admin.recibos.voucher' => 'Ver comprobantes de recibos',
            'admin.recibos.delete' => 'Eliminar recibos',
            'admin.recibos.report' => 'Reportar recibos',
        ];
    }
}
