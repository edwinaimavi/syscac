<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MemberEnrollmentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        foreach (['index', 'create', 'show', 'anular', 'receipt', 'voucher'] as $action) {
            $permission = Permission::firstOrCreate(['name' => "admin.inscripciones.$action", 'guard_name' => 'web'], ['description' => 'Gestionar inscripciones de socios']);
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
