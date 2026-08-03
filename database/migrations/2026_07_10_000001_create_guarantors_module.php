<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guarantors')) {
            Schema::create('guarantors', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique()->nullable();
                $table->string('type')->default('externo');
                $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->string('dni', 20)->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('full_name')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('address')->nullable();
                $table->text('observation')->nullable();
                $table->string('status')->default('activo');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('member_guarantors')) {
            Schema::create('member_guarantors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('guarantor_id')->constrained('guarantors')->cascadeOnDelete();
                $table->string('relationship_type')->default('aval');
                $table->text('observation')->nullable();
                $table->string('status')->default('activo');
                $table->timestamps();
                $table->unique(['member_id', 'guarantor_id', 'relationship_type'], 'member_guarantor_unique');
            });

            return;
        }

        Schema::table('member_guarantors', function (Blueprint $table) {
            $table->unique(['member_id', 'guarantor_id', 'relationship_type'], 'member_guarantor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_guarantors');
        Schema::dropIfExists('guarantors');
    }
};
