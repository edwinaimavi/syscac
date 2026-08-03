<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('capital_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('interest_amount', 14, 2)->default(0)->after('capital_amount');
            $table->decimal('late_interest_amount', 14, 2)->default(0)->after('interest_amount');
            $table->decimal('interest_exonerated_amount', 14, 2)->default(0)->after('late_interest_amount');
            $table->unsignedInteger('installments_advanced_count')->nullable()->after('interest_exonerated_amount');
            $table->json('schedule_before')->nullable()->after('installments_advanced_count');
            $table->json('schedule_after')->nullable()->after('schedule_before');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->decimal('capital_paid', 14, 2)->default(0)->after('paid_amount');
            $table->decimal('interest_paid', 14, 2)->default(0)->after('capital_paid');
            $table->decimal('interest_exonerated', 14, 2)->default(0)->after('interest_paid');
            $table->string('payment_type')->nullable()->after('status');
            $table->unsignedInteger('schedule_version')->default(1)->after('payment_type');
            $table->timestamp('recalculated_at')->nullable()->after('schedule_version');
        });

        Schema::table('member_account_closures', function (Blueprint $table) {
            $table->decimal('loan_capital_compensated', 14, 2)->default(0)->after('pending_loans_amount');
            $table->decimal('overdue_interest_compensated', 14, 2)->default(0)->after('loan_capital_compensated');
            $table->decimal('future_interest_exonerated', 14, 2)->default(0)->after('overdue_interest_compensated');
            $table->json('loan_schedule_before')->nullable()->after('future_interest_exonerated');
        });

        DB::table('loan_payment_details')->selectRaw('loan_installment_id, SUM(principal_paid) principal_paid, SUM(interest_paid) interest_paid')->whereNotNull('loan_installment_id')->groupBy('loan_installment_id')->orderBy('loan_installment_id')->get()->each(function ($row) {
            DB::table('loan_installments')->where('id', $row->loan_installment_id)->update(['capital_paid' => $row->principal_paid, 'interest_paid' => $row->interest_paid]);
        });
        DB::table('loan_payment_details')->selectRaw('loan_payment_id, SUM(principal_paid) capital_amount, SUM(interest_paid) interest_amount')->groupBy('loan_payment_id')->orderBy('loan_payment_id')->get()->each(function ($row) {
            DB::table('loan_payments')->where('id', $row->loan_payment_id)->update(['capital_amount' => $row->capital_amount, 'interest_amount' => $row->interest_amount]);
        });
    }

    public function down(): void
    {
        Schema::table('member_account_closures', fn (Blueprint $table) => $table->dropColumn(['loan_capital_compensated', 'overdue_interest_compensated', 'future_interest_exonerated', 'loan_schedule_before']));
        Schema::table('loan_installments', fn (Blueprint $table) => $table->dropColumn(['capital_paid', 'interest_paid', 'interest_exonerated', 'payment_type', 'schedule_version', 'recalculated_at']));
        Schema::table('loan_payments', fn (Blueprint $table) => $table->dropColumn(['capital_amount', 'interest_amount', 'late_interest_amount', 'interest_exonerated_amount', 'installments_advanced_count', 'schedule_before', 'schedule_after']));
    }
};
