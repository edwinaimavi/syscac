<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void { Schema::table('profit_sources',fn(Blueprint $t)=>$t->string('adjustment_type',30)->default('positive')->after('amount')); }
    public function down():void { Schema::table('profit_sources',fn(Blueprint $t)=>$t->dropColumn('adjustment_type')); }
};
