<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_payments', 'receipt_id')) {
                $table->foreignId('receipt_id')->nullable()->after('receipt_number')->constrained('receipts')->nullOnDelete();
            }

            if (! Schema::hasColumn('loan_payments', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('loan_payments', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('annulled_by');
            }
        });

        Schema::table('loan_payment_details', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_payment_details', 'observation')) {
                $table->text('observation')->nullable()->after('new_balance');
            }
        });

        Schema::table('receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('receipts', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            if (Schema::hasColumn('receipts', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });

        Schema::table('loan_payment_details', function (Blueprint $table) {
            if (Schema::hasColumn('loan_payment_details', 'observation')) {
                $table->dropColumn('observation');
            }
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            if (Schema::hasColumn('loan_payments', 'receipt_id')) {
                $table->dropConstrainedForeignId('receipt_id');
            }

            foreach (['annulled_at', 'annulled_by'] as $column) {
                if (Schema::hasColumn('loan_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
