<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfitDistribution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'period_year',
        'period_month',
        'start_date',
        'end_date',
        'total_profit',
        'total_shares',
        'total_action_month',
        'profit_per_share',
        'profit_per_action_month',
        'source_type',
        'calculated_at',
        'calculated_by',
        'status',
        'approved_at',
        'approved_by',
        'paid_at',
        'paid_by',
        'observation',
        'created_by',
        'updated_by',
        'annulled_by',
        'annulled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_profit' => 'decimal:2',
        'profit_per_share' => 'decimal:4',
        'total_action_month' => 'decimal:4',
        'profit_per_action_month' => 'decimal:8',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'annulled_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(ProfitDistributionDetail::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function calculator()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function annuller()
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'UTI-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^UTI-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'UTI-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
