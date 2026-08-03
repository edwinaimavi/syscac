<?php

namespace App\Services;

use App\Models\LoanPayment;
use App\Models\LoanPaymentDetail;
use App\Models\MemberAccountClosure;
use App\Models\ProfitDistribution;
use App\Models\ProfitSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfitAvailabilityService
{
    public function summary(string $startDate, string $endDate, ?int $excludeDistributionId = null, bool $lock = false): array
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();
        $payments = $this->eligibleDetails($start, $end);
        $distributions = ProfitDistribution::query()
            ->where('status', '!=', 'anulado')
            ->whereDate('start_date', $start)
            ->whereDate('end_date', $end);

        if ($excludeDistributionId) {
            $distributions->whereKeyNot($excludeDistributionId);
        }
        if ($lock) {
            (clone $payments)->select('loan_payment_details.id')->lockForUpdate()->get();
            $distributions->lockForUpdate();
        }

        // Aggregate over a derived table. The eligibility query contains a correlated
        // EXISTS against loan_payments.id; aggregating it directly violates MySQL's
        // ONLY_FULL_GROUP_BY mode because that outer id is not grouped.
        $eligibleAmounts = $payments->select([
            'loan_payment_details.interest_paid',
            'loan_payment_details.late_fee_paid',
        ]);
        $totals = DB::query()
            ->fromSub($eligibleAmounts, 'eligible_profit_details')
            ->selectRaw('COALESCE(SUM(interest_paid), 0) interest, COALESCE(SUM(late_fee_paid), 0) late_fee')
            ->first();
        $interestCollected = round((float) $totals->interest, 2);
        $lateFeesCollected = round((float) $totals->late_fee, 2);
        $generated = round($interestCollected + $lateFeesCollected, 2);
        $adjustments = ProfitSource::query()->where('status', 'activo')->whereBetween('source_date', [$start, $end]);
        $positiveAdjustments = round((float) (clone $adjustments)->whereIn('adjustment_type', ['positive', 'correction_positive'])->sum('amount'), 2);
        $negativeAdjustments = round((float) (clone $adjustments)->whereIn('adjustment_type', ['negative', 'previous_year_discount', 'previously_paid', 'administrative_correction'])->sum('amount'), 2);
        $generated = round($generated + $positiveAdjustments - $negativeAdjustments, 2);
        $annualDistributed = round((float) $distributions->sum('total_profit'), 2);

        $periodStart = Carbon::parse($start);
        $periodEnd = Carbon::parse($end);
        $retirementQuery = MemberAccountClosure::query()
            ->where('status', 'cerrado')
            ->where('utility_status', 'liquidada');
        if ($periodStart->isStartOfYear() && $periodEnd->isEndOfYear() && $periodStart->year === $periodEnd->year) {
            $retirementQuery->where('utility_period_year', $periodStart->year);
        } else {
            $retirementQuery->whereBetween('retirement_date', [$start, $end]);
        }
        if ($lock) {
            $retirementQuery->lockForUpdate();
        }
        $retirementDistributed = round((float) $retirementQuery->sum('utility_paid_now'), 2);
        $distributed = round($annualDistributed + $retirementDistributed, 2);
        $available = round(max(0, $generated - $distributed), 2);

        return compact('start', 'end', 'interestCollected', 'lateFeesCollected', 'positiveAdjustments', 'negativeAdjustments', 'generated', 'annualDistributed', 'retirementDistributed', 'distributed', 'available');
    }

    public function validateAmount(float $amount, string $startDate, string $endDate, ?int $excludeDistributionId = null, bool $lock = false): array
    {
        $summary = $this->summary($startDate, $endDate, $excludeDistributionId, $lock);
        if ($amount > $summary['available'] + 0.0001) {
            throw ValidationException::withMessages([
                'total_profit' => ['No se puede distribuir más utilidad de la disponible. La utilidad solo se genera por intereses y moras cobradas.'],
            ]);
        }
        return $summary + ['remaining' => round($summary['available'] - $amount, 2)];
    }

    public function sources(string $startDate, string $endDate): Builder
    {
        return $this->eligibleDetails(Carbon::parse($startDate)->toDateString(), Carbon::parse($endDate)->toDateString())
            ->with(['payment.member:id,code,full_name', 'payment.loan:id,loan_number', 'installment:id,installment_number,due_date'])
            ->orderByDesc('loan_payments.payment_date')
            ->orderByDesc('loan_payment_details.id');
    }

    private function eligibleDetails(string $startDate, string $endDate): Builder
    {
        return LoanPaymentDetail::query()
            ->join('loan_payments', 'loan_payments.id', '=', 'loan_payment_details.loan_payment_id')
            ->whereNull('loan_payments.deleted_at')
            ->where('loan_payments.status', 'registrado')
            ->where('loan_payments.affects_profit', true)
            ->where('loan_payments.profit_treatment', 'eligible')
            ->whereBetween('loan_payments.payment_date', [$startDate, $endDate])
            ->where(fn ($query) => $query->where('loan_payment_details.interest_paid', '>', 0)->orWhere('loan_payment_details.late_fee_paid', '>', 0))
            ->select('loan_payment_details.*');
    }
}
