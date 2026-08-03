<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_distributions', function (Blueprint $table) {
            $table->decimal('total_action_month', 18, 4)->default(0)->after('total_shares');
            $table->decimal('profit_per_action_month', 18, 8)->default(0)->after('profit_per_share');
            $table->timestamp('calculated_at')->nullable()->after('source_type');
            $table->unsignedBigInteger('calculated_by')->nullable()->after('calculated_at');
        });

        Schema::table('profit_distribution_details', function (Blueprint $table) {
            $table->decimal('actions_considered', 18, 4)->default(0)->after('member_id');
            $table->unsignedSmallInteger('months_considered')->default(0)->after('actions_considered');
            $table->decimal('action_month', 18, 4)->default(0)->after('months_considered');
            $table->json('calculation_breakdown')->nullable()->after('action_month');
        });
    }

    public function down(): void
    {
        Schema::table('profit_distribution_details', fn (Blueprint $table) => $table->dropColumn(['actions_considered', 'months_considered', 'action_month', 'calculation_breakdown']));
        Schema::table('profit_distributions', fn (Blueprint $table) => $table->dropColumn(['total_action_month', 'profit_per_action_month', 'calculated_at', 'calculated_by']));
    }
};
