<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPaymentDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateLoanSchedules extends Command
{
    protected $signature = 'loans:recalculate-schedules {--loan= : ID o código del préstamo}';
    protected $description = 'Recalcula cuotas afectadas por cobros anulados usando solamente pagos activos';

    public function handle(): int
    {
        $loanFilter = $this->option('loan');
        $query = Loan::query()
            ->when($loanFilter, fn ($q) => $q->where(fn ($x) => $x->whereKey($loanFilter)->orWhere('loan_number', $loanFilter)))
            ->whereHas('installments.paymentDetails.payment', fn ($q) => $q->whereIn('status', ['anulado', 'cancelado']));

        $loans = $query->get();
        foreach ($loans as $loan) {
            DB::transaction(fn () => $this->repairLoan($loan));
        }

        $this->info("Préstamos recalculados: {$loans->count()}");
        return self::SUCCESS;
    }

    private function repairLoan(Loan $loan): void
    {
        $installments = $loan->installments()->whereHas('paymentDetails.payment', fn ($q) => $q->whereIn('status', ['anulado', 'cancelado']))->lockForUpdate()->get();

        foreach ($installments as $installment) {
            $activeDetails = LoanPaymentDetail::query()
                ->where('loan_installment_id', $installment->id)
                ->whereHas('payment', fn ($q) => $q->whereNotIn('status', ['anulado', 'cancelado']))
                ->with('payment')->get();

            $capital = round((float) $activeDetails->sum('principal_paid'), 2);
            $interest = round((float) $activeDetails->sum('interest_paid'), 2);
            $latePaid = round((float) $activeDetails->sum('late_fee_paid'), 2);
            $lateWaived = round((float) $activeDetails->sum('late_fee_waived'), 2);
            $paid = round($capital + $interest, 2);
            $remaining = max(0, round((float) $installment->installment_amount - $paid - (float) $installment->interest_exonerated, 2));
            $latest = $activeDetails->sortByDesc('loan_payment_id')->first()?->payment;
            $status = $paid <= 0.009 ? 'pendiente' : ($remaining <= 0.009 ? ($latest?->payment_type === 'adelanto_cuotas' ? 'adelantado' : 'pagado') : 'parcial');
            $latePending = max(0, round((float) $installment->late_fee_amount - $latePaid - $lateWaived, 2));

            $installment->update([
                'capital_paid' => $capital,
                'interest_paid' => $interest,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'status' => $status,
                'payment_type' => $latest?->payment_type,
                'paid_at' => $remaining <= 0.009 ? $latest?->payment_date : null,
                'late_fee_paid' => $latePaid,
                'late_fee_waived' => $lateWaived,
                'late_fee_pending' => $latePending,
                'late_fee_status' => $latePending > 0.009 ? 'pendiente' : ($lateWaived > 0 ? 'exonerada' : ($latePaid > 0 ? 'pagada' : 'no_mora')),
            ]);
        }

        $balance = round((float) $loan->installments()->whereNotIn('status', ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'])->sum('remaining_amount'), 2);
        $loan->update(['current_balance' => $balance, 'status' => $balance <= 0.009 ? 'pagado' : 'desembolsado']);
    }
}
