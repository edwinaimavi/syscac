<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('dni', 8)->nullable();
            $table->string('relationship', 30);
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->decimal('percentage', 5, 2);
            $table->date('birth_date')->nullable();
            $table->boolean('is_minor')->default(false);
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['member_id', 'dni']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_beneficiaries');
    }
};
