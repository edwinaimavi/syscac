<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ReportPermissionSeeder extends Seeder
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
            'reportes.index' => 'Ver modulo de reportes',
            'reportes.view' => 'Ver reportes autorizados',
            'reportes.socios_vigentes' => 'Ver reporte de socios vigentes',
            'reportes.socios_retirados' => 'Ver reporte de socios retirados',
            'reportes.acciones_socio' => 'Ver reporte de acciones por socio',
            'reportes.acciones_mensual' => 'Ver reporte de acciones mensual',
            'reportes.acciones_anual' => 'Ver reporte de acciones anual',
            'reportes.socio_mayoritario' => 'Ver reporte de socio mayoritario',
            'reportes.acciones_general' => 'Ver reporte general de acciones',
            'reportes.prestamos_activos' => 'Ver reporte de prestamos activos',
            'reportes.prestamos_pagados' => 'Ver reporte de prestamos pagados',
            'reportes.prestamos_vencidos' => 'Ver reporte de prestamos vencidos',
            'reportes.historial_socio' => 'Ver historial por socio',
            'reportes.cobros_diarios' => 'Ver reporte de cobros diarios',
            'reportes.cobros_mensuales' => 'Ver reporte de cobros mensuales',
            'reportes.caja_general' => 'Ver reporte de caja general',
            'reportes.solidaridad' => 'Ver reporte de solidaridad',
            'reportes.actividades' => 'Ver reporte de actividades',
            'reportes.utilidades_socio' => 'Ver reporte de utilidades por socio',
            'reportes.print' => 'Imprimir reportes',
            'reportes.pdf' => 'Generar PDF de reportes',
            'reportes.export' => 'Exportar reportes autorizados',
            'reportes.excel' => 'Exportar reportes a Excel o CSV',
        ];
    }
}
