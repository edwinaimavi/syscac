<?php

use App\Models\{Member, MemberBeneficiary, User};
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

function beneficiaryMemberData(array $beneficiaries): array
{
    return [
        'first_name' => 'Rosa', 'last_name' => 'Beneficiaria', 'dni' => '71234567',
        'birth_date' => '1985-01-01', 'admission_date' => '2020-01-01',
        'member_type_selected' => 'antiguo', 'civil_status' => 'casado',
        'status' => 'vigente', 'beneficiaries' => $beneficiaries,
    ];
}

it('stores spouse and children when their distribution totals one hundred percent', function () {
    $this->postJson(route('admin.socios.store'), beneficiaryMemberData([
        ['full_name'=>'Esposa','dni'=>'71234568','relationship'=>'conyuge','percentage'=>50],
        ['full_name'=>'Hijo 1','dni'=>'71234569','relationship'=>'hijo','percentage'=>25],
        ['full_name'=>'Hijo 2','dni'=>'71234570','relationship'=>'hijo','percentage'=>25],
    ]))->assertOk();
    expect(MemberBeneficiary::count())->toBe(3)
        ->and((float) MemberBeneficiary::sum('percentage'))->toBe(100.0);
});

it('rejects a distribution below one hundred percent', function () {
    $this->postJson(route('admin.socios.store'), beneficiaryMemberData([
        ['full_name'=>'Esposa','relationship'=>'conyuge','percentage'=>50],
        ['full_name'=>'Hijo','relationship'=>'hijo','percentage'=>20],
    ]))->assertUnprocessable()->assertJsonValidationErrors('beneficiaries');
    expect(Member::count())->toBe(0);
});

it('rejects a distribution above one hundred percent', function () {
    $this->postJson(route('admin.socios.store'), beneficiaryMemberData([
        ['full_name'=>'Esposa','relationship'=>'conyuge','percentage'=>80],
        ['full_name'=>'Hijo','relationship'=>'hijo','percentage'=>40],
    ]))->assertUnprocessable()->assertJsonValidationErrors('beneficiaries');
});

it('rejects duplicate beneficiary dni values', function () {
    $this->postJson(route('admin.socios.store'), beneficiaryMemberData([
        ['full_name'=>'Persona 1','dni'=>'71234568','relationship'=>'otro','percentage'=>50],
        ['full_name'=>'Persona 2','dni'=>'71234568','relationship'=>'otro','percentage'=>50],
    ]))->assertUnprocessable()->assertJsonValidationErrors('beneficiaries.1.dni');
});

it('updates existing beneficiaries and returns them in member detail', function () {
    $this->postJson(route('admin.socios.store'), beneficiaryMemberData([
        ['full_name'=>'Esposa','dni'=>'71234568','relationship'=>'conyuge','percentage'=>100],
    ]))->assertOk();
    $member = Member::firstOrFail();
    $beneficiary = $member->beneficiaries()->firstOrFail();

    $payload = beneficiaryMemberData([
        ['id'=>$beneficiary->id,'full_name'=>'Esposa','dni'=>'71234568','relationship'=>'conyuge','percentage'=>60],
        ['full_name'=>'Hijo','dni'=>'71234569','relationship'=>'hijo','percentage'=>40],
    ]);
    $this->putJson(route('admin.socios.update', $member), $payload)->assertOk();

    expect($member->beneficiaries()->count())->toBe(2)
        ->and((float) $beneficiary->fresh()->percentage)->toBe(60.0);
    $this->getJson(route('admin.socios.show', $member))->assertOk()
        ->assertJsonPath('beneficiaries.0.full_name', 'Esposa')
        ->assertJsonPath('beneficiaries.1.full_name', 'Hijo');
});
