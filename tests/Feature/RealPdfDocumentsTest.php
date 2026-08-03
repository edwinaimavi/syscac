<?php

use App\Models\Member;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('streams receipts as real pdf files without browser print html', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());

    $member = Member::create([
        'code' => 'SOC-990001',
        'first_name' => 'Socio',
        'full_name' => 'Socio PDF',
        'dni' => '99000001',
        'admission_date' => '2026-07-01',
        'status' => 'vigente',
    ]);
    $receipt = Receipt::create([
        'receipt_number' => 'REC-990001',
        'receipt_date' => '2026-07-12',
        'member_id' => $member->id,
        'type' => 'aporte_acciones',
        'amount' => 200,
        'payment_method' => 'efectivo',
        'status' => 'registrado',
    ]);

    $response = $this->get(route('admin.recibos.pdf', $receipt))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain('REC-990001.pdf')
        ->and($response->getContent())->toStartWith('%PDF-');
});
