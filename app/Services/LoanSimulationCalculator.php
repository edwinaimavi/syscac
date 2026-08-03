<?php

namespace App\Services;

use Carbon\Carbon;

class LoanSimulationCalculator
{
    public function calculate(
        float $amount,
        float $monthlyRate,
        int $termMonths,
        string $startDate,
        ?string $firstPaymentDate = null
    ): array {
        $firstDate = $firstPaymentDate
            ? Carbon::parse($firstPaymentDate)
            : Carbon::parse($startDate)->addMonth();

        $balance = round($amount, 2);
        $fixedPrincipal = round($amount / $termMonths, 2);
        $installments = [];

        for ($number = 1; $number <= $termMonths; $number++) {
            $openingBalance = round($balance, 2);
            $principal = $number === $termMonths ? $openingBalance : min($fixedPrincipal, $openingBalance);
            $principal = round($principal, 2);
            $interest = round($openingBalance * ($monthlyRate / 100), 2);
            $closingBalance = round($openingBalance - $principal, 2);

            if ($number === $termMonths) {
                $closingBalance = 0.00;
            }

            $installments[] = [
                'installment_number' => $number,
                'due_date' => $firstDate->copy()->addMonths($number - 1)->toDateString(),
                'opening_balance' => $openingBalance,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'installment_amount' => round($principal + $interest, 2),
                'closing_balance' => max($closingBalance, 0),
            ];

            $balance = max($closingBalance, 0);
        }

        $totalInterest = round((float) collect($installments)->sum('interest_amount'), 2);
        $totalPayment = round($amount + $totalInterest, 2);

        return [
            'summary' => [
                'fixed_principal' => $fixedPrincipal,
                'total_interest' => $totalInterest,
                'total_payment' => $totalPayment,
                'first_installment' => $installments[0]['installment_amount'] ?? 0,
                'last_installment' => $installments[count($installments) - 1]['installment_amount'] ?? 0,
                'first_payment_date' => $firstDate->toDateString(),
            ],
            'installments' => $installments,
        ];
    }
}
