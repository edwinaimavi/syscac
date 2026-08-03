<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeMemberCodes();
        $this->convertExternalGuarantorsThatAreMembers();
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }

    private function normalizeMemberCodes(): void
    {
        $usedCodes = DB::table('members')
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->all();

        $maxNumber = DB::table('members')
            ->whereNotNull('code')
            ->where('code', 'like', 'SOC-%')
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^SOC-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        DB::table('members')
            ->whereNotNull('code')
            ->where('code', 'like', 'SOC-%')
            ->orderBy('id')
            ->get(['id', 'code'])
            ->each(function ($member) use (&$usedCodes, &$maxNumber) {
                if (! preg_match('/^SOC-(\d+)$/', $member->code, $matches)) {
                    return;
                }

                $desiredCode = 'SOC-' . str_pad((string) ((int) $matches[1]), 6, '0', STR_PAD_LEFT);

                if ($desiredCode === $member->code) {
                    return;
                }

                if (isset($usedCodes[$desiredCode]) && (int) $usedCodes[$desiredCode] !== (int) $member->id) {
                    do {
                        $maxNumber++;
                        $desiredCode = 'SOC-' . str_pad((string) $maxNumber, 6, '0', STR_PAD_LEFT);
                    } while (isset($usedCodes[$desiredCode]));
                }

                unset($usedCodes[$member->code]);
                DB::table('members')->where('id', $member->id)->update([
                    'code' => $desiredCode,
                    'updated_at' => now(),
                ]);
                $usedCodes[$desiredCode] = $member->id;
            });
    }

    private function convertExternalGuarantorsThatAreMembers(): void
    {
        DB::table('guarantors')
            ->where('type', 'externo')
            ->whereNotNull('dni')
            ->where('status', '!=', 'anulado')
            ->orderBy('id')
            ->get()
            ->each(function ($guarantor) {
                $member = DB::table('members')
                    ->where('dni', $guarantor->dni)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $member) {
                    return;
                }

                $existingInternal = DB::table('guarantors')
                    ->where('type', 'socio')
                    ->where('member_id', $member->id)
                    ->where('id', '!=', $guarantor->id)
                    ->first();

                if ($existingInternal) {
                    $this->moveGuarantorLinks((int) $guarantor->id, (int) $existingInternal->id);

                    DB::table('guarantors')->where('id', $guarantor->id)->update([
                        'type' => 'socio',
                        'member_id' => $member->id,
                        'status' => 'inactivo',
                        'updated_at' => now(),
                    ]);

                    return;
                }

                DB::table('guarantors')->where('id', $guarantor->id)->update([
                    'type' => 'socio',
                    'member_id' => $member->id,
                    'dni' => $member->dni,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'full_name' => $member->full_name,
                    'phone' => $member->phone,
                    'address' => $member->address,
                    'status' => 'activo',
                    'updated_at' => now(),
                ]);
            });
    }

    private function moveGuarantorLinks(int $fromGuarantorId, int $toGuarantorId): void
    {
        DB::table('member_guarantors')
            ->where('guarantor_id', $fromGuarantorId)
            ->orderBy('id')
            ->get()
            ->each(function ($link) use ($toGuarantorId) {
                $duplicate = DB::table('member_guarantors')
                    ->where('member_id', $link->member_id)
                    ->where('guarantor_id', $toGuarantorId)
                    ->where('relationship_type', $link->relationship_type)
                    ->first();

                if ($duplicate) {
                    DB::table('member_guarantors')->where('id', $link->id)->update([
                        'status' => 'inactivo',
                        'updated_at' => now(),
                    ]);

                    return;
                }

                DB::table('member_guarantors')->where('id', $link->id)->update([
                    'guarantor_id' => $toGuarantorId,
                    'updated_at' => now(),
                ]);
            });
    }
};
