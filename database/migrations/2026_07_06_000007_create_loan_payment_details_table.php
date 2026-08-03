<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_payment_id')->constrained('loan_payments')->cascadeOnDelete();
            $table->foreignId('loan_installment_id')->nullable()->constrained('loan_installments')->nullOnDelete();
            $table->decimal('principal_paid', 14, 2)->default(0);
            $table->decimal('interest_paid', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('previous_balance', 14, 2)->nullable();
            $table->decimal('new_balance', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payment_details');
    }
};
