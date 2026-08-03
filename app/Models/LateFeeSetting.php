<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class LateFeeSetting extends Model
{
    use SoftDeletes;
    protected $fillable=['name','grace_days','calculation_type','value','max_amount','is_active','allow_waiver','auto_apply','observation','created_by','updated_by'];
    protected $casts=['grace_days'=>'integer','value'=>'decimal:4','max_amount'=>'decimal:2','is_active'=>'boolean','allow_waiver'=>'boolean','auto_apply'=>'boolean'];
    public static function active(): ?self { return static::where('is_active',true)->where('auto_apply',true)->latest('id')->first(); }
    public function creator(){ return $this->belongsTo(User::class,'created_by'); }
    public function updater(){ return $this->belongsTo(User::class,'updated_by'); }
}
