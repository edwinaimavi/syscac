<?php
use App\Http\Controllers\Admin\ProfitDistributionController;
use App\Models\{Member,MemberShare,ProfitDistribution,ProfitSource,User};
use App\Services\ProfitAvailabilityService;
use Illuminate\Support\Facades\Gate;

beforeEach(function(){Gate::before(fn()=>true);$this->actingAs(User::factory()->create());});

function cycleMember(string $code,string $dni):Member{return Member::create(['code'=>$code,'first_name'=>$code,'full_name'=>$code,'dni'=>$dni,'status'=>'vigente']);}
function cycleShare(Member $m,string $code,string $date,float $actions):void{MemberShare::create(['code'=>$code,'member_id'=>$m->id,'date'=>$date,'amount'=>$actions*20,'share_value'=>20,'shares_quantity'=>$actions,'payment_method'=>'efectivo','status'=>'registrado']);}

it('weights the march to march cycle as twelve eleven one and zero months',function(){
 $a=cycleMember('SOC-CYC-A','70000001');$b=cycleMember('SOC-CYC-B','70000002');$c=cycleMember('SOC-CYC-C','70000003');$d=cycleMember('SOC-CYC-D','70000004');
 cycleShare($a,'APO-CYC-A','2026-02-15',10);cycleShare($b,'APO-CYC-B','2026-04-10',10);cycleShare($c,'APO-CYC-C','2027-02-10',10);cycleShare($d,'APO-CYC-D','2027-03-01',10);
 $method=new ReflectionMethod(ProfitDistributionController::class,'calculationPayload');
 $payload=$method->invoke(app(ProfitDistributionController::class),240,'2026-03-01','2027-03-01');
 $rows=collect($payload['details'])->keyBy('member_code');
 expect($rows['SOC-CYC-A']['action_month'])->toBe(120.0)
  ->and($rows['SOC-CYC-B']['action_month'])->toBe(110.0)
  ->and($rows['SOC-CYC-C']['action_month'])->toBe(10.0)
  ->and($rows->has('SOC-CYC-D'))->toBeFalse()
  ->and((float)$payload['summary']['total_action_month'])->toBe(240.0)
  ->and((float)$payload['details']->sum('profit_amount'))->toBe(240.0);
});

it('audits positive and negative adjustments in cycle availability',function(){
 ProfitSource::create(['code'=>'UFM-CYC-1','source_date'=>'2026-04-01','amount'=>500,'adjustment_type'=>'positive','reason'=>'Ajuste positivo','status'=>'activo']);
 ProfitSource::create(['code'=>'UFM-CYC-2','source_date'=>'2026-05-01','amount'=>100,'adjustment_type'=>'previous_year_discount','reason'=>'Descuento año anterior','status'=>'activo']);
 $summary=app(ProfitAvailabilityService::class)->summary('2026-03-01','2027-03-01');
 expect($summary['positiveAdjustments'])->toBe(500.0)->and($summary['negativeAdjustments'])->toBe(100.0)->and($summary['available'])->toBe(400.0);
});

it('blocks a cycle overlapping a confirmed distribution',function(){
 ProfitDistribution::create(['code'=>'UTI-CYC-1','period_year'=>2026,'start_date'=>'2026-03-01','end_date'=>'2027-03-01','total_profit'=>100,'total_shares'=>1,'profit_per_share'=>100,'status'=>'aprobado']);
 $member=cycleMember('SOC-CYC-E','70000005');cycleShare($member,'APO-CYC-E','2026-02-01',10);
 ProfitSource::create(['code'=>'UFM-CYC-3','source_date'=>'2026-04-01','amount'=>200,'adjustment_type'=>'positive','reason'=>'Disponible','status'=>'activo']);
 $this->postJson(route('admin.utilidades.store'),['period_year'=>2026,'start_date'=>'2026-03-01','end_date'=>'2027-03-01','total_profit'=>100,'status'=>'calculado'])
  ->assertUnprocessable()->assertJsonValidationErrors('start_date');
});
