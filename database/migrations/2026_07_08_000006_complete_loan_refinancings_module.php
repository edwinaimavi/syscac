<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_refinancings', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_refinancings', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('loan_refinancings', 'additional_amount')) {
                $table->decimal('additional_amount', 14, 2)->default(0)->after('new_amount');
            }

            if (! Schema::hasColumn('loan_refinancings', 'start_date')) {
                $table->date('start_date')->nullable()->after('term_months');
            }

            if (! Schema::hasColumn('loan_refinancings', 'first_payment_date')) {
                $table->date('first_payment_date')->nullable()->after('start_date');
            }

            if (! Schema::hasColumn('loan_refinancings', 'amortization_method')) {
                $table->string('amortization_method')->default('aleman')->after('first_payment_date');
            }

            if (! Schema::hasColumn('loan_refinancings', 'fixed_principal')) {
                $table->decimal('fixed_principal', 14, 2)->default(0)->after('amortization_method');
            }

            if (! Schema::hasColumn('loan_refinancings', 'total_interest')) {
                $table->decimal('total_interest', 14, 2)->default(0)->after('fixed_principal');
            }

            if (! Schema::hasColumn('loan_refinancings', 'total_amount')) {
                $table->decimal('total_amount', 14, 2)->default(0)->after('total_interest');
            }

            if (! Schema::hasColumn('loan_refinancings', 'receipt_id')) {
                $table->foreignId('receipt_id')->nullable()->after('total_amount')->constrained('receipts')->nullOnDelete();
            }

            if (! Schema::hasColumn('loan_refinancings', 'closed_installments_snapshot')) {
                $table->json('closed_installments_snapshot')->nullable()->after('observation');
            }

            if (! Schema::hasColumn('loan_refinancings', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('loan_refinancings', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('annulled_by');
            }
        });

        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'refinancing_id')) {
                $table->foreignId('refinancing_id')->nullable()->after('loan_simulation_id')->constrained('loan_refinancings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'refinancing_id')) {
                $table->dropConstrainedForeignId('refinancing_id');
            }
        });

        Schema::table('loan_refinancings', function (Blueprint $table) {
            if (Schema::hasColumn('loan_refinancings', 'receipt_id')) {
                $table->dropConstrainedForeignId('receipt_id');
            }

            foreach (['annulled_at', 'annulled_by', 'closed_installments_snapshot', 'total_amount', 'total_interest', 'fixed_principal', 'amortization_method', 'first_payment_date', 'start_date', 'additional_amount', 'code'] as $column) {
                if (Schema::hasColumn('loan_refinancings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
