<?php

use App\Models\Member;
use App\Models\MemberEnrollment;
use App\Models\MemberShare;
use App\Models\MemberAccountClosure;
use App\Models\User;
use App\Services\LoanEligibilityService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function eligibilityMember(array $attributes = []): Member
{
    return Member::create(array_merge([
        'code' => 'SOC-' . fake()->unique()->numerify('######'), 'first_name' => 'Socio', 'last_name' => 'Prueba',
        'full_name' => 'Socio Prueba', 'dni' => fake()->unique()->numerify('########'), 'admission_date' => now(),
        'member_type' => 'nuevo', 'status' => 'vigente',
    ], $attributes));
}

function eligibilityShare(Member $member, float $amount): MemberShare
{
    return MemberShare::create([
        'code' => 'APO-' . fake()->unique()->numerify('######'), 'member_id' => $member->id, 'date' => now(),
        'amount' => $amount, 'share_value' => 10, 'shares_quantity' => $amount / 10,
        'payment_method' => 'efectivo', 'status' => 'registrado',
    ]);
}

it('enforces enrollment and the third contribution for new members', function () {
    $member = eligibilityMember();
    MemberEnrollment::create(['code' => 'INS-000001', 'member_id' => $member->id, 'enrollment_date' => now(), 'amount' => 50, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    eligibilityShare($member, 30);
    eligibilityShare($member, 30);

    expect(fn () => app(LoanEligibilityService::class)->validate($member, 100))->toThrow(ValidationException::class);

    eligibilityShare($member, 40);
    $evaluation = app(LoanEligibilityService::class)->validate($member, 200);
    expect($evaluation['contribution_count'])->toBe(3)
        ->and($evaluation['loan_limit_without_guarantor'])->toBe(200.0)
        ->and($evaluation['requires_guarantor'])->toBeFalse();
});

it('requires a current member guarantor with enough registered contributions', function () {
    $member = eligibilityMember(['code' => 'SOC-100001']);
    MemberEnrollment::create(['code' => 'INS-100001', 'member_id' => $member->id, 'enrollment_date' => now(), 'amount' => 50, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    eligibilityShare($member, 40); eligibilityShare($member, 30); eligibilityShare($member, 30);
    $guarantor = eligibilityMember(['code' => 'SOC-100002', 'admission_date' => now()->subYears(2), 'member_type' => 'antiguo']);
    eligibilityShare($guarantor, 300);

    expect(fn () => app(LoanEligibilityService::class)->validate($member, 400, $guarantor->id))->toThrow(ValidationException::class);
    eligibilityShare($guarantor, 100);
    expect(app(LoanEligibilityService::class)->validate($member, 400, $guarantor->id)['requires_guarantor'])->toBeTrue();
});

it('recalculates guarantor requirement and reports every applicable reason', function () {
    $member = eligibilityMember(['code' => 'SOC-200001']);
    MemberEnrollment::create(['code' => 'INS-200001', 'member_id' => $member->id, 'enrollment_date' => now(), 'amount' => 50, 'payment_method' => 'efectivo', 'status' => 'registrado']);
    eligibilityShare($member, 80); eligibilityShare($member, 60); eligibilityShare($member, 60);

    $service = app(LoanEligibilityService::class);
    expect($service->evaluate($member, 400)['requires_guarantor'])->toBeFalse()
        ->and($service->evaluate($member, 500)['requires_guarantor'])->toBeTrue()
        ->and($service->evaluate($member, 500)['guarantor_requirement_reason'])->toBe('supera_limite_aportes')
        ->and($service->evaluate($member, 7500)['guarantor_requirement_reason'])->toBe('supera_limite_aportes,supera_7000');

    try {
        $service->validate($member, 7500);
        $this->fail('La validacion debio exigir garante.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['guarantor_member_id'][0])
            ->toContain('Este prestamo requiere garante.')
            ->toContain('Supera el limite permitido segun aportes.')
            ->toContain('Supera S/ 7,000.');
    }
});

it('only lists adult current members without a withdrawal process as guarantors', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());

    $eligible = eligibilityMember(['code' => 'SOC-300001', 'birth_date' => now()->subYears(30)]);
    $minor = eligibilityMember(['code' => 'SOC-300002', 'birth_date' => now()->subYears(17)]);
    $pending = eligibilityMember(['code' => 'SOC-300003', 'birth_date' => now()->subYears(25)]);
    $retired = eligibilityMember(['code' => 'SOC-300004', 'birth_date' => now()->subYears(40), 'status' => 'retirado', 'retirement_date' => now()]);

    MemberAccountClosure::create([
        'code' => 'CIE-300003', 'member_id' => $pending->id, 'closure_date' => now(), 'retirement_date' => now(),
        'total_in_favor' => 10, 'total_against' => 50, 'final_balance' => -40,
        'settlement_type' => 'contra_socio', 'reason' => 'Retiro', 'status' => 'pendiente_regularizacion',
    ]);

    expect(Member::eligibleGuarantors()->pluck('id')->all())
        ->toContain($eligible->id)
        ->not->toContain($minor->id, $pending->id, $retired->id);

    $results = $this->getJson(route('admin.avales.buscar-select2'))->assertOk()->json('results');
    expect(collect($results)->pluck('id')->all())
        ->toContain('member:' . $eligible->id)
        ->not->toContain('member:' . $minor->id, 'member:' . $pending->id, 'member:' . $retired->id);
    $this->getJson(route('admin.avales.buscar-select2', ['exclude_member_id' => $eligible->id]))->assertOk()
        ->assertJsonMissing(['id' => 'member:' . $eligible->id]);
    $this->getJson(route('admin.avales.verificar-dni', $minor->dni))->assertUnprocessable()
        ->assertJsonPath('message', LoanEligibilityService::MINOR_GUARANTOR_MESSAGE);

    try {
        app(LoanEligibilityService::class)->assertCanBeGuarantor($minor);
        $this->fail('La validación debió bloquear al garante menor de edad.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['guarantor_member_id'][0])->toBe(LoanEligibilityService::MINOR_GUARANTOR_MESSAGE);
    }

    $borrower = eligibilityMember(['code' => 'SOC-300005', 'birth_date' => now()->subYears(35), 'admission_date' => now()->subYears(2)]);
    eligibilityShare($borrower, 100);
    eligibilityShare($minor, 500);
    $this->postJson(route('admin.prestamos.store'), [
        'member_id' => $borrower->id, 'guarantor_member_id' => $minor->id,
        'requested_amount' => 400, 'approved_amount' => 400, 'interest_rate' => 2,
        'term_months' => 2, 'start_date' => now()->format('Y-m-d'),
        'first_payment_date' => now()->addMonth()->format('Y-m-d'),
        'amortization_method' => 'aleman', 'status' => 'pendiente',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.guarantor_member_id.0', LoanEligibilityService::MINOR_GUARANTOR_MESSAGE);
});
