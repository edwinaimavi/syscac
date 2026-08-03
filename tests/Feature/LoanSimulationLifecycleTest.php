<?php

use App\Models\Loan;
use App\Models\LoanSimulation;
use App\Models\Member;
use App\Models\MemberShare;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use App\Services\LoanEligibilityService;

function simulationFor(Member $member, string $code, string $status = 'simulada'): LoanSimulation
{
    return LoanSimulation::create([
        'code' => $code, 'member_id' => $member->id, 'simulation_date' => '2026-07-13',
        'amount' => 100, 'interest_rate' => 2, 'interest_type' => 'mensual', 'term_months' => 2,
        'start_date' => '2026-07-13', 'first_payment_date' => '2026-08-13',
        'amortization_method' => 'aleman', 'fixed_principal' => 50, 'total_interest' => 3,
        'total_payment' => 103, 'status' => $status,
    ]);
}

function directLoanPayload(Member $member, ?LoanSimulation $simulation = null): array
{
    return [
        'loan_simulation_id' => $simulation?->id, 'member_id' => $member->id,
        'requested_amount' => 100, 'approved_amount' => 100, 'interest_rate' => 2,
        'term_months' => 2, 'start_date' => '2026-07-13', 'first_payment_date' => '2026-08-13',
        'amortization_method' => 'aleman', 'status' => 'pendiente',
    ];
}

it('requires pending simulations to be resolved before a direct loan and converts one only once', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);
    $member = Member::create(['code' => 'SOC-960001', 'first_name' => 'Socio', 'full_name' => 'Socio Simulacion', 'dni' => '96000001', 'admission_date' => '2024-01-01', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-960001', 'member_id' => $member->id, 'date' => '2025-01-01', 'amount' => 100, 'share_value' => 20, 'shares_quantity' => 5, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    $simulation = simulationFor($member, 'SIM-960001');

    $this->postJson(route('admin.prestamos.store'), directLoanPayload($member))
        ->assertUnprocessable()
        ->assertJsonPath('errors.loan_simulation_id.0', 'Este socio tiene simulaciones pendientes. Tome una simulacion o dejela sin efecto antes de registrar un prestamo directo.');

    $this->postJson(route('admin.prestamos.store'), directLoanPayload($member, $simulation))->assertOk();
    $simulation->refresh();
    expect($simulation->status)->toBe('convertida')
        ->and($simulation->converted_loan_id)->toBe(Loan::first()->id)
        ->and($simulation->converted_by)->toBe($user->id)
        ->and($simulation->converted_at)->not->toBeNull();

    $this->postJson(route('admin.prestamos.store'), directLoanPayload($member, $simulation))
        ->assertUnprocessable()
        ->assertJsonPath('errors.loan_simulation_id.0', 'Esta simulacion ya fue convertida a prestamo y no puede volver a procesarse.');
});

it('stores the mandatory reason when a simulation is left without effect', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);
    $member = Member::create(['code' => 'SOC-960002', 'first_name' => 'Socio', 'full_name' => 'Socio Sin Efecto', 'dni' => '96000002', 'admission_date' => '2024-01-01', 'status' => 'vigente']);
    $simulation = simulationFor($member, 'SIM-960002');

    $this->postJson(route('admin.loan-simulations.without-effect', $simulation), [])->assertUnprocessable();
    $this->postJson(route('admin.loan-simulations.without-effect', $simulation), ['reason' => 'Cliente solicito otro monto.'])->assertOk();

    expect($simulation->fresh()->status)->toBe('sin_efecto')
        ->and($simulation->fresh()->effect_reason)->toBe('Cliente solicito otro monto.')
        ->and($simulation->fresh()->effected_by)->toBe($user->id)
        ->and($simulation->fresh()->effected_at)->not->toBeNull();
});

it('rejects a simulation whose member is retired with a specific message', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    $member = Member::create(['code' => 'SOC-960003', 'first_name' => 'Socio', 'full_name' => 'Socio Retirado', 'dni' => '96000003', 'admission_date' => '2024-01-01', 'retirement_date' => '2026-07-13', 'status' => 'retirado']);
    $simulation = simulationFor($member, 'SIM-960003');

    $this->postJson(route('admin.prestamos.store'), directLoanPayload($member, $simulation))
        ->assertUnprocessable()
        ->assertJsonPath('message', LoanEligibilityService::WITHDRAWAL_LOAN_MESSAGE)
        ->assertJsonPath('errors.member_id.0', LoanEligibilityService::WITHDRAWAL_LOAN_MESSAGE);
});
