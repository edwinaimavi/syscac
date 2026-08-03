<?php

use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows a retired DNI only as a confirmed reentry and preserves its history', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());

    $previous = Member::create(['code' => 'SOC-771001', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'full_name' => 'Ana Pérez', 'dni' => '77100001', 'birth_date' => '1990-01-01', 'admission_date' => '2024-01-01', 'retirement_date' => '2026-01-01', 'status' => 'retirado']);
    MemberAccountClosure::create(['code' => 'CIE-771001', 'member_id' => $previous->id, 'closure_date' => '2026-01-01', 'retirement_date' => '2026-01-01', 'status' => 'cerrado', 'reason' => 'Retiro']);

    $verification = $this->getJson(url('admin/socios/verificar-dni/77100001'))->assertOk()->json();
    expect($verification['status'])->toBe('reentry')->and($verification['member']['code'])->toBe('SOC-771001');

    $payload = [
        'dni' => '77100001', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '1990-01-01',
        'admission_date' => today()->toDateString(), 'member_type_selected' => 'nuevo', 'civil_status' => 'soltero',
        'status' => 'vigente', 'enrollment_amount' => 50, 'enrollment_date' => today()->toDateString(),
        'enrollment_payment_method' => 'efectivo',
    ];

    $this->postJson(route('admin.socios.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('dni');
    $this->postJson(route('admin.socios.store'), $payload + ['reentry_from_member_id' => $previous->id, 'reentry_confirmed' => '1'])->assertOk();

    $reentry = Member::where('dni', '77100001')->where('id', '!=', $previous->id)->firstOrFail();
    expect($reentry->code)->not->toBe($previous->code)
        ->and($reentry->reentry_from_member_id)->toBe($previous->id)
        ->and($previous->fresh()->status)->toBe('retirado');
});
