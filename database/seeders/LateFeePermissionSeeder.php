<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder; use Spatie\Permission\Models\Permission; use Spatie\Permission\Models\Role; use Spatie\Permission\PermissionRegistrar;
class LateFeePermissionSeeder extends Seeder { public function run():void { app(PermissionRegistrar::class)->forgetCachedPermissions(); $r=Role::firstOrCreate(['name'=>'Administrador','guard_name'=>'web']); foreach(['mora.index','mora.create','mora.view','mora.edit','mora.delete','mora.activate','mora.configure','mora.exonerate','mora.report'] as $p) $r->givePermissionTo(Permission::firstOrCreate(['name'=>$p,'guard_name'=>'web'],['description'=>'Permiso del módulo de mora'])); app(PermissionRegistrar::class)->forgetCachedPermissions(); } }
