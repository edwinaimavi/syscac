<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MonthlyProfitAccrualDetail extends Model{
 protected $guarded=[];protected $casts=['shares_quantity'=>'decimal:4','profit_amount'=>'decimal:2','paid_amount'=>'decimal:2'];
 public function accrual(){return $this->belongsTo(MonthlyProfitAccrual::class,'monthly_profit_accrual_id');}
 public function member(){return $this->belongsTo(Member::class);}
}
