<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\ShareCashMovementService;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('syscac:sync-share-cash', function (ShareCashMovementService $service) {
    $count = $service->syncRegisteredShares();

    $this->info("Aportes registrados sincronizados con Caja: {$count}");
})->purpose('Sincroniza aportes con Caja y Solidaridad sin duplicar');

Schedule::command('credit-history:recalculate')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->description('Actualiza atrasos activos y puntajes crediticios');
