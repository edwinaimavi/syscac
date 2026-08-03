<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('member_shares', 'code')) {
            return;
        }

        $lastNumber = 0;
        $lastCode = DB::table('member_shares')
            ->whereNotNull('code')
            ->where('code', 'like', 'APO-%')
            ->orderByDesc('id')
            ->value('code');

        if ($lastCode && preg_match('/APO-(\d+)/', $lastCode, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        DB::table('member_shares')
            ->whereNull('code')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($shares) use (&$lastNumber) {
                foreach ($shares as $share) {
                    $lastNumber++;

                    DB::table('member_shares')
                        ->where('id', $share->id)
                        ->update([
                            'code' => 'APO-' . str_pad((string) $lastNumber, 6, '0', STR_PAD_LEFT),
                        ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
