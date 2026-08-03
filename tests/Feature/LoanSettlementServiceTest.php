<?php

use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanSettlementService;

function settlementLoan(float $amount = 300, int $months = 12): Loan
{
    $member = Member::create(['code' => 'SOC-' . fake()->unique()->numerify('######'), 'first_name' => 'Socio', 'last_name' => 'Prueba', 'full_name' => 'Socio Prueba', 'dni' => fake()->unique()->numerify('########'), 'admission_date' => today(), 'member_type' => 'nuevo', 'status' => 'vigente']);
    $loan = Loan::create(['member_id' => $member->id, 'loan_number' => 'PRE-' . fake()->unique()->numerify('######'), 'requested_amount' => $amount, 'approved_amount' => $amount, 'interest_rate' => 1, 'term_months' => $months, 'start_date' => today(), 'first_payment_date' => today()->addMonth(), 'amortization_method' => 'aleman', 'fixed_principal' => $amount / $months, 'total_interest' => 19.50, 'total_amount' => 319.50, 'current_balance' => 319.50, 'status' => 'desembolsado']);
    $opening = $amount;
    for ($i = 1; $i <= $months; $i++) {
        $principal = round($amount / $months, 2); $interest = round($opening * .01, 2); $closing = round($opening - $principal, 2);
        $loan->installments()->create(['installment_number' => $i, 'due_date' => today()->addMonths($i), 'opening_balance' => $opening, 'principal_amount' => $principal, 'interest_amount' => $interest, 'installment_amount' => $principal + $interest, 'remaining_amount' => $principal + $interest, 'closing_balance' => $closing, 'status' => 'pendiente']);
        $opening = $closing;
    }
    return $loan;
}

it('calculates early liquidation as pending capital without future interest', function () {
    $loan = settlementLoan();
    $debt = app(LoanSettlementService::class)->debt($loan, today());
    expect($debt['capital'])->toBe(300.0)->and($debt['overdue_interest'])->toBe(0.0)->and($debt['total'])->toBe(300.0)->and($debt['future_interest_exonerated'])->toBe(19.5);
});

it('charges overdue interest but still excludes future interest on liquidation', function () {
    $loan = settlementLoan();
    $debt = app(LoanSettlementService::class)->debt($loan, today()->addMonth());
    expect($debt['capital'])->toBe(300.0)->and($debt['overdue_interest'])->toBe(3.0)->and($debt['total'])->toBe(303.0)->and($debt['future_interest_exonerated'])->toBe(16.5);
});

it('recalculates a german schedule after a one hundred soles capital amortization', function () {
    $loan = settlementLoan();
    $lastFour = $loan->installments()->orderByDesc('installment_number')->limit(4)->get();
    foreach ($lastFour as $row) $row->update(['principal_amount' => 0, 'remaining_amount' => 0, 'closing_balance' => 0]);
    app(LoanSettlementService::class)->recalculateFutureSchedule($loan, today());
    $loan->refresh();
    expect(round((float) $loan->installments()->sum('principal_amount'), 2))->toBe(200.0)
        ->and((float) $loan->installments()->orderByDesc('installment_number')->first()->closing_balance)->toBe(0.0)
        ->and((float) $loan->current_balance)->toBeGreaterThan(200.0)->toBeLessThan(219.5);
});

it('liquidates a loan by retirement compensation and records exonerated interest', function () {
    $loan = settlementLoan();
    $debt = app(LoanSettlementService::class)->liquidateByCompensation($loan, today());
    expect($debt['total'])->toBe(300.0)->and($loan->fresh()->status)->toBe('pagado')
        ->and((float) $loan->installments()->sum('interest_exonerated'))->toBe(19.5)
        ->and($loan->installments()->where('status', 'liquidado')->count())->toBe(12);
});
