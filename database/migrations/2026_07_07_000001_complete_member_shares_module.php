<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_shares', function (Blueprint $table) {
            if (! Schema::hasColumn('member_shares', 'code')) {
                $table->string('code', 50)->nullable()->unique()->after('id');
            }

            if (Schema::hasColumn('member_shares', 'shares_quantity')) {
                $table->decimal('shares_quantity', 14, 4)->default(0)->change();
            }

            if (! Schema::hasColumn('member_shares', 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable()->after('updated_by');
            }

            if (! Schema::hasColumn('member_shares', 'annulled_by')) {
                $table->unsignedBigInteger('annulled_by')->nullable()->after('annulled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_shares', function (Blueprint $table) {
            if (Schema::hasColumn('member_shares', 'annulled_by')) {
                $table->dropColumn('annulled_by');
            }

            if (Schema::hasColumn('member_shares', 'annulled_at')) {
                $table->dropColumn('annulled_at');
            }

            if (Schema::hasColumn('member_shares', 'shares_quantity')) {
                $table->unsignedInteger('shares_quantity')->default(0)->change();
            }

            if (Schema::hasColumn('member_shares', 'code')) {
                $table->dropUnique('member_shares_code_unique');
                $table->dropColumn('code');
            }
        });
    }
};
