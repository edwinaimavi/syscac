<?php

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\CreditHistoryEvent;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditHistoryService
{
    public function __construct(private readonly LateFeeService $lateFees) {}
    public function recalculate(Member|int $member): CreditHistory
    {
        $member = $member instanceof Member ? $member : Member::withTrashed()->findOrFail($member);
        $member->load(['accountClosures', 'loans' => fn ($query) => $query->withTrashed()->with([
            'installments' => fn ($query) => $query->withTrashed()->orderBy('installment_number'),
            'payments' => fn ($query) => $query->withTrashed()->with(['details.installment', 'creator']),
        ])]);

        return DB::transaction(function () use ($member) {
            $events = [];
            $validLoans = $member->loans->filter(fn ($loan) => $loan->status !== 'anulado' && ! $loan->trashed());
            $paidDates = [];
            $totalPaid = 0.0;

            foreach ($validLoans as $loan) {
                foreach ($loan->payments->filter(fn ($payment) => $payment->status === 'registrado' && $payment->affects_credit_history && ! $payment->trashed()) as $payment) {
                    $totalPaid += (float) $payment->amount;
                    foreach ($payment->details as $detail) {
                        $installment = $detail->installment;
                        $daysLate = (int) ($detail->late_fee_days ?: ($installment?->due_date
                            ? max(0, (int) Carbon::parse($installment->due_date)->diffInDays(Carbon::parse($payment->payment_date), false))
                            : 0));
                        $eventType = (float) $detail->late_fee_paid > 0 || (float) $detail->late_fee_waived > 0
                            ? ($daysLate <= 8 ? 'mild_late' : 'serious_late')
                            : $this->paymentEventType($installment?->due_date, $payment->payment_date, $payment->payment_type);

                        $events[] = [
                            'member_id' => $member->id,
                            'loan_id' => $loan->id,
                            'loan_installment_id' => $detail->loan_installment_id,
                            'loan_payment_id' => $payment->id,
                            'event_type' => $eventType,
                            'due_date' => $installment?->due_date?->format('Y-m-d'),
                            'payment_date' => $payment->payment_date?->format('Y-m-d'),
                            'registered_at' => $payment->created_at,
                            'days_late' => $daysLate,
                            'amount' => (float) $detail->amount_paid + (float) $detail->late_fee_paid,
                            'principal_amount' => (float) $detail->principal_paid,
                            'interest_amount' => (float) $detail->interest_paid,
                            'observation' => $this->eventObservation($eventType, $payment->payment_number) . ((float)$detail->late_fee_waived > 0 ? ' Mora exonerada: '.$payment->late_fee_reason : ''),
                            'created_by' => $payment->created_by,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $paidDates[] = $payment->payment_date?->format('Y-m-d');
                    }

                    if ($payment->details->isEmpty() || $payment->payment_type === 'liquidacion') {
                        $events[] = $this->standalonePaymentEvent($member->id, $loan->id, $payment);
                        $paidDates[] = $payment->payment_date?->format('Y-m-d');
                    }
                }

                if (in_array($loan->status, ['desembolsado', 'refinanciado'], true)) {
                    foreach ($loan->installments as $installment) {
                        if ((float) $installment->remaining_amount <= 0.009 || ! $installment->due_date || $installment->due_date->gte(today())) continue;
                        $lateQuote = $this->lateFees->quote($installment, today());
                        $events[] = [
                            'member_id' => $member->id,
                            'loan_id' => $loan->id,
                            'loan_installment_id' => $installment->id,
                            'loan_payment_id' => null,
                            'event_type' => 'overdue_active',
                            'due_date' => $installment->due_date->format('Y-m-d'),
                            'payment_date' => null,
                            'registered_at' => null,
                            'days_late' => $lateQuote['days'],
                            'amount' => (float) $installment->remaining_amount + $lateQuote['pending'],
                            'principal_amount' => max(0, (float) $installment->principal_amount - (float) $installment->capital_paid),
                            'interest_amount' => max(0, (float) $installment->interest_amount - (float) $installment->interest_paid - (float) $installment->interest_exonerated),
                            'observation' => 'Cuota vencida con saldo pendiente. Mora: S/ '.number_format($lateQuote['pending'],2).'.',
                            'created_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            CreditHistoryEvent::where('member_id', $member->id)->delete();
            foreach (array_chunk($events, 250) as $chunk) CreditHistoryEvent::insert($chunk);

            $paidEvents = collect($events)->whereNotNull('payment_date');
            $mild = $paidEvents->where('event_type', 'mild_late')->count();
            $serious = $paidEvents->where('event_type', 'serious_late')->count();
            $lateDays = $paidEvents->where('days_late', '>', 0)->pluck('days_late');
            $activeOverdue = collect($events)->where('event_type', 'overdue_active');
            $early = $paidEvents->whereIn('event_type', ['paid_early', 'capital_payment'])->count();
            $paidLoans = $validLoans->where('status', 'pagado')->count();
            $withdrawalRisk = $member->accountClosures->contains(fn ($closure) => in_array($closure->status, ['pendiente_regularizacion', 'en_proceso'], true) || ($closure->status === 'calculado' && (float) $closure->final_balance < 0));
            $score = $this->calculateScore($mild, $serious, $lateDays, $activeOverdue->count(), $paidLoans, $early);
            if ($withdrawalRisk) $score = min(39, $score);
            [$status, $color] = $this->classification($score);
            $userId = Auth::id();
            $existing = CreditHistory::where('member_id', $member->id)->first();

            return CreditHistory::updateOrCreate(['member_id' => $member->id], [
                'total_loans' => $validLoans->count(),
                'paid_loans' => $paidLoans,
                'active_loans' => $validLoans->whereIn('status', ['aprobado', 'desembolsado', 'refinanciado'])->count(),
                'on_time_payments' => $paidEvents->whereIn('event_type', ['on_time', 'grace', 'paid_early'])->count(),
                'mild_late_payments' => $mild,
                'serious_late_payments' => $serious,
                'late_payments' => $mild + $serious,
                'max_days_late' => (int) ($lateDays->max() ?? 0),
                'average_days_late' => round((float) ($lateDays->avg() ?? 0), 2),
                'total_paid' => round($totalPaid, 2),
                'last_payment_date' => collect($paidDates)->filter()->max(),
                'last_loan_date' => $validLoans->max(fn ($loan) => optional($loan->disbursed_at ?? $loan->start_date ?? $loan->created_at)->format('Y-m-d')),
                'active_overdue_installments' => $activeOverdue->count(),
                'active_overdue_amount' => round((float) $activeOverdue->sum('amount'), 2),
                'score' => $score,
                'status' => $status,
                'color' => $color,
                'recommendation' => $withdrawalRisk
                    ? 'El socio tiene un cierre pendiente de regularización. Regularizar la deuda o anular el cierre si corresponde.'
                    : $this->recommendation($score, $activeOverdue->count(), $validLoans->count()),
                'calculated_at' => now(),
                'created_by' => $existing?->created_by ?? $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    public function summary(Member|int $member, bool $fresh = false): array
    {
        $member = $member instanceof Member ? $member : Member::withTrashed()->findOrFail($member);
        $history = $fresh ? $this->recalculate($member) : ($member->creditHistory ?: $this->recalculate($member));
        $history->loadMissing('events.payment.creator', 'events.loan');

        return [
            'score' => (int) $history->score,
            'status' => $history->status,
            'label' => ucfirst($history->status),
            'color' => $history->color,
            'total_loans' => $history->total_loans,
            'paid_loans' => $history->paid_loans,
            'active_loans' => $history->active_loans,
            'on_time' => $history->on_time_payments,
            'mild_late' => $history->mild_late_payments,
            'serious_late' => $history->serious_late_payments,
            'overdue' => $history->late_payments,
            'unpaid' => $history->active_overdue_installments,
            'active_overdue_installments' => $history->active_overdue_installments,
            'active_overdue_amount' => (float) $history->active_overdue_amount,
            'active_overdue_amount_formatted' => 'S/ ' . number_format((float) $history->active_overdue_amount, 2),
            'max_days_late' => $history->max_days_late,
            'average_days_late' => (float) $history->average_days_late,
            'total_paid_formatted' => 'S/ ' . number_format((float) $history->total_paid, 2),
            'last_payment_date' => optional($history->last_payment_date)->format('d/m/Y'),
            'last_loan_date' => optional($history->last_loan_date)->format('d/m/Y'),
            'recommendation' => $history->recommendation,
            'calculated_at' => optional($history->calculated_at)->format('d/m/Y H:i'),
        ];
    }

    public function installmentStatus(mixed $dueDate, mixed $paymentDate = null, float $remaining = 0): array
    {
        $due = Carbon::parse($dueDate)->startOfDay();
        $end = $paymentDate ? Carbon::parse($paymentDate)->startOfDay() : today();
        $days = max(0, (int) $due->diffInDays($end, false));
        $type = $paymentDate ? $this->paymentEventType($due, $end) : ($remaining > .009 && $due->lt(today()) ? 'overdue_active' : 'pending');
        return ['event_type' => $type, 'days_late' => $days, 'label' => $this->eventLabel($type), 'color' => $this->eventColor($type)];
    }

    private function paymentEventType(mixed $dueDate, mixed $paymentDate, ?string $paymentType = null): string
    {
        if (in_array($paymentType, ['capital', 'abono_capital'], true)) return 'capital_payment';
        if (! $dueDate) return $paymentType === 'liquidacion' ? 'liquidation' : 'on_time';
        $days = (int) Carbon::parse($dueDate)->diffInDays(Carbon::parse($paymentDate), false);
        if ($days < 0 || in_array($paymentType, ['adelanto', 'adelantado'], true)) return 'paid_early';
        if ($days === 0) return 'on_time';
        if ($days <= 5) return 'grace';
        if ($days <= 8) return 'mild_late';
        return 'serious_late';
    }

    private function standalonePaymentEvent(int $memberId, int $loanId, $payment): array
    {
        $type = in_array($payment->payment_type, ['capital', 'abono_capital'], true) ? 'capital_payment' : ($payment->payment_type === 'liquidacion' ? 'liquidation' : 'on_time');
        return ['member_id' => $memberId, 'loan_id' => $loanId, 'loan_installment_id' => null, 'loan_payment_id' => $payment->id, 'event_type' => $type, 'due_date' => null, 'payment_date' => optional($payment->payment_date)->format('Y-m-d'), 'registered_at' => $payment->created_at, 'days_late' => 0, 'amount' => (float) $payment->amount, 'principal_amount' => (float) $payment->capital_amount, 'interest_amount' => (float) $payment->interest_amount, 'observation' => $this->eventObservation($type, $payment->payment_number), 'created_by' => $payment->created_by, 'created_at' => now(), 'updated_at' => now()];
    }

    private function calculateScore(int $mild, int $serious, $lateDays, int $activeOverdue, int $paidLoans, int $early): int
    {
        $score = 100 - min(20, $mild * 4) - min(50, $serious * 10);
        $max = (int) ($lateDays->max() ?? 0);
        $avg = (float) ($lateDays->avg() ?? 0);
        if ($max > 30) $score -= 15; elseif ($max > 8) $score -= 8; elseif ($max > 5) $score -= 3;
        if ($avg > 15) $score -= 10; elseif ($avg > 8) $score -= 5;
        $score += min(6, $paidLoans * 2) + min(5, $early);
        if ($activeOverdue > 0) $score = min(39, $score - 25 - min(20, ($activeOverdue - 1) * 5));
        return max(0, min(100, $score));
    }

    private function classification(int $score): array
    {
        return match (true) { $score >= 90 => ['excelente', 'verde'], $score >= 75 => ['bueno', 'azul'], $score >= 60 => ['regular', 'amarillo'], $score >= 40 => ['riesgo', 'naranja'], default => ['malo', 'rojo'] };
    }

    private function recommendation(int $score, int $activeOverdue, int $loans): string
    {
        if ($loans === 0) return 'Sin historial de préstamos. Evaluar con las reglas generales y la capacidad de pago.';
        if ($activeOverdue > 0) return 'Regularizar las cuotas vencidas antes de asumir una nueva obligación.';
        return match (true) { $score >= 90 => 'Historial excelente. Mantener el comportamiento puntual.', $score >= 75 => 'Buen historial. Continuar monitoreando la puntualidad.', $score >= 60 => 'Revisar atrasos leves y capacidad de pago antes de aprobar.', $score >= 40 => 'Evaluar con cautela, solicitar sustento y reforzar seguimiento.', default => 'Historial de alto riesgo. Realizar evaluación reforzada antes de decidir.' };
    }

    private function eventObservation(string $type, ?string $code): string { return $this->eventLabel($type) . ($code ? " ({$code})." : '.'); }
    private function eventLabel(string $type): string { return ['on_time' => 'Pago puntual', 'grace' => 'Pago dentro de tolerancia', 'mild_late' => 'Atraso leve', 'serious_late' => 'Atraso grave', 'overdue_active' => 'Cuota vencida activa', 'paid_early' => 'Pago anticipado', 'liquidation' => 'Liquidación', 'capital_payment' => 'Abono a capital', 'pending' => 'Pendiente'][$type] ?? 'Evento crediticio'; }
    private function eventColor(string $type): string { return ['on_time' => 'verde', 'grace' => 'verde', 'paid_early' => 'azul', 'capital_payment' => 'azul', 'mild_late' => 'amarillo', 'serious_late' => 'rojo', 'overdue_active' => 'rojo', 'pending' => 'gris'][$type] ?? 'gris'; }
}
