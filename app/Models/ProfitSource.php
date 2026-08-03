<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitSource extends Model
{
    protected $fillable = ['code', 'source_date', 'amount', 'adjustment_type', 'reason', 'observation', 'status', 'created_by', 'annulled_by', 'annulled_at'];

    protected $casts = ['source_date' => 'date', 'amount' => 'decimal:2', 'annulled_at' => 'datetime'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function annuller() { return $this->belongsTo(User::class, 'annulled_by'); }

    public static function nextCode(): string
    {
        $last = self::where('code', 'like', 'UFM-%')->orderByDesc('id')->lockForUpdate()->value('code');
        $number = $last && preg_match('/UFM-(\d+)/', $last, $matches) ? (int) $matches[1] : 0;
        return 'UFM-' . str_pad((string) ($number + 1), 6, '0', STR_PAD_LEFT);
    }
}
