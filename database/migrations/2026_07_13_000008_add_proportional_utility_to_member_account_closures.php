<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_account_closures', function (Blueprint $table) {
            $table->string('utility_mode')->default('pending')->after('pending_utilities_amount');
            $table->string('utility_status')->default('no_calculada')->after('utility_mode');
            $table->unsignedSmallInteger('utility_period_year')->nullable()->after('utility_status');
            $table->decimal('utility_actions_considered', 18, 4)->default(0)->after('utility_period_year');
            $table->unsignedSmallInteger('utility_productive_months')->default(0)->after('utility_actions_considered');
            $table->decimal('utility_action_month', 18, 4)->default(0)->after('utility_productive_months');
            $table->decimal('utility_total_action_month', 18, 4)->default(0)->after('utility_action_month');
            $table->decimal('utility_available_snapshot', 14, 2)->default(0)->after('utility_total_action_month');
            $table->decimal('utility_estimated_amount', 14, 2)->default(0)->after('utility_available_snapshot');
            $table->decimal('utility_paid_now', 14, 2)->default(0)->after('utility_estimated_amount');
            $table->json('utility_calculation_breakdown')->nullable()->after('utility_paid_now');
        });
    }

    public function down(): void
    {
        Schema::table('member_account_closures', fn (Blueprint $table) => $table->dropColumn([
            'utility_mode', 'utility_status', 'utility_period_year', 'utility_actions_considered',
            'utility_productive_months', 'utility_action_month', 'utility_total_action_month',
            'utility_available_snapshot', 'utility_estimated_amount', 'utility_paid_now', 'utility_calculation_breakdown',
        ]));
    }
};
