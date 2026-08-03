<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('loan_number')->unique();
            $table->decimal('requested_amount', 14, 2)->default(0);
            $table->decimal('approved_amount', 14, 2)->default(0);
            $table->decimal('interest_rate', 8, 2)->default(0);
            $table->string('interest_type')->default('anual');
            $table->unsignedInteger('term_months')->default(0);
            $table->date('start_date')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->string('payment_frequency')->default('mensual');
            $table->string('amortization_method')->default('aleman');
            $table->decimal('total_interest', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->decimal('current_balance', 14, 2)->nullable();
            $table->string('status')->default('simulado');
            $table->string('purpose')->nullable();
            $table->text('observation')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
