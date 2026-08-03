<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_simulations')) {
            Schema::create('loan_simulations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->date('simulation_date');
                $table->decimal('amount', 14, 2);
                $table->decimal('interest_rate', 8, 2)->default(0);
                $table->string('interest_type', 50)->default('mensual');
                $table->unsignedInteger('term_months');
                $table->date('start_date');
                $table->date('first_payment_date');
                $table->string('amortization_method', 50)->default('aleman');
                $table->decimal('fixed_principal', 14, 2)->default(0);
                $table->decimal('total_interest', 14, 2)->default(0);
                $table->decimal('total_payment', 14, 2)->default(0);
                $table->text('observation')->nullable();
                $table->string('status', 50)->default('simulada');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('annulled_by')->nullable();
                $table->timestamp('annulled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('loan_simulation_installments')) {
            Schema::create('loan_simulation_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_simulation_id')->constrained('loan_simulations')->cascadeOnDelete();
                $table->unsignedInteger('installment_number');
                $table->date('due_date');
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->decimal('principal_amount', 14, 2)->default(0);
                $table->decimal('interest_amount', 14, 2)->default(0);
                $table->decimal('installment_amount', 14, 2)->default(0);
                $table->decimal('closing_balance', 14, 2)->default(0);
                $table->timestamps();

                $table->unique(['loan_simulation_id', 'installment_number'], 'loan_sim_inst_number_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_simulation_installments');
        Schema::dropIfExists('loan_simulations');
    }
};
