<?php

use App\Models\CashMovement;
use App\Models\LateFeeSetting;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\User;
use App\Services\ProfitAvailabilityService;
use Illuminate\Support\Facades\Gate;

it('calculates the loan balance from unpaid installments after collecting interest and late fee', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    LateFeeSetting::create(['name' => 'Mora diaria', 'grace_days' => 0, 'calculation_type' => 'fixed_daily', 'value' => 1, 'is_active' => true, 'auto_apply' => true, 'allow_waiver' => true]);

    $member = Member::create(['code' => 'SOC-206001', 'first_name' => 'Caso', 'full_name' => 'Caso Saldo 206', 'dni' => '20600001', 'status' => 'vigente']);
    $loan = Loan::create(['member_id' => $member->id, 'loan_number' => 'PRE-000001', 'requested_amount' => 300, 'approved_amount' => 300, 'interest_rate' => 2, 'term_months' => 3, 'start_date' => '2026-05-01', 'first_payment_date' => '2026-06-01', 'amortization_method' => 'aleman', 'fixed_principal' => 100, 'total_interest' => 12, 'total_amount' => 312, 'current_balance' => 312, 'status' => 'desembolsado']);
    $first = $loan->installments()->create(['installment_number' => 1, 'due_date' => '2026-06-01', 'opening_balance' => 300, 'principal_amount' => 100, 'interest_amount' => 6, 'installment_amount' => 106, 'remaining_amount' => 106, 'closing_balance' => 200, 'status' => 'pendiente']);
    $loan->installments()->create(['installment_number' => 2, 'due_date' => '2026-07-01', 'opening_balance' => 200, 'principal_amount' => 100, 'interest_amount' => 4, 'installment_amount' => 104, 'remaining_amount' => 104, 'closing_balance' => 100, 'status' => 'pendiente']);
    $loan->installments()->create(['installment_number' => 3, 'due_date' => '2026-08-01', 'opening_balance' => 100, 'principal_amount' => 100, 'interest_amount' => 2, 'installment_amount' => 102, 'remaining_amount' => 102, 'closing_balance' => 0, 'status' => 'pendiente']);

    $this->postJson(route('admin.cobros.store'), ['loan_id' => $loan->id, 'payment_date' => '2026-06-17', 'amount' => 122, 'payment_type' => 'cuota', 'payment_method' => 'efectivo', 'installment_ids' => [$first->id]])->assertOk();

    $payment = LoanPayment::firstOrFail();
    expect((float) $loan->fresh()->current_balance)->toBe(206.0)
        ->and((float) $payment->previous_loan_balance)->toBe(312.0)
        ->and((float) $payment->new_loan_balance)->toBe(206.0)
        ->and((float) $payment->capital_amount)->toBe(100.0)
        ->and((float) $payment->interest_amount)->toBe(6.0)
        ->and((float) $payment->late_fee_paid)->toBe(16.0)
        ->and((float) CashMovement::where('related_id', $payment->id)->where('status', 'registrado')->sum('amount'))->toBe(122.0);

    $this->getJson(route('admin.cobros.loan.installments', $loan) . '?payment_date=2026-06-17')
        ->assertOk()->assertJsonPath('loan.current_balance', '206.00')->assertJsonCount(2, 'installments')
        ->assertJsonPath('installments.0.remaining_amount', '104.00')->assertJsonPath('installments.1.remaining_amount', '102.00');
    $this->getJson(route('admin.prestamos.show', $loan))->assertOk()->assertJsonPath('current_balance_formatted', 'S/ 206.00');

    $profit = app(ProfitAvailabilityService::class)->summary('2026-06-01', '2026-06-30');
    expect($profit['interestCollected'])->toBe(6.0)->and($profit['lateFeesCollected'])->toBe(16.0)->and($profit['generated'])->toBe(22.0);
});
