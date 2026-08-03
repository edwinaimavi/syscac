<?php
use App\Models\{AdministrativeFundMovement,CashMovement,Member,MemberShare,User};
use App\Services\ShareCashMovementService;
use Illuminate\Support\Facades\Gate;

beforeEach(function(){Gate::before(fn()=>true);$this->actingAs(User::factory()->create());});

it('creates and annuls a manual expense without allowing a negative fund balance',function(){
 $member=Member::create(['code'=>'SOC-FAD-01','first_name'=>'Socio','full_name'=>'Socio Fondo','dni'=>'76543210','status'=>'vigente']);
 $share=MemberShare::create(['code'=>'APO-FAD-01','member_id'=>$member->id,'date'=>'2026-07-23','amount'=>100,'total_paid'=>105,'share_capital_amount'=>100,'solidarity_amount'=>0,'administrative_fee_amount'=>5,'share_value'=>20,'shares_quantity'=>5,'payment_method'=>'efectivo','status'=>'registrado']);
 app(ShareCashMovementService::class)->sync($share->fresh('member'));
 $this->postJson(route('admin.fondo-administrativo.store'),['movement_date'=>'2026-07-23','type'=>'egreso','concept'=>'Compra de útiles','amount'=>20,'payment_method'=>'efectivo'])
   ->assertUnprocessable()->assertJsonValidationErrors('amount');
 $share->update(['administrative_fee_amount'=>25,'total_paid'=>125]);app(ShareCashMovementService::class)->sync($share->fresh('member'));
 $this->postJson(route('admin.fondo-administrativo.store'),['movement_date'=>'2026-07-23','type'=>'egreso','concept'=>'Compra de útiles','amount'=>20,'payment_method'=>'efectivo'])->assertOk();
 $manual=AdministrativeFundMovement::whereNull('source_type')->firstOrFail();
 expect((float)AdministrativeFundMovement::where('status','registrado')->where('type','ingreso')->sum('amount'))->toBe(25.0)
  ->and((float)AdministrativeFundMovement::where('status','registrado')->where('type','egreso')->sum('amount'))->toBe(20.0)
  ->and(CashMovement::whereKey($manual->cash_movement_id)->value('status'))->toBe('registrado');
 $this->postJson(route('admin.fondo-administrativo.annul',$manual),['cancellation_reason'=>'Compra cancelada'])->assertOk();
 expect($manual->fresh()->status)->toBe('anulado')->and(CashMovement::find($manual->cash_movement_id)->status)->toBe('anulado');
});
