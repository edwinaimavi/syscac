<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdministrativeFundMovement extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['movement_date'=>'date','amount'=>'decimal:2','cancelled_at'=>'datetime'];
    public function member() { return $this->belongsTo(Member::class); }
    public function cashMovement() { return $this->belongsTo(CashMovement::class); }
    public function source() { return $this->morphTo(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function canceller() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public static function nextCode(): string
    {
        $max = self::withTrashed()->where('code','like','FAD-%')->pluck('code')->reduce(
            fn ($max,$code) => preg_match('/^FAD-(\d+)$/',$code,$m) ? max($max,(int)$m[1]) : $max, 0
        );
        return 'FAD-'.str_pad((string)($max+1),6,'0',STR_PAD_LEFT);
    }
}
