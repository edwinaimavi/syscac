<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_distributions', function (Blueprint $table) {
            if (! Schema::hasColumn('profit_distributions', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('profit_distributions', 'source_type')) {
                $table->string('source_type')->nullable()->after('profit_per_share');
            }
            if (! Schema::hasColumn('profit_distributions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('profit_distributions', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('profit_distributions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('profit_distributions', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('profit_distributions', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('updated_by');
            }
            if (! Schema::hasColumn('profit_distributions', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('annulled_by');
            }
        });

        Schema::table('profit_distribution_details', function (Blueprint $table) {
            if (! Schema::hasColumn('profit_distribution_details', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('paid_amount');
            }
            if (! Schema::hasColumn('profit_distribution_details', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('profit_distribution_details', 'voucher_path')) {
                $table->string('voucher_path')->nullable()->after('payment_reference');
            }
            if (! Schema::hasColumn('profit_distribution_details', 'receipt_id')) {
                $table->foreignId('receipt_id')->nullable()->after('voucher_path')->constrained('receipts')->nullOnDelete();
            }
            if (! Schema::hasColumn('profit_distribution_details', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('profit_distribution_details', 'observation')) {
                $table->text('observation')->nullable()->after('paid_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('profit_distribution_details', function (Blueprint $table) {
            if (Schema::hasColumn('profit_distribution_details', 'receipt_id')) {
                $table->dropConstrainedForeignId('receipt_id');
            }
            foreach (['observation', 'paid_by', 'voucher_path', 'payment_reference', 'payment_method'] as $column) {
                if (Schema::hasColumn('profit_distribution_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('profit_distributions', function (Blueprint $table) {
            foreach (['annulled_at', 'annulled_by', 'paid_by', 'paid_at', 'approved_by', 'approved_at', 'source_type', 'code'] as $column) {
                if (Schema::hasColumn('profit_distributions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
