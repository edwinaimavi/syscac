<?php

use App\Http\Controllers\Admin\LoanSimulationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('separates current, converted and historical simulation totals', function () {
    DB::table('members')->insert([
        'id' => 1,
        'code' => 'SOC-000001',
        'first_name' => 'Socio de prueba',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('loan_simulations')->insert([
        [
            'code' => 'SIM-000001',
            'member_id' => 1,
            'simulation_date' => '2026-07-08',
            'amount' => 500,
            'term_months' => 1,
            'start_date' => '2026-07-08',
            'first_payment_date' => '2026-08-08',
            'status' => 'simulada',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'code' => 'SIM-000002',
            'member_id' => 1,
            'simulation_date' => '2026-07-09',
            'amount' => 300,
            'term_months' => 1,
            'start_date' => '2026-07-09',
            'first_payment_date' => '2026-08-09',
            'status' => 'convertida',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'code' => 'SIM-000003',
            'member_id' => 1,
            'simulation_date' => '2026-07-07',
            'amount' => 200,
            'term_months' => 1,
            'start_date' => '2026-07-07',
            'first_payment_date' => '2026-08-07',
            'status' => 'anulada',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = app(LoanSimulationController::class)->summary(Request::create('/summary'));

    expect($response->getData(true))->toMatchArray([
        'total_simulado_vigente' => '500.00',
        'total_convertido' => '300.00',
        'total_registros' => 3,
        'ultima_simulacion' => 'SIM-000002 - S/ 300.00 - Convertida',
    ]);
});
