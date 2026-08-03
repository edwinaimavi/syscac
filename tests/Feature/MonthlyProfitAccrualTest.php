<?php
use App\Models\{Loan,LoanPayment,Member,MemberShare,MonthlyProfitAccrual,ProfitSource,User};
use App\Services\MonthlyProfitAccrualService;
use Illuminate\Support\Facades\Gate;
beforeEach(function(){Gate::before(fn()=>true);$this->actingAs(User::factory()->create());});
it('starts a contribution in the following month and persists an auditable monthly accrual',function(){
 $a=Member::create(['code'=>'SOC-MON-A','first_name'=>'A','full_name'=>'Socio A','dni'=>'71111111','status'=>'vigente']);
 $b=Member::create(['code'=>'SOC-MON-B','first_name'=>'B','full_name'=>'Socio B','dni'=>'72222222','status'=>'vigente']);
 MemberShare::create(['code'=>'APO-MON-A','member_id'=>$a->id,'date'=>'2026-06-15','amount'=>200,'share_value'=>20,'shares_quantity'=>10,'payment_method'=>'efectivo','status'=>'registrado']);
 MemberShare::create(['code'=>'APO-MON-B','member_id'=>$b->id,'date'=>'2026-07-15','amount'=>200,'share_value'=>20,'shares_quantity'=>10,'payment_method'=>'efectivo','status'=>'registrado']);
 ProfitSource::create(['code'=>'UFM-MON-1','source_date'=>'2026-07-20','amount'=>100,'adjustment_type'=>'positive','reason'=>'Prueba','status'=>'activo']);
 $july=app(MonthlyProfitAccrualService::class)->save('2026-07-01');
 expect($july->details()->count())->toBe(1)->and((float)$july->details()->first()->profit_amount)->toBe(100.0);
 ProfitSource::create(['code'=>'UFM-MON-2','source_date'=>'2026-08-20','amount'=>100,'adjustment_type'=>'positive','reason'=>'Prueba','status'=>'activo']);
 $august=app(MonthlyProfitAccrualService::class)->save('2026-08-01');
 expect($august->details()->count())->toBe(2)->and((float)$august->details()->sum('profit_amount'))->toBe(100.0);
});
it('marks a calculated month outdated when a payment is annulled',function(){
 MonthlyProfitAccrual::create(['code'=>'UMS-TEST','month'=>'2026-07-01','interest_collected'=>6,'late_fees_collected'=>16,'total_profit'=>22,'total_shares'=>10,'profit_per_share'=>2.2,'status'=>'calculada']);
 $member=Member::create(['code'=>'SOC-MON-C','first_name'=>'C','full_name'=>'Socio C','dni'=>'73333333','status'=>'vigente']);
 $loan=Loan::create(['member_id'=>$member->id,'loan_number'=>'PRE-MON-1','approved_amount'=>100,'status'=>'desembolsado']);
 $payment=LoanPayment::create(['loan_id'=>$loan->id,'member_id'=>$member->id,'payment_number'=>'COB-MON-1','payment_date'=>'2026-07-20','amount'=>122,'status'=>'registrado']);
 $payment->update(['status'=>'anulado']);
 expect(MonthlyProfitAccrual::first()->status)->toBe('desactualizada');
});
