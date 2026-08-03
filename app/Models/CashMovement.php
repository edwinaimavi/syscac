<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'movement_number',
        'movement_date',
        'type',
        'category',
        'concept',
        'amount',
        'payment_method',
        'reference',
        'voucher_path',
        'related_type',
        'related_id',
        'balance_before',
        'balance_after',
        'observation',
        'status',
        'created_by',
        'updated_by',
        'annulled_at',
        'annulled_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'annulled_at' => 'datetime',
    ];

    public function related()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function annuller()
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('movement_number')
            ->where('movement_number', 'like', 'CAJ-%')
            ->lockForUpdate()
            ->pluck('movement_number')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^CAJ-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'CAJ-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
