<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_loans')->default(0);
            $table->unsignedInteger('paid_loans')->default(0);
            $table->unsignedInteger('active_loans')->default(0);
            $table->unsignedInteger('on_time_payments')->default(0);
            $table->unsignedInteger('mild_late_payments')->default(0);
            $table->unsignedInteger('serious_late_payments')->default(0);
            $table->unsignedInteger('late_payments')->default(0);
            $table->unsignedInteger('max_days_late')->default(0);
            $table->decimal('average_days_late', 8, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->date('last_payment_date')->nullable();
            $table->date('last_loan_date')->nullable();
            $table->unsignedInteger('active_overdue_installments')->default(0);
            $table->decimal('active_overdue_amount', 14, 2)->default(0);
            $table->unsignedTinyInteger('score')->default(100);
            $table->string('status', 30)->default('excelente');
            $table->string('color', 20)->default('verde');
            $table->text('recommendation')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credit_history_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->unsignedInteger('days_late')->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('principal_amount', 14, 2)->default(0);
            $table->decimal('interest_amount', 14, 2)->default(0);
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'event_type']);
            $table->index(['payment_date', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_history_events');
        Schema::dropIfExists('credit_histories');
    }
};
