<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solidarity_movements', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('receipt_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('cash_movement_id')->nullable()->after('source_id')->constrained('cash_movements')->nullOnDelete();
            $table->unique(['source_type', 'source_id']);
            $table->unique('cash_movement_id');
        });
    }
    public function down(): void
    {
        Schema::table('solidarity_movements', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_id']);
            $table->dropUnique(['cash_movement_id']);
            $table->dropConstrainedForeignId('cash_movement_id');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
