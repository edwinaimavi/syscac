<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_refinancings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('new_loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('refinancing_date');
            $table->decimal('previous_balance', 14, 2)->default(0);
            $table->decimal('new_amount', 14, 2)->default(0);
            $table->decimal('interest_rate', 8, 2)->default(0);
            $table->unsignedInteger('term_months')->default(0);
            $table->text('reason')->nullable();
            $table->text('observation')->nullable();
            $table->string('status')->default('registrado');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_refinancings');
    }
};
