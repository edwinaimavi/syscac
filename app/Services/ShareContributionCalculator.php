<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ShareContributionCalculator
{
    public const SHARE_VALUE = 20.00;
    public const SOLIDARITY_AMOUNT = 5.00;
    public const ADMINISTRATIVE_FEE_AMOUNT = 5.00;

    public function calculate(float $totalPaid, float $solidarityAmount = 0, float $administrativeFeeAmount = 0): array
    {
        $totalPaid = round($totalPaid, 2);
        $solidarityAmount = round($solidarityAmount, 2);
        $administrativeFeeAmount = round($administrativeFeeAmount, 2);

        if ($totalPaid <= 0) {
            throw ValidationException::withMessages(['total_paid' => ['El monto total pagado debe ser mayor a cero.']]);
        }
        if ($solidarityAmount < 0) {
            throw ValidationException::withMessages(['solidarity_amount' => ['La solidaridad no puede ser negativa.']]);
        }
        if ($administrativeFeeAmount < 0) {
            throw ValidationException::withMessages(['administrative_fee_amount' => ['Los gastos administrativos no pueden ser negativos.']]);
        }
        if (($solidarityAmount + $administrativeFeeAmount) >= $totalPaid) {
            throw ValidationException::withMessages(['share_capital_amount' => ['El capital para acciones debe ser mayor a cero.']]);
        }

        $capital = round($totalPaid - $solidarityAmount - $administrativeFeeAmount, 2);
        $quantity = $capital / self::SHARE_VALUE;
        if (abs($quantity - round($quantity)) > 0.00001) {
            throw ValidationException::withMessages([
                'share_capital_amount' => ['El capital para acciones debe ser múltiplo exacto de S/ 20.00. Ajuste el monto total, solidaridad o gastos administrativos.'],
            ]);
        }

        return [
            'total_paid' => $totalPaid,
            'share_capital_amount' => $capital,
            'solidarity_amount' => $solidarityAmount,
            'administrative_fee_amount' => $administrativeFeeAmount,
            'share_value' => self::SHARE_VALUE,
            'shares_quantity' => (int) round($quantity),
        ];
    }
}
