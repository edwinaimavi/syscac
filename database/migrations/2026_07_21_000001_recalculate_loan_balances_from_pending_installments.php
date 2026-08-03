<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('loans')->whereNull('deleted_at')->orderBy('id')->each(function ($loan) {
            $balance = DB::table('loan_installments')
                ->where('loan_id', $loan->id)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['pagado', 'adelantado', 'liquidado', 'anulado', 'refinanciado'])
                ->sum('remaining_amount');

            $status = in_array($loan->status, ['desembolsado', 'pagado'], true)
                ? ((float) $balance <= 0.009 ? 'pagado' : 'desembolsado')
                : $loan->status;

            DB::table('loans')->where('id', $loan->id)->update([
                'current_balance' => round((float) $balance, 2),
                'status' => $status,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // The former derived balances cannot be restored reliably.
    }
};
