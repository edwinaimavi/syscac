<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_distribution_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profit_distribution_id')->constrained('profit_distributions')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedInteger('shares_quantity')->default(0);
            $table->decimal('participation_percentage', 8, 4)->default(0);
            $table->decimal('profit_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status')->default('pendiente');
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_distribution_details');
    }
};
