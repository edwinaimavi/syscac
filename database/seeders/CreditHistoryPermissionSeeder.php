<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreditHistoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        foreach (['credit-history.index' => 'Ver historial crediticio', 'credit-history.show' => 'Ver detalle del historial crediticio', 'credit-history.recalculate' => 'Recalcular historial crediticio', 'credit-history.report' => 'Ver reporte de historial crediticio'] as $name => $description) {
            $permission = Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['description' => $description]);
            $admin->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
