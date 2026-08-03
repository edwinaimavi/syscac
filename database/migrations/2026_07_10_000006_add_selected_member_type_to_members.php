<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'member_type_selected')) {
                $table->string('member_type_selected', 20)->nullable()->after('member_type');
            }
        });
        DB::table('members')->whereNull('member_type_selected')->update(['member_type_selected' => DB::raw('member_type')]);
    }

    public function down(): void
    {
        // Se conserva por trazabilidad de la seleccion realizada por el usuario.
    }
};
