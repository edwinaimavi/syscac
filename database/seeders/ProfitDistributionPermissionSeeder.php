<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProfitDistributionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.utilidades.index' => 'Ver utilidades',
            'admin.utilidades.create' => 'Crear distribuciones de utilidades',
            'admin.utilidades.edit' => 'Editar distribuciones de utilidades',
            'admin.utilidades.show' => 'Ver detalle de utilidades',
            'admin.utilidades.calculate' => 'Calcular utilidades',
            'admin.utilidades.sources' => 'Ver cobros que originan utilidades',
            'admin.utilidades.approve' => 'Aprobar utilidades',
            'admin.utilidades.pay' => 'Pagar utilidades',
            'admin.utilidades.anular' => 'Anular utilidades',
            'admin.utilidades.receipt' => 'Ver recibos de utilidades',
            'admin.utilidades.receipt_pdf' => 'Descargar recibos de utilidades',
            'admin.utilidades.voucher' => 'Ver comprobantes de utilidades',
            'admin.utilidades.report' => 'Reportar utilidades',
            'admin.utilidades.report_pdf' => 'Descargar reportes de utilidades',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['description' => $description]);
        }

        $role = Role::where('name', 'Administrador')->where('guard_name', 'web')->first();
        if ($role) {
            $role->givePermissionTo(array_keys($permissions));
        }
    }
}
