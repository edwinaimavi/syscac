<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LoanSettlementService
{
    public function __construct(private readonly LateFeeService $lateFees) {}
    public function debt(Loan $loan, CarbonInterface $date): array
    {
        $installments = $loan->installments()->whereNotIn('status', ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'])->orderBy('installment_number')->get();
        $capital = $installments->sum(fn ($row) => max(0, (float) $row->principal_amount - (float) $row->capital_paid));
        $overdueInterest = $installments->filter(fn ($row) => $row->due_date && $row->due_date->lte($date))->sum(fn ($row) => max(0, (float) $row->interest_amount - (float) $row->interest_paid));
        $futureInterest = $installments->filter(fn ($row) => ! $row->due_date || $row->due_date->gt($date))->sum(fn ($row) => max(0, (float) $row->interest_amount - (float) $row->interest_paid));

        $lateFee = $installments->sum(fn ($row) => $this->lateFees->quote($row, $date)['pending']);
        return [
            'capital' => round((float) $capital, 2),
            'overdue_interest' => round((float) $overdueInterest, 2),
            'future_interest_exonerated' => round((float) $futureInterest, 2),
            'late_fee' => round((float) $lateFee, 2),
            'total' => round((float) $capital + (float) $overdueInterest + (float) $lateFee, 2),
        ];
    }

    public function hasOverdueDebt(Loan $loan, CarbonInterface $date): bool
    {
        return $loan->installments()->whereDate('due_date', '<', $date->toDateString())->whereIn('status', ['pendiente', 'parcial', 'vencido'])->where('remaining_amount', '>', 0.009)->exists();
    }

    public function recalculateFutureSchedule(Loan $loan, CarbonInterface $date): void
    {
        $future = $loan->installments()->whereDate('due_date', '>', $date->toDateString())->whereIn('status', ['pendiente', 'parcial', 'vencido'])->orderBy('installment_number')->lockForUpdate()->get();
        if ($future->isEmpty()) {
            $this->syncLoanBalance($loan);
            return;
        }

        $principalBalance = round((float) $future->sum(fn ($row) => max(0, (float) $row->principal_amount - (float) $row->capital_paid)), 2);
        $count = $future->count();
        $basePrincipal = $count > 0 ? round($principalBalance / $count, 2) : 0;
        $opening = $principalBalance;
        $version = ((int) $future->max('schedule_version')) + 1;

        foreach ($future as $index => $installment) {
            $principal = $index === $count - 1 ? $opening : min($opening, $basePrincipal);
            $interest = round($opening * ((float) $loan->interest_rate / 100), 2);
            $closing = max(0, round($opening - $principal, 2));
            $installment->update([
                'opening_balance' => $opening,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'installment_amount' => round($principal + $interest, 2),
                'remaining_amount' => round($principal + $interest, 2),
                'closing_balance' => $closing,
                'schedule_version' => $version,
                'recalculated_at' => now(),
            ]);
            $opening = $closing;
        }

        $this->syncLoanBalance($loan);
    }

    public function syncLoanBalance(Loan $loan): void
    {
        $balance = round((float) $loan->installments()->whereNotIn('status', ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'])->sum('remaining_amount'), 2);
        $loan->update(['current_balance' => $balance, 'status' => $balance <= 0.009 ? 'pagado' : 'desembolsado', 'updated_by' => auth()->id()]);
    }

    public function liquidateByCompensation(Loan $loan, CarbonInterface $date): array
    {
        $debt = $this->debt($loan, $date);
        $installments = $loan->installments()->whereNotIn('status', ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'])->lockForUpdate()->get();
        foreach ($installments as $row) {
            $capital = max(0, (float) $row->principal_amount - (float) $row->capital_paid);
            $interest = $row->due_date && $row->due_date->lte($date) ? max(0, (float) $row->interest_amount - (float) $row->interest_paid) : 0;
            $exonerated = max(0, (float) $row->interest_amount - (float) $row->interest_paid - $interest);
            $row->update(['capital_paid' => (float) $row->capital_paid + $capital, 'interest_paid' => (float) $row->interest_paid + $interest, 'interest_exonerated' => (float) $row->interest_exonerated + $exonerated, 'paid_amount' => (float) $row->paid_amount + $capital + $interest, 'remaining_amount' => 0, 'status' => 'liquidado', 'payment_type' => 'compensacion_retiro', 'paid_at' => $date]);
        }
        $this->syncLoanBalance($loan);
        return $debt;
    }
}
