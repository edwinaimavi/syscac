<?php

use App\Models\CashMovement;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('registers a normal 31 soles installment with schedule snapshots receipt and cash', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    $member = Member::create(['code' => 'SOC-310001', 'first_name' => 'Socio', 'full_name' => 'Socio Prueba', 'dni' => '31000001', 'status' => 'vigente']);
    $loan = Loan::create(['member_id' => $member->id, 'loan_number' => 'PRE-000001', 'requested_amount' => 300, 'approved_amount' => 300, 'interest_rate' => 2, 'term_months' => 12, 'start_date' => today(), 'first_payment_date' => today(), 'amortization_method' => 'aleman', 'fixed_principal' => 25, 'total_interest' => 39, 'total_amount' => 339, 'current_balance' => 339, 'status' => 'desembolsado']);
    $installment = $loan->installments()->create(['installment_number' => 1, 'due_date' => today(), 'opening_balance' => 300, 'principal_amount' => 25, 'interest_amount' => 6, 'installment_amount' => 31, 'remaining_amount' => 31, 'closing_balance' => 275, 'status' => 'pendiente']);
    for ($number = 2; $number <= 12; $number++) $loan->installments()->create(['installment_number' => $number, 'due_date' => today()->addMonths($number - 1), 'opening_balance' => 300 - (($number - 1) * 25), 'principal_amount' => 25, 'interest_amount' => 0, 'installment_amount' => 25, 'remaining_amount' => 25, 'closing_balance' => 300 - ($number * 25), 'status' => 'pendiente']);

    $response = $this->postJson(route('admin.cobros.store'), ['loan_id' => $loan->id, 'payment_date' => today()->toDateString(), 'amount' => 31, 'payment_type' => 'cuota', 'payment_method' => 'efectivo', 'installment_ids' => [$installment->id]]);
    $response->assertOk();
    $payment = LoanPayment::firstOrFail();
    $installment->refresh();
    expect($installment->status)->toBe('pagado')->and((float) $installment->capital_paid)->toBe(25.0)->and((float) $installment->interest_paid)->toBe(6.0)->and((float) $installment->paid_amount)->toBe(31.0)
        ->and($payment->schedule_before)->not->toBeNull()->and($payment->schedule_after)->not->toBeNull()
        ->and(Receipt::where('related_id', $payment->id)->exists())->toBeTrue()
        ->and((float) CashMovement::where('related_id', $payment->id)->where('type', 'ingreso')->value('amount'))->toBe(31.0);

    $detail = $this->getJson(route('admin.prestamos.show', $loan))->assertOk()->json();
    expect($detail['financial_summary']['total_paid'])->toBe(31)
        ->and($detail['financial_summary']['capital_paid_formatted'])->toBe('S/ 25.00')
        ->and($detail['financial_summary']['interest_paid_formatted'])->toBe('S/ 6.00')
        ->and($detail['related_payments'])->toHaveCount(1);

    $this->get(route('admin.prestamos.schedule.print', $loan))->assertOk()->assertSee('Total proyectado original')->assertSee('Total pagado real')->assertSee('Interes exonerado');
    $pdfResponse = $this->get(route('admin.prestamos.schedule.pdf', $loan))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($pdfResponse->getContent(), 0, 4))->toBe('%PDF')
        ->and($pdfResponse->headers->get('content-disposition'))->toContain('Cronograma PRE-000001.pdf');
});

it('classifies an installment advance as a loan collection in cash', function () {
    $method = new ReflectionMethod(\App\Http\Controllers\Admin\LoanPaymentController::class, 'cashCategory');
    $controller = app(\App\Http\Controllers\Admin\LoanPaymentController::class);
    expect($method->invoke($controller, 'adelanto_cuotas'))->toBe('cobro_prestamo')
        ->and($method->invoke($controller, 'liquidacion'))->toBe('liquidacion_prestamo');
});
