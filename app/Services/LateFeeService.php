<?php
namespace App\Services;
use App\Models\LateFeeSetting;
use App\Models\LoanInstallment;
use Carbon\CarbonInterface;
class LateFeeService
{
    public function quote(LoanInstallment $installment, CarbonInterface|string $date, ?LateFeeSetting $setting=null): array
    {
        $setting ??= LateFeeSetting::active(); $date=\Illuminate\Support\Carbon::parse($date)->startOfDay();
        if (!$setting || !$installment->due_date || $installment->due_date->copy()->addDays($setting->grace_days)->gte($date) || in_array($installment->status,['pagado','adelantado','liquidado'],true) || (float)$installment->late_fee_waived > 0) return ['days'=>0,'amount'=>0.0,'pending'=>max(0,(float)$installment->late_fee_amount-(float)$installment->late_fee_paid-(float)$installment->late_fee_waived),'setting'=>$setting];
        $days=(int) $installment->due_date->copy()->addDays($setting->grace_days)->diffInDays($date);
        $base=max(0,(float)$installment->remaining_amount);
        $amount=match($setting->calculation_type){'fixed_daily'=>$days*(float)$setting->value,'percentage_daily'=>$base*(float)$setting->value*$days/100,'fixed_once'=>(float)$setting->value,default=>0};
        if ($setting->max_amount !== null) $amount=min($amount,(float)$setting->max_amount);
        $amount=round($amount,2); return ['days'=>$days,'amount'=>$amount,'pending'=>max(0,$amount-(float)$installment->late_fee_paid-(float)$installment->late_fee_waived),'setting'=>$setting];
    }
    public function persistQuote(LoanInstallment $row, CarbonInterface|string $date): array
    { $q=$this->quote($row,$date); $status=$q['pending']<=.009?($q['amount']>0?'pagada':'no_mora'):'pendiente'; $row->update(['late_days'=>$q['days'],'late_fee_amount'=>$q['amount'],'late_fee_pending'=>$q['pending'],'late_fee_status'=>$status,'late_fee_calculated_at'=>now(),'late_fee_setting_id'=>$q['setting']?->id]); return $q; }
}
