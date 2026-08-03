<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'member_type')) {
                $table->string('member_type', 20)->nullable()->after('admission_date');
            }
        });

        if (! Schema::hasTable('member_enrollments')) {
            Schema::create('member_enrollments', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->date('enrollment_date');
                $table->decimal('amount', 14, 2)->default(50);
                $table->string('payment_method', 30);
                $table->string('payment_reference')->nullable();
                $table->string('voucher_path')->nullable();
                $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
                $table->text('observation')->nullable();
                $table->string('status', 20)->default('registrado');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('annulled_by')->nullable();
                $table->timestamp('annulled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('member_guarantors', function (Blueprint $table) {
            if (! Schema::hasColumn('member_guarantors', 'guarantor_member_id')) {
                $table->foreignId('guarantor_member_id')->nullable()->after('member_id')->constrained('members')->nullOnDelete();
            }
        });

        foreach (['loan_simulations', 'loans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'guarantor_member_id')) {
                    $table->foreignId('guarantor_member_id')->nullable()->constrained('members')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'requires_guarantor')) {
                    $table->boolean('requires_guarantor')->default(false);
                }
                if (! Schema::hasColumn($tableName, 'member_type_at_evaluation')) {
                    $table->string('member_type_at_evaluation', 20)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'member_contribution_count_at_evaluation')) {
                    $table->unsignedInteger('member_contribution_count_at_evaluation')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'member_total_contributions_at_evaluation')) {
                    $table->decimal('member_total_contributions_at_evaluation', 14, 2)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'loan_limit_without_guarantor')) {
                    $table->decimal('loan_limit_without_guarantor', 14, 2)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'guarantor_total_contributions_at_evaluation')) {
                    $table->decimal('guarantor_total_contributions_at_evaluation', 14, 2)->nullable();
                }
                if (! Schema::hasColumn($tableName, 'guarantor_requirement_reason')) {
                    $table->string('guarantor_requirement_reason')->nullable();
                }
            });
        }

        DB::table('members')->whereNull('member_type')->orderBy('id')->chunkById(200, function ($members) {
            foreach ($members as $member) {
                $type = $member->admission_date && Carbon::parse($member->admission_date)->lte(now()->subYear()->startOfDay())
                    ? 'antiguo'
                    : 'nuevo';
                DB::table('members')->where('id', $member->id)->update(['member_type' => $type]);
            }
        });

        DB::table('guarantors')->where('type', 'externo')->where('status', 'activo')->update(['status' => 'inactivo']);
    }

    public function down(): void
    {
        // Migracion conservadora: no se eliminan datos ni trazabilidad de negocio.
    }
};
