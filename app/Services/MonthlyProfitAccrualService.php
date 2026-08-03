<?php
namespace App\Services;
use App\Models\{MemberShare,MonthlyProfitAccrual,ProfitSource};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class MonthlyProfitAccrualService{
 public function preview(string $month):array{
  $start=Carbon::parse($month)->startOfMonth();$end=$start->copy()->addMonth();
  $financial=app(ProfitAvailabilityService::class)->summary($start->toDateString(),$end->copy()->subDay()->toDateString());
  $profit=round($financial['interestCollected']+$financial['lateFeesCollected']+$financial['positiveAdjustments']-$financial['negativeAdjustments'],2);
  $shares=MemberShare::query()->where('status','registrado')->whereDate('date','<',$start)->with('member:id,code,dni,full_name,status')->get()->groupBy('member_id')->map(fn($rows)=>['member'=>$rows->first()->member,'shares'=>round((float)$rows->sum('shares_quantity'),4)])->filter(fn($r)=>$r['member']&&$r['shares']>0);
  $total=round((float)$shares->sum('shares'),4);if($total<=0)throw ValidationException::withMessages(['month'=>['No hay acciones válidas para calcular este mes.']]);
  $rate=$profit>0?$profit/$total:0;$allocated=0;$details=$shares->values()->map(function($r,$i)use($shares,$profit,$rate,&$allocated){$amount=$i===$shares->count()-1?round($profit-$allocated,2):round($r['shares']*$rate,2);$allocated+=$amount;return ['member_id'=>$r['member']->id,'member_name'=>$r['member']->full_name,'member_code'=>$r['member']->code,'member_dni'=>$r['member']->dni,'shares_quantity'=>$r['shares'],'profit_amount'=>$amount];});
  return ['month'=>$start->toDateString(),'financial'=>$financial,'summary'=>['total_profit'=>$profit,'total_shares'=>$total,'profit_per_share'=>round($rate,10),'members_count'=>$details->count()],'details'=>$details];
 }
 public function save(string $month):MonthlyProfitAccrual{return DB::transaction(function()use($month){$p=$this->preview($month);$record=MonthlyProfitAccrual::whereDate('month',$p['month'])->lockForUpdate()->first();if($record&&in_array($record->status,['aprobada','pagada'],true))throw ValidationException::withMessages(['month'=>['El mes aprobado o pagado no puede recalcularse.']]);$record??=new MonthlyProfitAccrual(['code'=>MonthlyProfitAccrual::nextCode(),'month'=>$p['month'],'created_by'=>auth()->id()]);$f=$p['financial'];$record->fill(['interest_collected'=>$f['interestCollected'],'late_fees_collected'=>$f['lateFeesCollected'],'positive_adjustments'=>$f['positiveAdjustments'],'negative_adjustments'=>$f['negativeAdjustments'],'total_profit'=>$p['summary']['total_profit'],'total_shares'=>$p['summary']['total_shares'],'profit_per_share'=>$p['summary']['profit_per_share'],'status'=>'calculada'])->save();$record->details()->delete();$record->details()->createMany($p['details']->map(fn($d)=>['member_id'=>$d['member_id'],'shares_quantity'=>$d['shares_quantity'],'profit_amount'=>$d['profit_amount'],'status'=>'pendiente'])->all());return $record;});}
}
