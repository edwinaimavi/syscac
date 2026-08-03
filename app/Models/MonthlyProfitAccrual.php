<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class MonthlyProfitAccrual extends Model{
 use SoftDeletes;protected $guarded=[];protected $casts=['month'=>'date','approved_at'=>'datetime','interest_collected'=>'decimal:2','late_fees_collected'=>'decimal:2','total_profit'=>'decimal:2','total_shares'=>'decimal:4','profit_per_share'=>'decimal:10'];
 public function details(){return $this->hasMany(MonthlyProfitAccrualDetail::class);}
 public static function nextCode():string{$n=(int)self::withTrashed()->max('id')+1;return 'UMS-'.str_pad((string)$n,6,'0',STR_PAD_LEFT);}
}
