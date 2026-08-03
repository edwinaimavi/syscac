<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberShare extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'member_id',
        'date',
        'amount',
        'total_paid',
        'share_capital_amount',
        'solidarity_amount',
        'administrative_fee_amount',
        'share_value',
        'shares_quantity',
        'receipt_number',
        'payment_method',
        'payment_reference',
        'voucher_path',
        'observation',
        'status',
        'created_by',
        'updated_by',
        'annulled_at',
        'annulled_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'share_capital_amount' => 'decimal:2',
        'solidarity_amount' => 'decimal:2',
        'administrative_fee_amount' => 'decimal:2',
        'share_value' => 'decimal:2',
        'shares_quantity' => 'decimal:4',
        'annulled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MemberShare $share) {
            if ($share->total_paid === null && $share->amount !== null) $share->total_paid = $share->amount;
            if ($share->share_capital_amount === null && $share->amount !== null) $share->share_capital_amount = $share->amount;
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
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

    public function receipt()
    {
        return $this->morphOne(Receipt::class, 'related');
    }

    public static function nextCode(): string
    {
        $lastCode = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'APO-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('code');

        $lastNumber = 0;

        if ($lastCode && preg_match('/APO-(\d+)/', $lastCode, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'APO-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
