<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\MemberShare;
use App\Models\SolidarityMovement;
use App\Models\AdministrativeFundMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShareCashMovementService
{
    public function sync(MemberShare $share): Collection
    {
        $share->loadMissing('member');
        return DB::transaction(function () use ($share) {
            $components = [
                'accion_socio' => ['amount' => (float) ($share->share_capital_amount ?? $share->amount), 'concept' => 'Capital de acciones del socio '],
                'solidaridad_aporte' => ['amount' => (float) $share->solidarity_amount, 'concept' => 'Solidaridad del aporte del socio '],
                'gasto_administrativo_aporte' => ['amount' => (float) $share->administrative_fee_amount, 'concept' => 'Cuota administrativa del aporte del socio '],
            ];
            $movements = collect();
            foreach ($components as $category => $component) {
                $movement = CashMovement::query()->where('related_type', MemberShare::class)->where('related_id', $share->id)->where('category', $category)->lockForUpdate()->first();
                if ($component['amount'] <= 0) {
                    if ($movement) $movement->update(['status' => 'anulado', 'annulled_at' => now(), 'annulled_by' => auth()->id()]);
                    continue;
                }
                $movement ??= new CashMovement(['movement_number' => CashMovement::nextCode(), 'created_by' => $share->created_by ?: auth()->id()]);
                $movement->fill([
                    'movement_date' => $share->date, 'type' => 'ingreso', 'category' => $category,
                    'concept' => $component['concept'].($share->member?->full_name ?: 'sin nombre'), 'amount' => $component['amount'],
                    'payment_method' => $share->payment_method, 'reference' => $share->payment_reference, 'voucher_path' => $share->voucher_path,
                    'related_type' => MemberShare::class, 'related_id' => $share->id, 'observation' => $share->observation,
                    'status' => 'registrado', 'updated_by' => auth()->id(), 'annulled_at' => null, 'annulled_by' => null,
                ])->save();
                $movements->push($movement->fresh());
            }
            $this->syncSolidarityFundMovement($share, $movements->firstWhere('category', 'solidaridad_aporte'));
            $this->syncAdministrativeFundMovement($share, $movements->firstWhere('category', 'gasto_administrativo_aporte'));
            $this->recalculateBalances();
            return $movements;
        });
    }

    public function annul(MemberShare $share): void
    {
        DB::transaction(function () use ($share) {
            CashMovement::query()->where('related_type', MemberShare::class)->where('related_id', $share->id)->update([
                'status' => 'anulado', 'balance_before' => null, 'balance_after' => null, 'updated_by' => auth()->id(), 'annulled_by' => auth()->id(), 'annulled_at' => now(),
            ]);
            SolidarityMovement::query()->where('source_type', MemberShare::class)->where('source_id', $share->id)->update([
                'status' => 'anulado', 'updated_by' => auth()->id(), 'annulled_by' => auth()->id(), 'annulled_at' => now(),
            ]);
            AdministrativeFundMovement::query()->where('source_type', MemberShare::class)->where('source_id', $share->id)->update([
                'status'=>'anulado','updated_by'=>auth()->id(),'cancelled_by'=>auth()->id(),'cancelled_at'=>now(),
                'cancellation_reason'=>'Anulación automática del aporte de origen.',
            ]);
            $this->recalculateBalances();
        });
    }

    private function syncSolidarityFundMovement(MemberShare $share, ?CashMovement $cashMovement): void
    {
        $movement = SolidarityMovement::withTrashed()
            ->where('source_type', MemberShare::class)->where('source_id', $share->id)
            ->lockForUpdate()->first();

        if ((float) $share->solidarity_amount <= 0 || ! $cashMovement) {
            if ($movement) $movement->update([
                'status' => 'anulado', 'updated_by' => auth()->id(), 'annulled_by' => auth()->id(), 'annulled_at' => now(),
            ]);
            return;
        }

        $movement ??= new SolidarityMovement([
            'code' => SolidarityMovement::nextCode(), 'source_type' => MemberShare::class,
            'source_id' => $share->id, 'created_by' => $share->created_by ?: auth()->id(),
        ]);
        if ($movement->trashed()) $movement->restore();
        $movement->fill([
            'member_id' => $share->member_id, 'date' => $share->date, 'movement_date' => $share->date,
            'type' => 'ingreso', 'concept' => 'Solidaridad del aporte del socio ' . ($share->member?->full_name ?: 'sin nombre'),
            'amount' => $share->solidarity_amount, 'payment_method' => $share->payment_method,
            'payment_reference' => $share->payment_reference, 'voucher_path' => $share->voucher_path,
            'cash_movement_id' => $cashMovement->id, 'observation' => $share->observation,
            'status' => 'registrado', 'updated_by' => auth()->id(), 'annulled_by' => null, 'annulled_at' => null,
        ])->save();
    }

    private function syncAdministrativeFundMovement(MemberShare $share, ?CashMovement $cashMovement): void
    {
        $movement=AdministrativeFundMovement::withTrashed()->where('source_type',MemberShare::class)->where('source_id',$share->id)->lockForUpdate()->first();
        if((float)$share->administrative_fee_amount<=0||!$cashMovement){
            if($movement)$movement->update(['status'=>'anulado','updated_by'=>auth()->id(),'cancelled_by'=>auth()->id(),'cancelled_at'=>now(),'cancellation_reason'=>'El aporte ya no contiene cuota administrativa.']);
            return;
        }
        $movement??=new AdministrativeFundMovement(['code'=>AdministrativeFundMovement::nextCode(),'source_type'=>MemberShare::class,'source_id'=>$share->id,'created_by'=>$share->created_by?:auth()->id()]);
        if($movement->trashed())$movement->restore();
        $movement->fill(['movement_date'=>$share->date,'type'=>'ingreso','member_id'=>$share->member_id,
            'concept'=>'Cuota administrativa del aporte del socio '.($share->member?->full_name?:'sin nombre'),'amount'=>$share->administrative_fee_amount,
            'payment_method'=>$share->payment_method,'payment_reference'=>$share->payment_reference,'voucher_path'=>$share->voucher_path,
            'cash_movement_id'=>$cashMovement->id,'status'=>'registrado','observation'=>$share->observation,'updated_by'=>auth()->id(),
            'cancelled_by'=>null,'cancelled_at'=>null,'cancellation_reason'=>null])->save();
    }

    public function syncRegisteredShares(): int
    {
        $count = 0;
        MemberShare::with('member')->where('status', 'registrado')->orderBy('date')->orderBy('id')->chunkById(100, function ($shares) use (&$count) {
            foreach ($shares as $share) { $this->sync($share); $count++; }
        });
        return $count;
    }

    public function recalculateBalances(): void
    {
        $balance = 0.0;
        CashMovement::where('status', 'registrado')->orderBy('movement_date')->orderBy('id')->get()->each(function (CashMovement $movement) use (&$balance) {
            $before = $balance; $balance += $movement->type === 'egreso' ? -(float) $movement->amount : (float) $movement->amount;
            $movement->forceFill(['balance_before' => $before, 'balance_after' => $balance])->save();
        });
    }
}
