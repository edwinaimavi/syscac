<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_simulations', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_simulations', 'converted_loan_id')) {
                $table->foreignId('converted_loan_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('loans')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('loan_simulations', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('converted_loan_id');
            }

            if (! Schema::hasColumn('loan_simulations', 'converted_by')) {
                $table->foreignId('converted_by')
                    ->nullable()
                    ->after('converted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_simulations', function (Blueprint $table) {
            if (Schema::hasColumn('loan_simulations', 'converted_loan_id')) {
                $table->dropConstrainedForeignId('converted_loan_id');
            }

            if (Schema::hasColumn('loan_simulations', 'converted_by')) {
                $table->dropConstrainedForeignId('converted_by');
            }

            if (Schema::hasColumn('loan_simulations', 'converted_at')) {
                $table->dropColumn('converted_at');
            }
        });
    }
};
