<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasTable('loan_simulations') && ! Schema::hasColumn('loans', 'loan_simulation_id')) {
                $table->foreignId('loan_simulation_id')->nullable()->after('id')->constrained('loan_simulations')->nullOnDelete();
            }

            if (! Schema::hasColumn('loans', 'fixed_principal')) {
                $table->decimal('fixed_principal', 14, 2)->nullable()->after('amortization_method');
            }

            if (! Schema::hasColumn('loans', 'disbursed_amount')) {
                $table->decimal('disbursed_amount', 14, 2)->nullable()->after('current_balance');
            }

            if (! Schema::hasColumn('loans', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('disbursed_amount');
            }

            if (! Schema::hasColumn('loans', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('loans', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('loans', 'disbursed_by')) {
                $table->unsignedBigInteger('disbursed_by')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('loans', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('disbursed_by');
            }

            if (! Schema::hasColumn('loans', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('annulled_by');
            }
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_installments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            foreach (['annulled_at', 'annulled_by', 'disbursed_by', 'approved_by', 'disbursed_at', 'approved_at', 'disbursed_amount', 'fixed_principal'] as $column) {
                if (Schema::hasColumn('loans', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('loans', 'loan_simulation_id')) {
                $table->dropConstrainedForeignId('loan_simulation_id');
            }
        });
    }
};
