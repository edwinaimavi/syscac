<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantors', function (Blueprint $table) {
            if (! Schema::hasColumn('guarantors', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('address');
            }

            if (! Schema::hasColumn('guarantors', 'occupation')) {
                $table->string('occupation')->nullable()->after('photo_path');
            }

            if (! Schema::hasColumn('guarantors', 'relationship')) {
                $table->string('relationship')->nullable()->after('occupation');
            }
        });

        Schema::table('member_guarantors', function (Blueprint $table) {
            if (! Schema::hasColumn('member_guarantors', 'is_main')) {
                $table->boolean('is_main')->default(true)->after('relationship_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_guarantors', function (Blueprint $table) {
            if (Schema::hasColumn('member_guarantors', 'is_main')) {
                $table->dropColumn('is_main');
            }
        });

        Schema::table('guarantors', function (Blueprint $table) {
            foreach (['relationship', 'occupation', 'photo_path'] as $column) {
                if (Schema::hasColumn('guarantors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
