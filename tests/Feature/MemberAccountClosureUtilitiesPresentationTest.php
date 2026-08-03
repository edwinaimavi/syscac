<?php

use App\Models\Member;
use App\Models\MemberShare;
use App\Models\ProfitDistribution;
use App\Models\ProfitDistributionDetail;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('explains when a member has no pending utilities and omits the utility row', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    $member = Member::create(['code' => 'SOC-880001', 'first_name' => 'Socio', 'full_name' => 'Socio Prueba', 'dni' => '88000001', 'admission_date' => '2026-03-01', 'status' => 'vigente']);
    MemberShare::create(['code' => 'APO-880001', 'member_id' => $member->id, 'date' => '2026-03-10', 'amount' => 200, 'share_value' => 20, 'shares_quantity' => 10, 'payment_method' => 'efectivo', 'status' => 'registrado']);

    $payload = $this->postJson(route('admin.retiros-socios.calculate'), ['member_id' => $member->id])->assertOk()->json();
    expect((float) $payload['summary']['pending_utilities_amount'])->toBe(0.0)
        ->and($payload['summary']['utilities_note'])->toContain('Sin utilidades pendientes')
        ->and(collect($payload['details'])->where('item_type', 'utilidad_pendiente'))->toBeEmpty()
        ->and($payload['details'][0]['origin_label'])->toBe('Acciones / Aportes')
        ->and($payload['details'][0]['origin_code'])->toBe('APO-880001')
        ->and($payload['details'][0]['related_label'])->not->toContain('MemberShare');
});

it('shows period months shares and profit per share for a real pending utility', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    $member = Member::create(['code' => 'SOC-880002', 'first_name' => 'Socio', 'full_name' => 'Socio Utilidad', 'dni' => '88000002', 'admission_date' => '2026-03-01', 'status' => 'vigente']);
    $distribution = ProfitDistribution::create(['code' => 'UTI-880001', 'period_year' => 2026, 'start_date' => '2026-04-01', 'end_date' => '2026-07-31', 'total_profit' => 100, 'total_shares' => 200, 'profit_per_share' => .5, 'status' => 'calculado']);
    ProfitDistributionDetail::create(['profit_distribution_id' => $distribution->id, 'member_id' => $member->id, 'shares_quantity' => 40, 'participation_percentage' => 20, 'profit_amount' => 20, 'paid_amount' => 0, 'status' => 'pendiente']);

    $payload = $this->postJson(route('admin.retiros-socios.calculate'), ['member_id' => $member->id])->assertOk()->json();
    $utility = collect($payload['details'])->firstWhere('item_type', 'utilidad_pendiente');
    expect((float) $payload['summary']['pending_utilities_amount'])->toBe(20.0)
        ->and($utility['utility_period'])->toBe('2026')
        ->and($utility['utility_months'])->toBe(4)
        ->and($utility['utility_shares'])->toBe('40.0000')
        ->and($utility['utility_profit_per_share_formatted'])->toBe('S/ 0.50000000')
        ->and($utility['origin_label'])->toBe('Utilidades')
        ->and($utility['origin_code'])->toBe('UTI-880001');
});
