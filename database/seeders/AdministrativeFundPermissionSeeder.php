<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdministrativeFundPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.fondo-administrativo.index','admin.fondo-administrativo.create',
            'admin.fondo-administrativo.show','admin.fondo-administrativo.edit',
            'admin.fondo-administrativo.anular','admin.fondo-administrativo.voucher',
        ];
        foreach ($permissions as $name) Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']);
        Role::where('name','Administrador')->where('guard_name','web')->first()?->givePermissionTo($permissions);
    }
}
