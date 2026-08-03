<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('administrative_fund_movements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('movement_date');
            $table->string('type', 10);
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('concept');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete();
            $table->string('status')->default('registrado');
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['source_type', 'source_id']);
            $table->unique('cash_movement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('administrative_fund_movements'); }
};
