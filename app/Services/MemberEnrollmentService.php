<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\MemberEnrollment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class MemberEnrollmentService
{
    public function __construct(private readonly ShareCashMovementService $cashService) {}

    public function sync(MemberEnrollment $enrollment): void
    {
        $enrollment->loadMissing('member');
        DB::transaction(function () use ($enrollment) {
            $movement = CashMovement::firstOrNew(['related_type' => MemberEnrollment::class, 'related_id' => $enrollment->id]);
            if (! $movement->exists) {
                $movement->movement_number = CashMovement::nextCode();
                $movement->created_by = $enrollment->created_by;
            }
            $movement->fill([
                'movement_date' => $enrollment->enrollment_date, 'type' => 'ingreso', 'category' => 'inscripcion_socio',
                'concept' => 'Inscripcion de socio ' . ($enrollment->member?->full_name ?: '-'), 'amount' => $enrollment->amount,
                'payment_method' => $enrollment->payment_method, 'reference' => $enrollment->payment_reference,
                'voucher_path' => $enrollment->voucher_path, 'observation' => $enrollment->observation,
                'status' => 'registrado', 'updated_by' => auth()->id(), 'annulled_at' => null, 'annulled_by' => null,
            ])->save();

            $receipt = Receipt::firstOrNew(['related_type' => MemberEnrollment::class, 'related_id' => $enrollment->id]);
            if (! $receipt->exists) {
                $receipt->receipt_number = $this->nextReceiptNumber();
                $receipt->created_by = $enrollment->created_by;
            }
            $receipt->fill([
                'receipt_date' => $enrollment->enrollment_date, 'member_id' => $enrollment->member_id,
                'type' => 'inscripcion_socio', 'amount' => $enrollment->amount, 'payment_method' => $enrollment->payment_method,
                'payment_reference' => $enrollment->payment_reference, 'voucher_path' => $enrollment->voucher_path,
                'observation' => $enrollment->observation, 'status' => 'registrado', 'updated_by' => auth()->id(),
            ])->save();
            $enrollment->updateQuietly(['receipt_id' => $receipt->id]);
            $this->cashService->recalculateBalances();
        });
    }

    public function annul(MemberEnrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment) {
            $values = ['status' => 'anulado', 'updated_by' => auth()->id(), 'annulled_by' => auth()->id(), 'annulled_at' => now()];
            $enrollment->update($values);
            CashMovement::where('related_type', MemberEnrollment::class)->where('related_id', $enrollment->id)->update($values);
            Receipt::where('related_type', MemberEnrollment::class)->where('related_id', $enrollment->id)->update(['status' => 'anulado', 'updated_by' => auth()->id()]);
            $this->cashService->recalculateBalances();
        });
    }

    private function nextReceiptNumber(): string
    {
        $last = Receipt::withTrashed()->where('receipt_number', 'like', 'REC-%')->orderByDesc('id')->lockForUpdate()->value('receipt_number');
        $number = $last && preg_match('/REC-(\d+)/', $last, $matches) ? (int) $matches[1] : 0;
        return 'REC-' . str_pad((string) ($number + 1), 6, '0', STR_PAD_LEFT);
    }
}
