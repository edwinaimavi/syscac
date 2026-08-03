<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('renders every report with a consistent DataTables column count', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());

    $routes = [
        'admin.reportes.socios-vigentes',
        'admin.reportes.socios-retirados',
        'admin.reportes.acciones-por-socio',
        'admin.reportes.acciones-mensual',
        'admin.reportes.acciones-anual',
        'admin.reportes.socio-mayoritario',
        'admin.reportes.acciones-general',
        'admin.reportes.prestamos-activos',
        'admin.reportes.prestamos-pagados',
        'admin.reportes.prestamos-vencidos',
        'admin.reportes.historial-socio',
        'admin.reportes.historial-crediticio',
        'admin.reportes.cobros-diarios',
        'admin.reportes.cobros-mensuales',
        'admin.reportes.caja-general',
        'admin.reportes.solidaridad',
        'admin.reportes.actividades',
        'admin.reportes.utilidades-socio',
    ];

    foreach ($routes as $routeName) {
        $html = $this->get(route($routeName))->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $headers = $xpath->query('//table[@id="reportTable"]/thead/tr/th');
        $rows = $xpath->query('//table[@id="reportTable"]/tbody/tr');

        expect($headers->length)->toBeGreaterThan(0, "{$routeName} no tiene encabezados");

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            expect($cells->length)->toBe(
                $headers->length,
                "{$routeName} tiene {$cells->length} celdas para {$headers->length} columnas"
            );
        }

        expect($xpath->query('//table[@id="reportTable"]/tbody/tr/td[@colspan]')->length)
            ->toBe(0, "{$routeName} contiene una fila manual con colspan");
    }
});
