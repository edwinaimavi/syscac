<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('previous_loan_balance', 14, 2)->nullable()->after('amount');
            $table->decimal('new_loan_balance', 14, 2)->nullable()->after('previous_loan_balance');
        });

        DB::table('loan_payments')->whereNull('previous_loan_balance')->orderBy('id')->each(function ($payment) {
            $schedule = json_decode($payment->schedule_before ?: '[]', true) ?: [];
            $previous = collect($schedule)->sum(fn ($row) => (float) ($row['remaining_amount'] ?? 0));
            if ($previous <= 0) return;
            DB::table('loan_payments')->where('id', $payment->id)->update([
                'previous_loan_balance' => round($previous, 2),
                'new_loan_balance' => max(0, round($previous - (float) $payment->capital_amount, 2)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('loan_payments', fn (Blueprint $table) => $table->dropColumn(['previous_loan_balance', 'new_loan_balance']));
    }
};
