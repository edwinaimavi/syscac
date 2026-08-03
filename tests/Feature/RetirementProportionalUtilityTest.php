<?php

use App\Http\Controllers\Admin\ProfitDistributionController;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberShare;
use App\Models\ProfitSource;
use App\Services\RetirementUtilityService;

it('uses only closed productive months and preserves the right when no real profit exists', function () {
    $member = Member::create(['code' => 'SOC-RET-01', 'first_name' => 'Retiro', 'full_name' => 'Socio Retiro', 'dni' => '91000001', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-RET-01', 'member_id' => $member->id, 'date' => '2026-03-15', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);

    $result = app(RetirementUtilityService::class)->calculate($member, '2026-07-15', 'pending');

    expect($result['productive_months'])->toBe(3)
        ->and($result['action_month'])->toBe(30.0)
        ->and($result['available'])->toBe(0.0)
        ->and($result['paid_now'])->toBe(0)
        ->and($result['status'])->toBe('pendiente_cierre_anual');
});

it('calculates provisional profit proportionally and excludes a liquidated right from annual distribution', function () {
    $member = Member::create(['code' => 'SOC-RET-02', 'first_name' => 'Retiro', 'full_name' => 'Socio Provisional', 'dni' => '91000002', 'status' => 'retirado', 'retirement_date' => '2026-07-15']);
    $other = Member::create(['code' => 'SOC-RET-03', 'first_name' => 'Otro', 'full_name' => 'Socio Otro', 'dni' => '91000003', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-RET-02', 'member_id' => $member->id, 'date' => '2026-03-15', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    MemberShare::create(['code' => 'APO-RET-03', 'member_id' => $other->id, 'date' => '2026-01-15', 'amount' => 100, 'share_value' => 10, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    ProfitSource::create(['code' => 'UFM-RET-01', 'source_date' => '2026-06-30', 'amount' => 800, 'reason' => 'Utilidad real', 'status' => 'activo']);

    $result = app(RetirementUtilityService::class)->calculate($member, '2026-07-15', 'provisional');
    expect($result['action_month'])->toBe(30.0)->and($result['total_action_month'])->toBe(80.0)->and($result['paid_now'])->toBe(300.0);

    MemberAccountClosure::create(['code' => 'CIE-RET-01', 'member_id' => $member->id, 'closure_date' => '2026-07-15', 'retirement_date' => '2026-07-15', 'total_in_favor' => 400, 'total_against' => 0, 'final_balance' => 400, 'settlement_type' => 'favor_socio', 'reason' => 'Retiro', 'status' => 'cerrado', 'utility_mode' => 'provisional', 'utility_status' => 'liquidada', 'utility_period_year' => 2026, 'utility_paid_now' => 300]);

    $method = new ReflectionMethod(ProfitDistributionController::class, 'calculationPayload');
    $annual = $method->invoke(app(ProfitDistributionController::class), 500, '2026-01-01', '2026-12-31');
    expect(collect($annual['details'])->pluck('member_id'))->not->toContain($member->id)->toContain($other->id);
});
