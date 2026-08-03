<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanRefinancing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'original_loan_id',
        'new_loan_id',
        'member_id',
        'refinancing_date',
        'previous_balance',
        'new_amount',
        'additional_amount',
        'interest_rate',
        'term_months',
        'start_date',
        'first_payment_date',
        'amortization_method',
        'fixed_principal',
        'total_interest',
        'total_amount',
        'receipt_id',
        'reason',
        'observation',
        'closed_installments_snapshot',
        'status',
        'created_by',
        'updated_by',
        'annulled_by',
        'annulled_at',
    ];

    protected $casts = [
        'refinancing_date' => 'date',
        'previous_balance' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'additional_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'start_date' => 'date',
        'first_payment_date' => 'date',
        'fixed_principal' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'closed_installments_snapshot' => 'array',
        'annulled_at' => 'datetime',
    ];

    public function originalLoan()
    {
        return $this->belongsTo(Loan::class, 'original_loan_id');
    }

    public function newLoan()
    {
        return $this->belongsTo(Loan::class, 'new_loan_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function annuller()
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'REF-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^REF-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'REF-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
