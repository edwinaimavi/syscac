<?php

use App\Models\CashMovement;
use App\Models\LateFeeSetting;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\User;
use App\Services\CreditHistoryService;
use App\Services\ProfitAvailabilityService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    LateFeeSetting::create([
        'name' => 'Histórica',
        'grace_days' => 5,
        'calculation_type' => 'fixed_daily',
        'value' => 2,
        'is_active' => true,
        'auto_apply' => true,
        'allow_waiver' => true,
    ]);
});

function historicalLoan(string $suffix): array
{
    $member = Member::create([
        'code' => "SOC-H{$suffix}",
        'first_name' => 'Socio',
        'full_name' => "Socio Histórico {$suffix}",
        'dni' => str_pad($suffix, 8, '7'),
        'status' => 'vigente',
    ]);
    $loan = Loan::create([
        'member_id' => $member->id,
        'loan_number' => "PRE-H{$suffix}",
        'requested_amount' => 50,
        'approved_amount' => 50,
        'interest_rate' => 2,
        'term_months' => 2,
        'start_date' => '2025-06-01',
        'first_payment_date' => '2025-07-01',
        'amortization_method' => 'aleman',
        'fixed_principal' => 25,
        'total_interest' => 12,
        'total_amount' => 62,
        'current_balance' => 62,
        'status' => 'desembolsado',
    ]);
    $installment = $loan->installments()->create([
        'installment_number' => 1,
        'due_date' => '2025-07-01',
        'opening_balance' => 50,
        'principal_amount' => 25,
        'interest_amount' => 6,
        'installment_amount' => 31,
        'remaining_amount' => 31,
        'closing_balance' => 25,
        'status' => 'pendiente',
    ]);
    $loan->installments()->create([
        'installment_number' => 2,
        'due_date' => '2026-08-01',
        'opening_balance' => 25,
        'principal_amount' => 25,
        'interest_amount' => 6,
        'installment_amount' => 31,
        'remaining_amount' => 31,
        'closing_balance' => 0,
        'status' => 'pendiente',
    ]);

    return [$member, $loan, $installment];
}

it('records an on-time historical payment without current cash or an unfair score penalty', function () {
    [$member, $loan, $installment] = historicalLoan('1000001');

    $this->postJson(route('admin.cobros.store'), [
        'loan_id' => $loan->id,
        'payment_date' => '2025-07-01',
        'amount' => 31,
        'payment_type' => 'cuota',
        'payment_method' => 'efectivo',
        'installment_ids' => [$installment->id],
        'is_historical' => 1,
        'affects_profit' => 1,
        'affects_credit_history' => 1,
        'late_fee_charged' => 0,
        'late_fee_exonerated' => 0,
    ])->assertOk();

    $payment = LoanPayment::firstOrFail();
    $history = app(CreditHistoryService::class)->recalculate($member);
    expect($payment->is_historical)->toBeTrue()
        ->and($payment->affects_cash)->toBeFalse()
        ->and((float) $payment->late_fee_calculated)->toBe(0.0)
        ->and($installment->fresh()->status)->toBe('pagado')
        ->and((float) $loan->fresh()->current_balance)->toBe(31.0)
        ->and(CashMovement::where('related_type', LoanPayment::class)->where('related_id', $payment->id)->exists())->toBeFalse()
        ->and($history->on_time_payments)->toBe(1)
        ->and($history->late_payments)->toBe(0);
});

it('uses the real payment date and accepts the justified historical late fee split', function () {
    [$member, $loan, $installment] = historicalLoan('1000002');

    $this->postJson(route('admin.cobros.store'), [
        'loan_id' => $loan->id,
        'payment_date' => '2025-07-22',
        'amount' => 61,
        'payment_type' => 'cuota',
        'payment_method' => 'efectivo',
        'installment_ids' => [$installment->id],
        'is_historical' => 1,
        'affects_profit' => 1,
        'affects_credit_history' => 1,
        'late_fee_charged' => 30,
        'late_fee_exonerated' => 2,
        'late_fee_override_reason' => 'Migración desde kardex histórico',
    ])->assertOk();

    $payment = LoanPayment::firstOrFail();
    $history = app(CreditHistoryService::class)->recalculate($member);
    expect((float) $payment->late_fee_calculated)->toBe(32.0)
        ->and((float) $payment->late_fee_charged)->toBe(30.0)
        ->and((float) $payment->late_fee_exonerated)->toBe(2.0)
        ->and($payment->late_fee_days)->toBe(16)
        ->and($history->late_payments)->toBe(1);
});

