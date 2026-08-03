<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inicio operativo
    |--------------------------------------------------------------------------
    |
    | Los pagos reales anteriores a esta fecha pueden cargarse como históricos
    | sin alterar el saldo de Caja que inició con el saldo de corte.
    |
    */
    'system_cutoff_date' => env('SYSCAC_SYSTEM_CUTOFF_DATE', '2026-07-01'),
    'fiscal_start_month' => (int) env('UTILITY_FISCAL_START_MONTH', 3),
];
