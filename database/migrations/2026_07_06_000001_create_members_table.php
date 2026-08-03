<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('dni')->unique()->nullable();
            $table->date('birth_date')->nullable();
            $table->date('admission_date')->nullable();
            $table->date('retirement_date')->nullable();
            $table->string('current_job')->nullable();
            $table->string('address')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('reference_name')->nullable();
            $table->string('reference_dni')->nullable();
            $table->string('reference_phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('vigente');
            $table->text('observation')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
