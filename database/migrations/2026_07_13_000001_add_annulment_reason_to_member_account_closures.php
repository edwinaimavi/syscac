<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_account_closures', function (Blueprint $table) {
            if (! Schema::hasColumn('member_account_closures', 'annulment_reason')) {
                $table->text('annulment_reason')->nullable()->after('annulled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_account_closures', function (Blueprint $table) {
            if (Schema::hasColumn('member_account_closures', 'annulment_reason')) {
                $table->dropColumn('annulment_reason');
            }
        });
    }
};
