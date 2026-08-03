<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'disbursement_payment_method')) {
                $table->string('disbursement_payment_method')->nullable()->after('disbursed_amount');
            }

            if (! Schema::hasColumn('loans', 'disbursement_reference')) {
                $table->string('disbursement_reference')->nullable()->after('disbursement_payment_method');
            }

            if (! Schema::hasColumn('loans', 'disbursement_voucher_path')) {
                $table->string('disbursement_voucher_path')->nullable()->after('disbursement_reference');
            }

            if (! Schema::hasColumn('loans', 'disbursement_receipt_id')) {
                $table->foreignId('disbursement_receipt_id')
                    ->nullable()
                    ->after('disbursement_voucher_path')
                    ->constrained('receipts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'disbursement_receipt_id')) {
                $table->dropConstrainedForeignId('disbursement_receipt_id');
            }

            foreach (['disbursement_voucher_path', 'disbursement_reference', 'disbursement_payment_method'] as $column) {
                if (Schema::hasColumn('loans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
