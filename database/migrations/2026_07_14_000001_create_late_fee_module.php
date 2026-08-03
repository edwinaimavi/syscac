<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('late_fee_settings', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->unsignedInteger('grace_days')->default(0);
            $table->string('calculation_type', 30); $table->decimal('value', 14, 4);
            $table->decimal('max_amount', 14, 2)->nullable(); $table->boolean('is_active')->default(true);
            $table->boolean('allow_waiver')->default(false); $table->boolean('auto_apply')->default(true);
            $table->text('observation')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->softDeletes();
        });
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->unsignedInteger('late_days')->default(0); $table->decimal('late_fee_amount', 14, 2)->default(0);
            $table->decimal('late_fee_paid', 14, 2)->default(0); $table->decimal('late_fee_waived', 14, 2)->default(0);
            $table->decimal('late_fee_pending', 14, 2)->default(0); $table->timestamp('late_fee_calculated_at')->nullable();
            $table->string('late_fee_status', 20)->default('no_mora'); $table->foreignId('late_fee_setting_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 14, 2)->default(0); $table->decimal('late_fee_paid', 14, 2)->default(0);
            $table->decimal('late_fee_waived', 14, 2)->default(0); $table->text('late_fee_reason')->nullable();
            $table->unsignedInteger('late_fee_days')->default(0); $table->foreignId('late_fee_setting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('late_fee_waived_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('late_fee_waived_at')->nullable();
        });
        Schema::table('loan_payment_details', function (Blueprint $table) {
            $table->decimal('late_fee_paid', 14, 2)->default(0); $table->decimal('late_fee_waived', 14, 2)->default(0);
            $table->unsignedInteger('late_fee_days')->default(0);
        });
    }
    public function down(): void
    {
        Schema::table('loan_payment_details', fn (Blueprint $t) => $t->dropColumn(['late_fee_paid','late_fee_waived','late_fee_days']));
        Schema::table('loan_payments', function (Blueprint $t) { $t->dropConstrainedForeignId('late_fee_setting_id'); $t->dropConstrainedForeignId('late_fee_waived_by'); $t->dropColumn(['late_fee_amount','late_fee_paid','late_fee_waived','late_fee_reason','late_fee_days','late_fee_waived_at']); });
        Schema::table('loan_installments', function (Blueprint $t) { $t->dropConstrainedForeignId('late_fee_setting_id'); $t->dropColumn(['late_days','late_fee_amount','late_fee_paid','late_fee_waived','late_fee_pending','late_fee_calculated_at','late_fee_status']); });
        Schema::dropIfExists('late_fee_settings');
    }
};