it('includes only collected interest and late fee when historical profit is enabled', function () {
    [, $loan, $installment] = historicalLoan('1000003');
    $this->postJson(route('admin.cobros.store'), [
        'loan_id' => $loan->id,
        'payment_date' => '2025-07-22',
        'amount' => 61,
        'payment_type' => 'cuota',
        'payment_method' => 'efectivo',
        'installment_ids' => [$installment->id],
        'is_historical' => 1,
        'affects_profit' => 1,
        'affects_credit_history' => 1,
        'late_fee_charged' => 30,
        'late_fee_exonerated' => 2,
        'late_fee_override_reason' => 'Migración desde kardex histórico',
    ])->assertOk();

    $profit = app(ProfitAvailabilityService::class)->summary('2025-07-01', '2025-07-31');
    expect($profit['interestCollected'])->toBe(6.0)
        ->and($profit['lateFeesCollected'])->toBe(30.0)
        ->and($profit['generated'])->toBe(36.0);
});

it('shows a migrated payment by real or registration date without turning it into current cash', function () {
    [$member, $loan, $installment] = historicalLoan('1000004');
    $installment->update([
        'opening_balance' => 125,
        'principal_amount' => 100,
        'interest_amount' => 6,
        'installment_amount' => 106,
        'remaining_amount' => 106,
        'closing_balance' => 25,
    ]);
    $loan->update([
        'requested_amount' => 125,
        'approved_amount' => 125,
        'total_interest' => 12,
        'total_amount' => 137,
        'current_balance' => 137,
    ]);

    $this->postJson(route('admin.cobros.store'), [
        'loan_id' => $loan->id,
        'payment_date' => '2025-07-01',
        'amount' => 106,
        'payment_type' => 'cuota',
        'payment_method' => 'efectivo',
        'installment_ids' => [$installment->id],
        'is_historical' => 1,
        'affects_profit' => 1,
        'affects_credit_history' => 1,
        'late_fee_charged' => 0,
        'late_fee_exonerated' => 0,
    ])->assertOk();

    $payment = LoanPayment::firstOrFail();
    $this->get(route('admin.reportes.cobros-diarios', [
        'date' => '2025-07-01',
        'date_basis' => 'payment_date',
        'include_historical' => '1',
        'affects_cash' => 'all',
    ]))->assertOk()
        ->assertSee($payment->payment_number)
        ->assertSee('Histórico')
        ->assertSee('No afecta caja')
        ->assertSee('S/ 6.00');

    $this->get(route('admin.reportes.cobros-diarios', [
        'date' => $payment->created_at->toDateString(),
        'date_basis' => 'registered_at',
        'include_historical' => 'only',
        'affects_cash' => '0',
    ]))->assertOk()->assertSee($payment->payment_number);

    $this->get(route('admin.reportes.caja-general'))->assertOk()->assertDontSee($payment->payment_number);
    $this->get(route('admin.reportes.caja-general', [
        'cash_include_historical' => '1',
        'date_from' => '2025-07-01',
        'date_to' => '2025-07-01',
    ]))->assertOk()->assertSee($payment->payment_number)->assertSee('Sin efecto');

    $july2025 = app(ProfitAvailabilityService::class)->summary('2025-07-01', '2025-07-31');
    $cycle2026 = app(ProfitAvailabilityService::class)->summary('2026-03-01', '2027-03-01');
    expect(CashMovement::where('related_id', $payment->id)->exists())->toBeFalse()
        ->and($installment->fresh()->status)->toBe('pagado')
        ->and((float) $installment->fresh()->capital_paid)->toBe(100.0)
        ->and((float) $installment->fresh()->interest_paid)->toBe(6.0)
        ->and($july2025['interestCollected'])->toBe(6.0)
        ->and($cycle2026['generated'])->toBe(0.0)
        ->and(app(CreditHistoryService::class)->recalculate($member)->on_time_payments)->toBe(1);
});

it('keeps externally distributed historical profit visible without generating it again', function () {
    [, $loan, $installment] = historicalLoan('1000005');
    $this->postJson(route('admin.cobros.store'), [
        'loan_id' => $loan->id,
        'payment_date' => '2025-07-01',
        'amount' => 31,
        'payment_type' => 'cuota',
        'payment_method' => 'efectivo',
        'installment_ids' => [$installment->id],
        'is_historical' => 1,
        'affects_profit' => 1,
        'affects_credit_history' => 1,
        'profit_treatment' => 'externally_distributed',
        'late_fee_charged' => 0,
        'late_fee_exonerated' => 0,
    ])->assertOk();

    expect(LoanPayment::first()->profit_treatment)->toBe('externally_distributed')
        ->and(app(ProfitAvailabilityService::class)->summary('2025-07-01', '2025-07-31')['generated'])->toBe(0.0);
});
