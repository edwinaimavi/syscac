<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('monthly_profit_accruals',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->date('month');$t->decimal('interest_collected',14,2)->default(0);$t->decimal('late_fees_collected',14,2)->default(0);$t->decimal('positive_adjustments',14,2)->default(0);$t->decimal('negative_adjustments',14,2)->default(0);$t->decimal('total_profit',14,2);$t->decimal('total_shares',14,4);$t->decimal('profit_per_share',18,10);$t->string('status')->default('calculada');$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('approved_at')->nullable();$t->timestamps();$t->softDeletes();$t->unique(['month','deleted_at']);});
  Schema::create('monthly_profit_accrual_details',function(Blueprint $t){$t->id();$t->foreignId('monthly_profit_accrual_id')->constrained()->cascadeOnDelete();$t->foreignId('member_id')->constrained()->cascadeOnDelete();$t->decimal('shares_quantity',14,4);$t->decimal('profit_amount',14,2);$t->decimal('paid_amount',14,2)->default(0);$t->string('status')->default('pendiente');$t->timestamps();$t->unique(['monthly_profit_accrual_id','member_id'],'monthly_profit_member_unique');});
 }
 public function down():void{Schema::dropIfExists('monthly_profit_accrual_details');Schema::dropIfExists('monthly_profit_accruals');}
};
