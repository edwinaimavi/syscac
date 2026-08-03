<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\CreditHistoryService;

class CreditHistoryController extends Controller
{
    public function __construct(private readonly CreditHistoryService $service)
    {
        $this->middleware('can:credit-history.show')->only('show');
        $this->middleware('can:credit-history.recalculate')->only('recalculate');
    }

    public function show(Member $member)
    {
        $summary = $this->service->summary($member);
        $events = $member->creditHistoryEvents()->with(['loan', 'installment', 'payment.creator', 'creator'])->latest('payment_date')->latest('id')->get()->map(fn ($event) => [
            'type' => $event->event_type,
            'loan' => $event->loan?->loan_number,
            'installment' => $event->installment?->installment_number,
            'due_date' => optional($event->due_date)->format('d/m/Y'),
            'payment_date' => optional($event->payment_date)->format('d/m/Y'),
            'registered_at' => optional($event->registered_at)->format('d/m/Y H:i'),
            'registered_by' => $event->payment?->creator?->name ?? $event->creator?->name ?? 'Sistema',
            'days_late' => $event->days_late,
            'amount' => 'S/ ' . number_format((float) $event->amount, 2),
            'observation' => $event->observation,
        ]);

        return response()->json(compact('summary', 'events'));
    }

    public function recalculate(Member $member)
    {
        $this->service->recalculate($member);
        return response()->json(['message' => 'Historial crediticio recalculado correctamente.', 'summary' => $this->service->summary($member)]);
    }
}
