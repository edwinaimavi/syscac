<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'balance_before')) {
                $table->decimal('balance_before', 14, 2)->nullable()->after('related_id');
            }

            if (! Schema::hasColumn('cash_movements', 'balance_after')) {
                $table->decimal('balance_after', 14, 2)->nullable()->after('balance_before');
            }

            if (! Schema::hasColumn('cash_movements', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('cash_movements', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('annulled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (Schema::hasColumn('cash_movements', 'annulled_by')) {
                $table->dropColumn('annulled_by');
            }

            if (Schema::hasColumn('cash_movements', 'annulled_at')) {
                $table->dropColumn('annulled_at');
            }

            if (Schema::hasColumn('cash_movements', 'balance_after')) {
                $table->dropColumn('balance_after');
            }

            if (Schema::hasColumn('cash_movements', 'balance_before')) {
                $table->dropColumn('balance_before');
            }
        });
    }
};
