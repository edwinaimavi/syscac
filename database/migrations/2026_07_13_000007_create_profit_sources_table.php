<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('source_date');
            $table->decimal('amount', 14, 2);
            $table->string('reason', 180);
            $table->text('observation')->nullable();
            $table->string('status')->default('activo');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annulled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_sources');
    }
};
