<?php

namespace App\Console\Commands;

use App\Models\CashMovement;
use App\Models\LoanPayment;
use App\Services\ShareCashMovementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsolidateLoanPaymentCashMovements extends Command
{
    protected $signature = 'cash:consolidate-loan-payments {--payment= : ID o código del cobro}';
    protected $description = 'Consolida movimientos separados de cuota y mora en un solo ingreso de Caja';

    public function handle(ShareCashMovementService $balances): int
    {
        $filter = $this->option('payment');
        $payments = LoanPayment::query()->where('status', 'registrado')
            ->when($filter, fn ($q) => $q->where(fn ($x) => $x->whereKey($filter)->orWhere('payment_number', $filter)))
            ->whereHas('cashMovements', fn ($q) => $q->where('status', 'registrado'))
            ->with(['loan', 'details.installment', 'cashMovements' => fn ($q) => $q->where('status', 'registrado')->orderBy('id')])->get();

        foreach ($payments as $payment) {
            DB::transaction(function () use ($payment) {
                $primary = $payment->cashMovements->firstWhere('category', '!=', 'mora_atraso') ?? $payment->cashMovements->first();
                $installments = $payment->details->pluck('installment.installment_number')->filter()->implode(', ');
                $primary->update([
                    'category' => 'cobro_prestamo',
                    'concept' => 'Cobro ' . ($payment->loan?->loan_number ?? '-') . ' / Cuota ' . ($installments ?: '-')
                        . ': Capital S/' . number_format((float) $payment->capital_amount, 2)
                        . ', Interés S/' . number_format((float) $payment->interest_amount, 2)
                        . ', Mora cobrada S/' . number_format((float) $payment->late_fee_paid, 2)
                        . ', Mora exonerada S/' . number_format((float) $payment->late_fee_waived, 2),
                    'amount' => $payment->amount,
                ]);
                foreach ($payment->cashMovements->where('id', '!=', $primary->id) as $movement) {
                    $movement->update([
                        'status' => 'anulado',
                        'balance_before' => null,
                        'balance_after' => null,
                        'annulled_at' => now(),
                    ]);
                }
            });
        }

        $balances->recalculateBalances();
        $this->info("Cobros de Caja normalizados: {$payments->count()}");
        return self::SUCCESS;
    }
}
