<?php

use App\Services\LoanSimulationCalculator;

it('adjusts the final german principal and closes the balance at zero', function () {
    $result = app(LoanSimulationCalculator::class)->calculate(100, 3.25, 3, '2026-07-11');
    $installments = $result['installments'];

    expect($installments[0]['principal_amount'])->toBe(33.33)
        ->and($installments[1]['principal_amount'])->toBe(33.33)
        ->and($installments[2]['principal_amount'])->toBe(33.34)
        ->and($installments[2]['closing_balance'])->toBe(0.0)
        ->and(round(array_sum(array_column($installments, 'principal_amount')), 2))->toBe(100.0)
        ->and($result['summary']['total_interest'])->toBe(round(array_sum(array_column($installments, 'interest_amount')), 2))
        ->and($result['summary']['total_payment'])->toBe(round(100 + $result['summary']['total_interest'], 2));
});
