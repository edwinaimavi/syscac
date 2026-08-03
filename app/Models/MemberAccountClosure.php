<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberAccountClosure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'member_id',
        'closure_date',
        'retirement_date',
        'total_contributions',
        'total_shares',
        'pending_loans_amount',
        'loan_capital_compensated',
        'overdue_interest_compensated',
        'future_interest_exonerated',
        'loan_schedule_before',
        'pending_utilities_amount',
        'utility_mode', 'utility_status', 'utility_period_year', 'utility_actions_considered', 'utility_productive_months',
        'utility_action_month', 'utility_total_action_month', 'utility_available_snapshot', 'utility_estimated_amount',
        'utility_paid_now', 'utility_calculation_breakdown',
        'total_in_favor',
        'total_against',
        'final_balance',
        'settlement_type',
        'payment_method',
        'payment_reference',
        'voucher_path',
        'receipt_id',
        'reason',
        'observation',
        'status',
        'created_by',
        'updated_by',
        'closed_by',
        'closed_at',
        'annulled_by',
        'annulled_at',
        'annulment_reason',
    ];

    protected $casts = [
        'closure_date' => 'date',
        'retirement_date' => 'date',
        'total_contributions' => 'decimal:2',
        'total_shares' => 'decimal:4',
        'pending_loans_amount' => 'decimal:2',
        'loan_capital_compensated' => 'decimal:2',
        'overdue_interest_compensated' => 'decimal:2',
        'future_interest_exonerated' => 'decimal:2',
        'loan_schedule_before' => 'array',
        'pending_utilities_amount' => 'decimal:2',
        'utility_actions_considered' => 'decimal:4', 'utility_action_month' => 'decimal:4', 'utility_total_action_month' => 'decimal:4',
        'utility_available_snapshot' => 'decimal:2', 'utility_estimated_amount' => 'decimal:2', 'utility_paid_now' => 'decimal:2',
        'utility_calculation_breakdown' => 'array',
        'total_in_favor' => 'decimal:2',
        'total_against' => 'decimal:2',
        'final_balance' => 'decimal:2',
        'closed_at' => 'datetime',
        'annulled_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function details()
    {
        return $this->hasMany(MemberAccountClosureDetail::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function cashMovement()
    {
        return $this->morphOne(CashMovement::class, 'related');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function annuller()
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'CIE-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^CIE-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'CIE-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
