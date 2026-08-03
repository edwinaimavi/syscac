<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->boolean('is_historical')->default(false)->after('payment_date')->index();
            $table->boolean('affects_cash')->default(true)->after('is_historical')->index();
            $table->boolean('affects_profit')->default(true)->after('affects_cash')->index();
            $table->boolean('affects_credit_history')->default(true)->after('affects_profit')->index();
            $table->decimal('late_fee_calculated', 14, 2)->default(0)->after('late_fee_amount');
            $table->decimal('late_fee_charged', 14, 2)->default(0)->after('late_fee_calculated');
            $table->decimal('late_fee_exonerated', 14, 2)->default(0)->after('late_fee_charged');
            $table->text('late_fee_override_reason')->nullable()->after('late_fee_exonerated');
        });
    }

    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropIndex(['is_historical']);
            $table->dropIndex(['affects_cash']);
            $table->dropIndex(['affects_profit']);
            $table->dropIndex(['affects_credit_history']);
            $table->dropColumn([
                'is_historical',
                'affects_cash',
                'affects_profit',
                'affects_credit_history',
                'late_fee_calculated',
                'late_fee_charged',
                'late_fee_exonerated',
                'late_fee_override_reason',
            ]);
        });
    }
};
