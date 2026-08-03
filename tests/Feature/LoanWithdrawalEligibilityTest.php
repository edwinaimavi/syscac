<?php

use App\Models\Loan;
use App\Models\LoanSimulation;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberShare;
use App\Models\User;
use App\Services\LoanEligibilityService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

it('blocks loans simulations conversion approval disbursement and guarantees during an active withdrawal', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());

    $blocked = Member::create([
        'code' => 'SOC-950001', 'first_name' => 'Socio', 'full_name' => 'Socio retiro pendiente',
        'dni' => '95000001', 'admission_date' => '2024-01-01', 'status' => 'vigente',
    ]);
    MemberShare::create([
        'code' => 'APO-950001', 'member_id' => $blocked->id, 'date' => '2025-01-01',
        'amount' => 100, 'share_value' => 20, 'shares_quantity' => 5,
        'payment_method' => 'efectivo', 'status' => 'registrado',
    ]);
    MemberAccountClosure::create([
        'code' => 'CIE-950001', 'member_id' => $blocked->id,
        'closure_date' => '2026-07-13', 'retirement_date' => '2026-07-13',
        'total_contributions' => 100, 'total_shares' => 5,
        'pending_loans_amount' => 250, 'pending_utilities_amount' => 0,
        'total_in_favor' => 100, 'total_against' => 250, 'final_balance' => -150,
        'settlement_type' => 'contra_socio', 'reason' => 'Solicitud de retiro', 'status' => 'calculado',
    ]);
    $simulation = LoanSimulation::create([
        'code' => 'SIM-950001', 'member_id' => $blocked->id, 'simulation_date' => '2026-07-13',
        'amount' => 100, 'interest_rate' => 2, 'interest_type' => 'mensual', 'term_months' => 2,
        'start_date' => '2026-07-13', 'first_payment_date' => '2026-08-13',
        'amortization_method' => 'aleman', 'fixed_principal' => 50,
        'total_interest' => 3, 'total_payment' => 103, 'status' => 'simulada',
    ]);

    $loanPayload = [
        'loan_simulation_id' => null, 'member_id' => $blocked->id,
        'requested_amount' => 100, 'approved_amount' => 100, 'interest_rate' => 2,
        'term_months' => 2, 'start_date' => '2026-07-13', 'first_payment_date' => '2026-08-13',
        'amortization_method' => 'aleman', 'status' => 'pendiente',
    ];
    $simulationPayload = [
        'member_id' => $blocked->id, 'simulation_date' => '2026-07-13',
        'amount' => 100, 'interest_rate' => 2, 'term_months' => 2,
        'start_date' => '2026-07-13', 'first_payment_date' => '2026-08-13',
        'amortization_method' => 'aleman', 'status' => 'simulada',
    ];
    $message = LoanEligibilityService::WITHDRAWAL_LOAN_MESSAGE;

    $evaluation = app(LoanEligibilityService::class)->evaluate($blocked, 100);
    expect($evaluation['withdrawal_blocked'])->toBeTrue()
        ->and($evaluation['withdrawal_status'])->toBe('No habilitado')
        ->and($evaluation['withdrawal_reason'])->toBe('Retiro pendiente de regularización')
        ->and($evaluation['withdrawal_balance_against_formatted'])->toBe('S/ 150.00')
        ->and($blocked->canRequestLoan())->toBeFalse()
        ->and($blocked->canBeGuarantor())->toBeFalse();
    expect(fn () => app(LoanEligibilityService::class)->validate($blocked, 100))
        ->toThrow(ValidationException::class, $message);

    $this->postJson(route('admin.prestamos.calculate'), $loanPayload)->assertOk()
        ->assertJsonPath('eligibility.withdrawal_blocked', true)
        ->assertJsonPath('eligibility.withdrawal_reason', 'Retiro pendiente de regularización');
    $this->postJson(route('admin.loan-simulations.calculate'), $simulationPayload)->assertOk()
        ->assertJsonPath('eligibility.withdrawal_blocked', true);

    $this->postJson(route('admin.prestamos.store'), $loanPayload)->assertUnprocessable()
        ->assertJsonPath('message', $message)
        ->assertJsonPath('errors.member_id.0', $message);
    $this->postJson(route('admin.loan-simulations.store'), $simulationPayload)->assertUnprocessable()
        ->assertJsonPath('message', $message)
        ->assertJsonPath('errors.member_id.0', $message);
    $this->postJson(route('admin.prestamos.store'), array_merge($loanPayload, ['loan_simulation_id' => $simulation->id]))
        ->assertUnprocessable()->assertJsonPath('message', $message);
    expect($simulation->fresh()->status)->toBe('simulada');

    $pendingLoan = Loan::create([
        'member_id' => $blocked->id, 'loan_number' => 'PRE-950001',
        'requested_amount' => 100, 'approved_amount' => 100, 'interest_rate' => 2,
        'term_months' => 2, 'start_date' => '2026-07-13', 'first_payment_date' => '2026-08-13',
        'total_amount' => 103, 'current_balance' => 103, 'status' => 'pendiente',
    ]);
    $this->putJson(route('admin.prestamos.update', $pendingLoan), $loanPayload)->assertUnprocessable()
        ->assertJsonPath('message', $message);
    $this->postJson(route('admin.prestamos.approve', $pendingLoan))->assertUnprocessable()
        ->assertJsonPath('message', $message);
    expect($pendingLoan->fresh()->status)->toBe('pendiente');

    $pendingLoan->update(['status' => 'aprobado']);
    $this->postJson(route('admin.prestamos.disburse', $pendingLoan), [
        'payment_method' => 'efectivo', 'disbursed_at' => '2026-07-13',
    ])->assertUnprocessable()->assertJsonPath('message', $message);
    expect($pendingLoan->fresh()->status)->toBe('aprobado')
        ->and($pendingLoan->cashMovement ?? null)->toBeNull();

    $borrower = Member::create([
        'code' => 'SOC-950002', 'first_name' => 'Solicitante', 'full_name' => 'Solicitante vigente',
        'dni' => '95000002', 'admission_date' => '2024-01-01', 'status' => 'vigente',
    ]);
    MemberShare::create([
        'code' => 'APO-950002', 'member_id' => $borrower->id, 'date' => '2025-01-01',
        'amount' => 100, 'share_value' => 20, 'shares_quantity' => 5,
        'payment_method' => 'efectivo', 'status' => 'registrado',
    ]);
    $guarantorPayload = array_merge($loanPayload, [
        'member_id' => $borrower->id, 'guarantor_member_id' => $blocked->id,
        'requested_amount' => 400, 'approved_amount' => 400,
    ]);
    $this->postJson(route('admin.prestamos.store'), $guarantorPayload)->assertUnprocessable()
        ->assertJsonPath('errors.guarantor_member_id.0', LoanEligibilityService::WITHDRAWAL_GUARANTOR_MESSAGE);

    $this->get(route('admin.prestamos.index'))->assertOk()->assertDontSee('SOC-950001');
    $this->get(route('admin.loan-simulations.index'))->assertOk()->assertDontSee('SOC-950001');
});
