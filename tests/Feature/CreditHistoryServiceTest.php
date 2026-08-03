<?php

use App\Models\CreditHistoryEvent;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Services\CreditHistoryService;
use Illuminate\Support\Facades\Gate;

function creditHistoryLoan(int $daysPastDue): array
{
    $member = Member::create(['code' => 'SOC-' . fake()->unique()->numerify('######'), 'first_name' => 'Socio', 'full_name' => 'Socio Historial', 'dni' => fake()->unique()->numerify('########'), 'status' => 'vigente']);
    $loan = Loan::create(['member_id' => $member->id, 'loan_number' => 'PRE-' . fake()->unique()->numerify('######'), 'requested_amount' => 100, 'approved_amount' => 100, 'interest_rate' => 1, 'term_months' => 1, 'start_date' => today()->subMonth(), 'first_payment_date' => today()->subDays($daysPastDue), 'amortization_method' => 'aleman', 'fixed_principal' => 100, 'total_interest' => 1, 'total_amount' => 101, 'current_balance' => 101, 'status' => 'desembolsado']);
    $installment = $loan->installments()->create(['installment_number' => 1, 'due_date' => today()->subDays($daysPastDue), 'opening_balance' => 100, 'principal_amount' => 100, 'interest_amount' => 1, 'installment_amount' => 101, 'remaining_amount' => 101, 'closing_balance' => 0, 'status' => 'vencido']);
    return [$member, $loan, $installment];
}

it('uses five days of tolerance and the real payment date', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    [$member, $loan, $installment] = creditHistoryLoan(5);

    $this->postJson(route('admin.cobros.store'), ['loan_id' => $loan->id, 'payment_date' => today()->toDateString(), 'amount' => 101, 'payment_type' => 'cuota', 'payment_method' => 'efectivo', 'installment_ids' => [$installment->id]])->assertOk();

    $event = CreditHistoryEvent::where('member_id', $member->id)->whereNotNull('loan_payment_id')->firstOrFail();
    expect($event->event_type)->toBe('grace')->and($event->days_late)->toBe(5)
        ->and($member->creditHistory()->value('on_time_payments'))->toBe(1)
        ->and($member->creditHistory()->value('serious_late_payments'))->toBe(0);
});

it('classifies days six to eight as mild and more than eight as serious', function () {
    $service = app(CreditHistoryService::class);
    expect($service->installmentStatus('2026-01-01', '2026-01-07')['event_type'])->toBe('mild_late')
        ->and($service->installmentStatus('2026-01-01', '2026-01-09')['event_type'])->toBe('mild_late')
        ->and($service->installmentStatus('2026-01-01', '2026-01-10')['event_type'])->toBe('serious_late');
});

it('keeps credit score informative and separate from hard loan eligibility rules', function () {
    [$member] = creditHistoryLoan(12);
    $history = app(CreditHistoryService::class)->recalculate($member);
    expect($history->active_overdue_installments)->toBe(1)->and($history->score)->toBeLessThanOrEqual(39)->and($history->status)->toBe('malo')
        ->and($member->canRequestLoan())->toBeTrue();
});

it('exposes the auditable detail and filtered credit report', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    [$member] = creditHistoryLoan(10);
    app(CreditHistoryService::class)->recalculate($member);

    $this->getJson(route('admin.historial-crediticio.show', $member))->assertOk()->assertJsonPath('summary.status', 'malo')->assertJsonCount(1, 'events');
    $this->get(route('admin.reportes.historial-crediticio', ['credit_status' => 'malo', 'has_overdue' => '1']))->assertOk()->assertSee('Historial crediticio')->assertSee($member->full_name);
});
