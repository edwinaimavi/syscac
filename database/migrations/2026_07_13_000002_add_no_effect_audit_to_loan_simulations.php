<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_simulations', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_simulations', 'effect_reason')) $table->text('effect_reason')->nullable()->after('status');
            if (! Schema::hasColumn('loan_simulations', 'effected_by')) $table->foreignId('effected_by')->nullable()->after('effect_reason')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('loan_simulations', 'effected_at')) $table->timestamp('effected_at')->nullable()->after('effected_by');
        });

        $pending = DB::table('loan_simulations')
            ->join('members', 'members.id', '=', 'loan_simulations.member_id')
            ->where('loan_simulations.status', 'simulada')
            ->where('members.status', '!=', 'vigente')
            ->select('loan_simulations.id', 'loan_simulations.member_id')
            ->get();

        foreach ($pending as $simulation) {
            $closure = DB::table('member_account_closures')
                ->where('member_id', $simulation->member_id)
                ->where('status', 'cerrado')
                ->latest('closed_at')
                ->first(['closed_by', 'closed_at']);
            DB::table('loan_simulations')->where('id', $simulation->id)->update([
                'status' => 'sin_efecto',
                'effect_reason' => 'Socio retirado / cierre de cuenta confirmado.',
                'effected_by' => $closure?->closed_by,
                'effected_at' => $closure?->closed_at ?? now(),
                'updated_by' => $closure?->closed_by,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loan_simulations', function (Blueprint $table) {
            if (Schema::hasColumn('loan_simulations', 'effected_by')) $table->dropConstrainedForeignId('effected_by');
            if (Schema::hasColumn('loan_simulations', 'effected_at')) $table->dropColumn('effected_at');
            if (Schema::hasColumn('loan_simulations', 'effect_reason')) $table->dropColumn('effect_reason');
        });
    }
};
