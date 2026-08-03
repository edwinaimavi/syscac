<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_account_closures')) {
            Schema::create('member_account_closures', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->date('closure_date');
                $table->date('retirement_date');
                $table->decimal('total_contributions', 14, 2)->default(0);
                $table->decimal('total_shares', 14, 4)->default(0);
                $table->decimal('pending_loans_amount', 14, 2)->default(0);
                $table->decimal('pending_utilities_amount', 14, 2)->default(0);
                $table->decimal('total_in_favor', 14, 2)->default(0);
                $table->decimal('total_against', 14, 2)->default(0);
                $table->decimal('final_balance', 14, 2)->default(0);
                $table->string('settlement_type')->default('sin_saldo');
                $table->string('payment_method')->nullable();
                $table->string('payment_reference')->nullable();
                $table->string('voucher_path')->nullable();
                $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
                $table->text('reason');
                $table->text('observation')->nullable();
                $table->string('status')->default('calculado');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('annulled_by')->nullable();
                $table->timestamp('annulled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('member_account_closure_details')) {
            Schema::create('member_account_closure_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_account_closure_id')->constrained('member_account_closures')->cascadeOnDelete();
                $table->string('item_type');
                $table->string('description');
                $table->decimal('amount', 14, 2)->default(0);
                $table->string('sign');
                $table->string('related_type')->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_account_closure_details');
        Schema::dropIfExists('member_account_closures');
    }
};
