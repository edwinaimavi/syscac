<?php

use App\Models\{AdministrativeFundMovement, CashMovement, Member, MemberShare, SolidarityMovement, User};
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('splits a fifty soles contribution into capital and optional fees', function () {
    $member = Member::create(['code'=>'SOC-BRK-01','first_name'=>'Socio','full_name'=>'Socio Desglose','dni'=>'87654321','status'=>'vigente']);
    $this->postJson(route('admin.acciones.store'), [
        'member_id'=>$member->id, 'date'=>'2026-07-20', 'total_paid'=>50,
        'solidarity_amount'=>5, 'administrative_fee_amount'=>5,
        'payment_method'=>'efectivo', 'status'=>'registrado',
    ])->assertOk();

    $share = MemberShare::firstOrFail();
    expect((float)$share->total_paid)->toBe(50.0)
        ->and((float)$share->share_capital_amount)->toBe(40.0)
        ->and((float)$share->amount)->toBe(40.0)
        ->and((float)$share->solidarity_amount)->toBe(5.0)
        ->and((float)$share->administrative_fee_amount)->toBe(5.0)
        ->and((int)$share->shares_quantity)->toBe(2)
        ->and((float)CashMovement::where('related_type', MemberShare::class)->where('related_id',$share->id)->where('status','registrado')->sum('amount'))->toBe(50.0)
        ->and(CashMovement::where('related_id',$share->id)->where('status','registrado')->count())->toBe(3)
        ->and(SolidarityMovement::where('source_type', MemberShare::class)->where('source_id', $share->id)->count())->toBe(1)
        ->and(AdministrativeFundMovement::where('source_type', MemberShare::class)->where('source_id', $share->id)->count())->toBe(1)
        ->and((float) SolidarityMovement::where('status', 'registrado')->sum('amount'))->toBe(5.0);
    $solidarity = SolidarityMovement::firstOrFail();
    expect($solidarity->cash_movement_id)->toBe(
        CashMovement::where('related_id', $share->id)->where('category', 'solidaridad_aporte')->value('id')
    );
    $this->getJson(route('admin.solidaridad.summary'))->assertOk()->assertJsonPath('balance', '5.00');
});

it('accepts a contribution made entirely of shares', function () {
    $member = Member::create(['code'=>'SOC-BRK-02','first_name'=>'Socio','full_name'=>'Socio Solo Acciones','dni'=>'87654322','status'=>'vigente']);
    $this->postJson(route('admin.acciones.store'), [
        'member_id'=>$member->id, 'date'=>'2026-07-20', 'total_paid'=>500,
        'solidarity_amount'=>0, 'administrative_fee_amount'=>0,
        'payment_method'=>'efectivo', 'status'=>'registrado',
    ])->assertOk();

    $share = MemberShare::firstOrFail();
    expect((float)$share->share_capital_amount)->toBe(500.0)
        ->and((int)$share->shares_quantity)->toBe(25)
        ->and(CashMovement::where('related_id',$share->id)->where('status','registrado')->count())->toBe(1)
        ->and(SolidarityMovement::count())->toBe(0)
        ->and(AdministrativeFundMovement::count())->toBe(0);
});

it('accepts five hundred and ten soles with optional fees', function () {
    $member = Member::create(['code'=>'SOC-BRK-03','first_name'=>'Socio','full_name'=>'Socio Con Cargos','dni'=>'87654323','status'=>'vigente']);
    $this->postJson(route('admin.acciones.store'), [
        'member_id'=>$member->id, 'date'=>'2026-07-20', 'total_paid'=>510,
        'solidarity_amount'=>5, 'administrative_fee_amount'=>5,
        'payment_method'=>'efectivo', 'status'=>'registrado',
    ])->assertOk();

    $share = MemberShare::firstOrFail();
    expect((float)$share->share_capital_amount)->toBe(500.0)
        ->and((int)$share->shares_quantity)->toBe(25);
});

it('rejects capital that does not produce whole shares', function () {
    $member = Member::create(['code'=>'SOC-BRK-04','first_name'=>'Socio','full_name'=>'Socio Invalido','dni'=>'87654324','status'=>'vigente']);
    $this->postJson(route('admin.acciones.store'), [
        'member_id'=>$member->id, 'date'=>'2026-07-20', 'total_paid'=>55,
        'solidarity_amount'=>5, 'administrative_fee_amount'=>5,
        'payment_method'=>'efectivo', 'status'=>'registrado',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.share_capital_amount.0', 'El capital para acciones debe ser múltiplo exacto de S/ 20.00. Ajuste el monto total, solidaridad o gastos administrativos.');
});

it('annuls every generated cash movement with the contribution', function () {
    $member = Member::create(['code'=>'SOC-BRK-05','first_name'=>'Socio','full_name'=>'Socio Anulado','dni'=>'87654325','status'=>'vigente']);
    $this->postJson(route('admin.acciones.store'), [
        'member_id'=>$member->id, 'date'=>'2026-07-20', 'total_paid'=>50,
        'solidarity_amount'=>5, 'administrative_fee_amount'=>5,
        'payment_method'=>'efectivo', 'status'=>'registrado',
    ])->assertOk();
    $share = MemberShare::firstOrFail();
    $this->postJson(route('admin.acciones.annul',$share))->assertOk();
    expect(CashMovement::where('related_id',$share->id)->where('status','anulado')->count())->toBe(3);
    expect(SolidarityMovement::where('source_id', $share->id)->where('status', 'anulado')->count())->toBe(1);
    expect(AdministrativeFundMovement::where('source_id', $share->id)->where('status', 'anulado')->count())->toBe(1);
    $this->getJson(route('admin.solidaridad.summary'))->assertOk()->assertJsonPath('balance', '0.00');
});
