<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_account_closures')
            ->where('status', 'calculado')
            ->where(function ($query) {
                $query->where('final_balance', '<', -0.009)
                    ->orWhereColumn('total_against', '>', 'total_in_favor');
            })
            ->update(['status' => 'pendiente_regularizacion']);
    }

    public function down(): void
    {
        DB::table('member_account_closures')
            ->where('status', 'pendiente_regularizacion')
            ->update(['status' => 'calculado']);
    }
};
