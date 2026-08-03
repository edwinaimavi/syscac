<?php

use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MemberAccountClosure;
use App\Models\MemberShare;
use App\Models\LoanSimulation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

it('keeps a calculated member active and retires them only when the closure is confirmed', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);
    Storage::fake('public');

    $member = Member::create([
        'code' => 'SOC-970001', 'first_name' => 'Socio', 'full_name' => 'Socio Retiro',
        'dni' => '97000001', 'admission_date' => '2026-07-01', 'status' => 'vigente',
    ]);
    MemberShare::create([
        'code' => 'APO-970001', 'member_id' => $member->id, 'date' => '2026-07-02',
        'amount' => 200, 'share_value' => 20, 'shares_quantity' => 10,
        'payment_method' => 'efectivo', 'status' => 'registrado',
    ]);
    $pendingSimulation = LoanSimulation::create([
        'code' => 'SIM-970001', 'member_id' => $member->id, 'simulation_date' => '2026-07-10',
        'amount' => 100, 'interest_rate' => 2, 'term_months' => 2, 'start_date' => '2026-07-10',
        'first_payment_date' => '2026-08-10', 'amortization_method' => 'aleman', 'status' => 'simulada',
    ]);
    CashMovement::create([
        'movement_number' => 'MOV-970001', 'movement_date' => '2026-07-01', 'type' => 'ingreso',
        'category' => 'saldo_inicial', 'concept' => 'Saldo para prueba', 'amount' => 500,
        'payment_method' => 'efectivo', 'status' => 'registrado', 'created_by' => $user->id,
    ]);

    $this->postJson(route('admin.retiros-socios.store'), [
        'member_id' => $member->id, 'closure_date' => '2026-07-12',
        'retirement_date' => '2026-07-12', 'reason' => 'Retiro voluntario',
    ])->assertOk();

    $closure = MemberAccountClosure::where('member_id', $member->id)->firstOrFail();
    expect($closure->status)->toBe('calculado')
        ->and($member->fresh()->status)->toBe('vigente')
        ->and($closure->receipt_id)->toBeNull()
        ->and($closure->cashMovement)->toBeNull();
    $this->get(route('admin.retiros-socios.report', $closure))
        ->assertOk()->assertSee('Calculo preliminar de retiro')->assertSee('No valido como constancia definitiva');

    $this->postJson(route('admin.retiros-socios.close', $closure), [
        'payment_method' => 'efectivo',
        'voucher_path' => UploadedFile::fake()->image('comprobante.jpg'),
    ])->assertOk();

    $closure->refresh();
    expect($closure->status)->toBe('cerrado')
        ->and($closure->closed_by)->toBe($user->id)
        ->and($closure->closed_at)->not->toBeNull()
        ->and($closure->receipt_id)->not->toBeNull()
        ->and($member->fresh()->status)->toBe('retirado')
        ->and($member->fresh()->retirement_date?->format('Y-m-d'))->toBe('2026-07-12')
        ->and($closure->cashMovement?->type)->toBe('egreso')
        ->and((float) $closure->cashMovement?->amount)->toBe(200.0)
        ->and($closure->cashMovement?->concept)->toContain($closure->code);
    expect($pendingSimulation->fresh()->status)->toBe('sin_efecto')
        ->and($pendingSimulation->fresh()->effect_reason)->toBe('Socio retirado / cierre de cuenta confirmado.')
        ->and($pendingSimulation->fresh()->effected_by)->toBe($user->id)
        ->and($pendingSimulation->fresh()->effected_at)->not->toBeNull();
    $closureDetail = $this->getJson(route('admin.retiros-socios.show', $closure))->assertOk()->json();
    expect($closureDetail['status_label'])->toBe('Confirmado')
        ->and($closureDetail['calculated_by_name'])->toBe($user->name)
        ->and($closureDetail['confirmed_by_name'])->toBe($user->name)
        ->and($closureDetail['receipt_generated'])->toBeTrue()
        ->and($closureDetail['receipt_number'])->not->toBeNull()
        ->and($closureDetail['cash_movement_generated'])->toBeTrue()
        ->and($closureDetail['cash_movement']['type_label'])->toBe('Egreso')
        ->and($closureDetail['cash_movement']['number'])->toBe($closure->cashMovement?->movement_number)
        ->and($closureDetail['cash_movement']['url'])->toContain('movement_id=')
        ->and($closureDetail['voucher_exists'])->toBeTrue()
        ->and($closureDetail['voucher_type'])->toBe('image')
        ->and($closureDetail['voucher_view_url'])->toContain('/comprobante/ver')
        ->and($closureDetail['status_message'])->toContain('Cierre confirmado correctamente');
    $this->get(route('admin.retiros-socios.voucher.view', $closure))->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->get(route('admin.retiros-socios.report', $closure))
        ->assertOk()->assertSee('Constancia de retiro y cierre de cuenta')->assertSee('Trazabilidad del cierre')->assertDontSee('No valido como constancia definitiva')->assertDontSee('window.print');
    $closurePdf = $this->get(route('admin.retiros-socios.pdf', $closure))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($closurePdf->getContent())->toStartWith('%PDF-');

    $memberDetail = $this->getJson(route('admin.socios.show', $member))->assertOk()->json();
    expect($memberDetail['account_closure']['code'])->toBe($closure->code)
        ->and($memberDetail['account_closure']['final_balance_formatted'])->toBe('S/ 200.00')
        ->and($memberDetail['account_closure']['constancy_url'])->toContain('/retiros-socios/' . $closure->id . '/pdf');

    $memberList = $this->getJson(route('admin.socios.list'))->assertOk()->getContent();
    expect($memberList)->not->toContain('editMember')
        ->not->toContain('retireMember')
        ->toContain('Ver constancia de retiro')
        ->toContain('No se puede eliminar porque el socio tiene historial y cierre de cuenta confirmado.');

    $this->putJson(route('admin.socios.update', $member), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.member.0', 'Este socio se encuentra retirado y no puede realizar nuevas operaciones.');
    $this->postJson(route('admin.acciones.store'), [
        'member_id' => $member->id, 'date' => '2026-07-13', 'amount' => 20,
        'share_value' => 20, 'payment_method' => 'efectivo', 'status' => 'registrado',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.member_id.0', 'Este socio se encuentra retirado y no puede realizar nuevas operaciones.');
    $this->deleteJson(route('admin.socios.destroy', $member))->assertUnprocessable()
        ->assertJsonPath('message', 'No se puede eliminar porque el socio tiene historial y cierre de cuenta confirmado.');
    expect(Route::has('admin.socios.retire'))->toBeFalse();

    $this->postJson(route('admin.retiros-socios.annul', $closure), [
        'annulment_reason' => 'Solicitud documentada del socio',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Este cierre ya fue confirmado y no puede modificarse.');
    expect($closure->fresh()->status)->toBe('cerrado')
        ->and($member->fresh()->status)->toBe('retirado');

    $this->getJson(route('admin.retiros-socios.edit', $closure))->assertUnprocessable()
        ->assertJsonPath('message', 'Este cierre ya fue confirmado y no puede modificarse.');
    $this->putJson(route('admin.retiros-socios.update', $closure), [
        'closure_date' => '2026-07-13', 'retirement_date' => '2026-07-13', 'reason' => 'Cambio',
    ])->assertUnprocessable()->assertJsonPath('message', 'Este cierre ya fue confirmado y no puede modificarse.');
    $this->deleteJson(route('admin.retiros-socios.destroy', $closure))->assertUnprocessable()
        ->assertJsonPath('message', 'Este cierre ya fue confirmado y no puede modificarse.');
});

it('blocks confirmation and keeps the member active when the closure has a balance against them', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);

    $member = Member::create([
        'code' => 'SOC-970002', 'first_name' => 'Socio', 'full_name' => 'Socio con deuda',
        'dni' => '97000002', 'admission_date' => '2026-01-01', 'status' => 'vigente',
    ]);
    MemberShare::create([
        'code' => 'APO-970002', 'member_id' => $member->id, 'date' => '2026-01-02',
        'amount' => 50, 'share_value' => 10, 'shares_quantity' => 5,
        'payment_method' => 'efectivo', 'status' => 'registrado',
    ]);
    $loan = Loan::create([
        'member_id' => $member->id, 'loan_number' => 'PRE-970002',
        'requested_amount' => 100, 'approved_amount' => 100, 'interest_rate' => 0,
        'term_months' => 1, 'start_date' => '2026-06-01', 'first_payment_date' => '2026-06-30',
        'total_amount' => 100, 'current_balance' => 100, 'status' => 'desembolsado',
    ]);
    LoanInstallment::create([
        'loan_id' => $loan->id, 'installment_number' => 1, 'due_date' => '2026-06-30',
        'opening_balance' => 100, 'principal_amount' => 100, 'interest_amount' => 0,
        'installment_amount' => 100, 'remaining_amount' => 100, 'closing_balance' => 0,
        'status' => 'pendiente',
    ]);

    $this->postJson(route('admin.retiros-socios.store'), [
        'member_id' => $member->id, 'closure_date' => '2026-07-13',
        'retirement_date' => '2026-07-13', 'reason' => 'Solicitud de retiro',
    ])->assertOk();
    $closure = MemberAccountClosure::where('member_id', $member->id)->firstOrFail();

    expect((float) $closure->final_balance)->toBe(-50.0);
    $this->postJson(route('admin.retiros-socios.close', $closure), [
        'payment_method' => 'transferencia', 'payment_reference' => 'INTENTO-001',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'No se puede confirmar el retiro porque el socio mantiene saldo pendiente en contra.')
        ->assertJsonPath('errors.member_id.0', 'No se puede confirmar el retiro porque el socio mantiene saldo pendiente en contra.');

    $closure->refresh();
    expect($closure->status)->toBe('pendiente_regularizacion')
        ->and($closure->closed_by)->toBeNull()
        ->and($closure->closed_at)->toBeNull()
        ->and($closure->cashMovement)->toBeNull()
        ->and($closure->receipt_id)->toBeNull()
        ->and($member->fresh()->status)->toBe('vigente')
        ->and($loan->fresh()->status)->toBe('desembolsado');

    $payload = $this->getJson(route('admin.retiros-socios.show', $closure))->assertOk()->json();
    expect($payload['confirmation_scenario'])->toBe('saldo_en_contra')
        ->and($payload['can_confirm'])->toBeFalse()
        ->and($payload['status_label'])->toBe('Pendiente de regularización')
        ->and($payload['status_message'])->toBe('El socio tiene saldo en contra. No se puede confirmar el retiro hasta regularizar la deuda pendiente.')
        ->and($payload['cash_movement_generated'])->toBeFalse()
        ->and($payload['receipt_generated'])->toBeFalse();
    $list = $this->getJson(route('admin.retiros-socios.list'))->assertOk()->json();
    expect($list['data'][0]['status'])->toContain('Pendiente de regularización');
    $this->getJson(route('admin.retiros-socios.summary'))->assertOk()
        ->assertJsonPath('retired_members', 0)
        ->assertJsonPath('pending_collect', '50.00');

    $otherMember = Member::create([
        'code' => 'SOC-970099', 'first_name' => 'Otro', 'full_name' => 'Otro socio',
        'dni' => '97000099', 'admission_date' => '2026-01-01', 'status' => 'vigente',
    ]);
    $this->putJson(route('admin.retiros-socios.update', $closure), [
        'member_id' => $otherMember->id, 'closure_date' => '2026-07-13',
        'retirement_date' => '2026-07-13', 'reason' => 'Intento de cambio',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.member_id.0', 'El socio de un cierre existente no puede modificarse.');
    expect($closure->fresh()->member_id)->toBe($member->id);

    $loan->update(['status' => 'pagado', 'current_balance' => 0]);
    $loan->installments()->update(['status' => 'pagado', 'remaining_amount' => 0, 'paid_amount' => 100]);
    $this->putJson(route('admin.retiros-socios.update', $closure), [
        'closure_date' => '2026-07-13', 'retirement_date' => '2026-07-13',
        'reason' => 'Solicitud de retiro', 'observation' => 'Deuda regularizada',
    ])->assertOk();

    $closure->refresh();
    expect($closure->member_id)->toBe($member->id)
        ->and($closure->status)->toBe('calculado')
        ->and((float) $closure->final_balance)->toBe(50.0);
    $this->getJson(route('admin.retiros-socios.show', $closure))->assertOk()
        ->assertJsonPath('can_confirm', true)
        ->assertJsonPath('status_label', 'Calculado');
});

it('confirms a zero-balance closure without payment data, cash movement, or receipt', function () {
    Gate::before(fn () => true);
    $user = User::factory()->create();
    $this->actingAs($user);

    $member = Member::create([
        'code' => 'SOC-970003', 'first_name' => 'Socio', 'full_name' => 'Socio saldo cero',
        'dni' => '97000003', 'admission_date' => '2026-07-01', 'status' => 'vigente',
    ]);
    $this->postJson(route('admin.retiros-socios.store'), [
        'member_id' => $member->id, 'closure_date' => '2026-07-13',
        'retirement_date' => '2026-07-13', 'reason' => 'Solicitud de retiro',
    ])->assertOk();
    $closure = MemberAccountClosure::where('member_id', $member->id)->firstOrFail();

    $payload = $this->getJson(route('admin.retiros-socios.show', $closure))->assertOk()->json();
    expect($payload['confirmation_scenario'])->toBe('saldo_cero')
        ->and($payload['can_confirm'])->toBeTrue();

    $this->postJson(route('admin.retiros-socios.close', $closure), [])->assertOk();

    $closure->refresh();
    expect($closure->status)->toBe('cerrado')
        ->and($closure->payment_method)->toBeNull()
        ->and($closure->payment_reference)->toBeNull()
        ->and($closure->cashMovement)->toBeNull()
        ->and($closure->receipt_id)->toBeNull()
        ->and($closure->closed_by)->toBe($user->id)
        ->and($closure->closed_at)->not->toBeNull()
        ->and($member->fresh()->status)->toBe('retirado');
    $this->getJson(route('admin.retiros-socios.summary'))->assertOk()
        ->assertJsonPath('retired_members', 1)
        ->assertJsonPath('pending_collect', '0.00');
});
