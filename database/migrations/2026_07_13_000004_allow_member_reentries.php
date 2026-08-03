<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique('members_dni_unique');
            $table->foreignId('reentry_from_member_id')->nullable()->after('id')->constrained('members')->nullOnDelete();
            $table->index('dni');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['reentry_from_member_id']);
            $table->dropIndex(['dni']);
            $table->dropColumn('reentry_from_member_id');
            $table->unique('dni');
        });
    }
};
