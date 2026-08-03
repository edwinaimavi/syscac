<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('receipts')->where('type', 'cobro_cuota')->update(['type' => 'cobro_prestamo']);
        DB::table('receipts')->where('type', 'liquidacion')->update(['type' => 'liquidacion_prestamo']);
    }

    public function down(): void
    {
        DB::table('receipts')->where('type', 'cobro_prestamo')->update(['type' => 'cobro_cuota']);
        DB::table('receipts')->where('type', 'liquidacion_prestamo')->update(['type' => 'liquidacion']);
    }
};
