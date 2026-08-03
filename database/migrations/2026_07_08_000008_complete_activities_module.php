<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (! Schema::hasColumn('activities', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('activities', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('activities', 'closed_by')) {
                $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            }

            if (! Schema::hasColumn('activities', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('activities', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('annulled_by');
            }
        });

        Schema::table('activity_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_movements', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('activity_movements', 'movement_date')) {
                $table->date('movement_date')->nullable()->after('member_id');
            }

            if (! Schema::hasColumn('activity_movements', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('activity_movements', 'receipt_id')) {
                $table->foreignId('receipt_id')->nullable()->after('voucher_path')->constrained('receipts')->nullOnDelete();
            }

            if (! Schema::hasColumn('activity_movements', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('activity_movements', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('annulled_by');
            }
        });

        if (Schema::hasColumn('activity_movements', 'date') && Schema::hasColumn('activity_movements', 'movement_date')) {
            DB::table('activity_movements')
                ->whereNull('movement_date')
                ->update(['movement_date' => DB::raw('`date`')]);
        }
    }

    public function down(): void
    {
        Schema::table('activity_movements', function (Blueprint $table) {
            if (Schema::hasColumn('activity_movements', 'receipt_id')) {
                $table->dropConstrainedForeignId('receipt_id');
            }

            foreach (['annulled_at', 'annulled_by', 'payment_reference', 'movement_date', 'code'] as $column) {
                if (Schema::hasColumn('activity_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            foreach (['annulled_at', 'annulled_by', 'closed_by', 'closed_at', 'code'] as $column) {
                if (Schema::hasColumn('activities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
