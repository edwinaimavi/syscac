<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('member_shares', function (Blueprint $table) {
            $table->decimal('total_paid', 14, 2)->nullable()->after('amount');
            $table->decimal('share_capital_amount', 14, 2)->nullable()->after('total_paid');
            $table->decimal('solidarity_amount', 14, 2)->default(0)->after('share_capital_amount');
            $table->decimal('administrative_fee_amount', 14, 2)->default(0)->after('solidarity_amount');
        });
        DB::table('member_shares')->update([
            'total_paid' => DB::raw('amount'),
            'share_capital_amount' => DB::raw('amount'),
            'solidarity_amount' => 0,
            'administrative_fee_amount' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('member_shares', fn (Blueprint $table) => $table->dropColumn(['total_paid', 'share_capital_amount', 'solidarity_amount', 'administrative_fee_amount']));
    }
};
