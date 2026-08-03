<?php

use App\Http\Controllers\Admin\ProfitDistributionController;
use App\Models\Member;
use App\Models\Activity;
use App\Models\MemberShare;
use App\Models\ProfitDistribution;
use App\Models\ProfitSource;
use App\Models\MemberAccountClosure;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

it('starts producing profit in the month after each contribution', function () {
    $member = Member::create(['code' => 'SOC-900001', 'first_name' => 'Socio', 'full_name' => 'Socio Prueba', 'dni' => '90000001', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-900001', 'member_id' => $member->id, 'date' => '2026-03-15', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);

    $method = new ReflectionMethod(ProfitDistributionController::class, 'calculationPayload');
    $result = $method->invoke(app(ProfitDistributionController::class), 400, '2026-01-01', '2026-07-31');

    expect($result['details'][0]['actions_considered'])->toBe(10.0)
        ->and($result['details'][0]['months_considered'])->toBe(4)
        ->and($result['details'][0]['action_month'])->toBe(40.0)
        ->and($result['summary']['total_action_month'])->toBe(40.0)
        ->and($result['summary']['profit_per_action_month'])->toBe(10.0)
        ->and($result['details'][0]['profit_amount'])->toBe(400.0)
        ->and($result['details'][0]['calculation_breakdown'][0]['effective_from'])->toBe('2026-04-01');
});

it('sums every contribution as action month and limits a retired member to the retirement month', function () {
    $active = Member::create(['code' => 'SOC-900002', 'first_name' => 'Activo', 'full_name' => 'Socio Activo', 'dni' => '90000002', 'status' => 'vigente']);
    $retired = Member::create(['code' => 'SOC-900003', 'first_name' => 'Retirado', 'full_name' => 'Socio Retirado', 'dni' => '90000003', 'status' => 'retirado', 'retirement_date' => '2026-08-15']);
    MemberShare::create(['code' => 'APO-900002', 'member_id' => $active->id, 'date' => '2026-01-10', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    MemberShare::create(['code' => 'APO-900003', 'member_id' => $active->id, 'date' => '2026-05-10', 'amount' => 50, 'share_value' => 10, 'shares_quantity' => 5, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    MemberShare::create(['code' => 'APO-900004', 'member_id' => $retired->id, 'date' => '2025-12-10', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    MemberShare::create(['code' => 'APO-ANULADO', 'member_id' => $active->id, 'date' => '2025-12-10', 'amount' => 999, 'share_value' => 10, 'shares_quantity' => 99.9, 'payment_method' => 'efectivo', 'status' => 'anulado']);
    MemberAccountClosure::create(['code' => 'CIE-900002', 'member_id' => $active->id, 'closure_date' => '2026-08-01', 'retirement_date' => '2026-08-01', 'total_in_favor' => 100, 'total_against' => 150, 'final_balance' => -50, 'settlement_type' => 'contra_socio', 'reason' => 'Retiro', 'status' => 'pendiente_regularizacion']);

    $method = new ReflectionMethod(ProfitDistributionController::class, 'calculationPayload');
    $result = $method->invoke(app(ProfitDistributionController::class), 1000, '2026-01-01', '2026-12-31');
    $activeRow = collect($result['details'])->firstWhere('member_id', $active->id);
    $retiredRow = collect($result['details'])->firstWhere('member_id', $retired->id);

    expect($activeRow['actions_considered'])->toBe(15.0)
        ->and($activeRow['action_month'])->toBe(145.0)
        ->and($activeRow['contributions_count'])->toBe(2)
        ->and($activeRow['member_status'])->toBe('pendiente_regularizacion')
        ->and($activeRow['member_status_label'])->toBe('Pendiente de regularización')
        ->and($retiredRow['months_considered'])->toBe(7)
        ->and($retiredRow['action_month'])->toBe(70.0)
        ->and($result['summary']['total_action_month'])->toBe(215.0)
        ->and(round(collect($result['details'])->sum('profit_amount'), 2))->toBe(1000.0);
});

it('rejects a period without valid productive contributions', function () {
    $member = Member::create(['code' => 'SOC-900004', 'first_name' => 'Socio', 'full_name' => 'Sin acción mes', 'dni' => '90000004', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-900005', 'member_id' => $member->id, 'date' => '2026-12-15', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    $method = new ReflectionMethod(ProfitDistributionController::class, 'calculationPayload');

    expect(fn () => $method->invoke(app(ProfitDistributionController::class), 100, '2026-01-01', '2026-12-31'))
        ->toThrow(ValidationException::class, 'No hay aportes válidos para calcular utilidades en este periodo.');
});

it('recalculates on save and persists action month and audit fields', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);
    $member = Member::create(['code' => 'SOC-900005', 'first_name' => 'Auditable', 'full_name' => 'Socio Auditable', 'dni' => '90000005', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-900006', 'member_id' => $member->id, 'date' => '2026-05-10', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    Activity::create(['code' => 'ACT-900001', 'name' => 'Actividad rentable', 'activity_date' => '2026-06-01', 'profit' => 1000, 'status' => 'cerrada']);

    $this->postJson(route('admin.utilidades.store'), [
        'period_year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'total_profit' => 700, 'status' => 'calculado',
    ])->assertOk();

    $distribution = ProfitDistribution::with('details')->firstOrFail();
    $detail = $distribution->details->first();
    expect((float) $distribution->total_action_month)->toBe(70.0)
        ->and((float) $distribution->profit_per_action_month)->toBe(10.0)
        ->and($distribution->calculated_by)->toBe($user->id)
        ->and($distribution->calculated_at)->not->toBeNull()
        ->and((float) $detail->actions_considered)->toBe(10.0)
        ->and($detail->months_considered)->toBe(7)
        ->and((float) $detail->action_month)->toBe(70.0)
        ->and($detail->calculation_breakdown)->toHaveCount(1);
    expect($distribution->start_date?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($distribution->end_date?->format('Y-m-d'))->toBe('2026-12-31');

    $this->postJson(route('admin.utilidades.approve', $distribution))->assertOk();
    expect($distribution->fresh()->approved_by)->toBe($user->id)
        ->and($distribution->fresh()->approved_at)->not->toBeNull()
        ->and($distribution->fresh()->status)->toBe('aprobado');
});

it('blocks amounts above real available profit and preserves the remainder', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    Activity::create(['code' => 'ACT-900002', 'name' => 'Fuente cerrada', 'activity_date' => '2026-01-10', 'profit' => 1000, 'status' => 'cerrada']);
    $member = Member::create(['code' => 'SOC-900006', 'first_name' => 'Financiero', 'full_name' => 'Socio Financiero', 'dni' => '90000006', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-900007', 'member_id' => $member->id, 'date' => '2025-12-10', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);

    $payload = ['period_year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'calculado'];
    $this->postJson(route('admin.utilidades.store'), $payload + ['total_profit' => 800])->assertOk();

    $this->getJson(route('admin.utilidades.availability'))->assertOk()
        ->assertJsonPath('generated', 1000)
        ->assertJsonPath('distributed', 800)
        ->assertJsonPath('available', 200);

    $this->postJson(route('admin.utilidades.store'), $payload + ['total_profit' => 201])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('total_profit')
        ->assertJsonPath('errors.total_profit.0', 'No puede distribuir S/ 201.00 porque la utilidad disponible es S/ 200.00.');
});

it('does not treat contributions as available profit and audits manual sources', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);
    $member = Member::create(['code' => 'SOC-900007', 'first_name' => 'Aportante', 'full_name' => 'Socio Aportante', 'dni' => '90000007', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-900008', 'member_id' => $member->id, 'date' => '2025-12-10', 'amount' => 5000, 'share_value' => 10, 'shares_quantity' => 500, 'payment_method' => 'efectivo', 'status' => 'registrado']);

    $this->getJson(route('admin.utilidades.availability'))
        ->assertJsonPath('available', 0)
        ->assertJsonPath('contributions_total', 5000);

    $this->postJson(route('admin.utilidades.sources.store'), [
        'source_date' => '2026-07-13', 'amount' => 600, 'reason' => 'Saldo inicial auditado', 'observation' => 'Aprobado por asamblea',
    ])->assertOk();

    $source = ProfitSource::firstOrFail();
    expect($source->created_by)->toBe($user->id)->and($source->status)->toBe('activo');
    $this->getJson(route('admin.utilidades.availability'))->assertJsonPath('available', 600);

    $this->postJson(route('admin.utilidades.sources.annul', $source))->assertOk();
    expect($source->fresh()->status)->toBe('anulado')
        ->and($source->fresh()->annulled_by)->toBe($user->id)
        ->and($source->fresh()->annulled_at)->not->toBeNull();
    $this->getJson(route('admin.utilidades.availability'))->assertJsonPath('available', 0);
});
