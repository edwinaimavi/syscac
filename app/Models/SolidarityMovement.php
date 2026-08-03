<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolidarityMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'member_id',
        'date',
        'movement_date',
        'type',
        'concept',
        'amount',
        'payment_method',
        'payment_reference',
        'voucher_path',
        'receipt_id',
        'source_type',
        'source_id',
        'cash_movement_id',
        'observation',
        'status',
        'created_by',
        'updated_by',
        'annulled_by',
        'annulled_at',
    ];

    protected $casts = [
        'date' => 'date',
        'movement_date' => 'date',
        'amount' => 'decimal:2',
        'annulled_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function cashMovement()
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function source()
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
            ->whereNotNull('code')
            ->where('code', 'like', 'SOL-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^SOL-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'SOL-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
