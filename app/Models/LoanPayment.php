<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::updated(function (LoanPayment $payment) {
            $profitChanged = $payment->wasChanged(['affects_profit', 'profit_treatment']);
            $annulledEligiblePayment = $payment->wasChanged('status') && $payment->status === 'anulado'
                && (! $payment->is_historical || ($payment->affects_profit && $payment->profit_treatment === 'eligible'));
            if (($profitChanged || $annulledEligiblePayment) && $payment->payment_date) {
                MonthlyProfitAccrual::whereDate('month', $payment->payment_date->copy()->startOfMonth())
                    ->whereIn('status', ['calculada', 'aprobada'])
                    ->update(['status' => 'desactualizada']);
            }
        });
    }

    protected $fillable = [
        'loan_id',
        'member_id',
        'payment_number',
        'payment_date',
        'is_historical',
        'affects_cash',
        'affects_profit',
        'profit_treatment',
        'affects_credit_history',
        'amount',
        'previous_loan_balance',
        'new_loan_balance',
        'capital_amount',
        'interest_amount',
        'late_interest_amount',
        'interest_exonerated_amount',
        'installments_advanced_count',
        'schedule_before',
        'schedule_after',
        'payment_type',
        'payment_method',
        'payment_reference',
        'voucher_path',
        'receipt_number',
        'receipt_id',
        'observation',
        'status',
        'created_by',
        'updated_by',
        'annulled_by',
        'annulled_at',
        'late_fee_amount','late_fee_paid','late_fee_waived','late_fee_reason','late_fee_days','late_fee_setting_id','late_fee_waived_by','late_fee_waived_at',
        'late_fee_calculated','late_fee_charged','late_fee_exonerated','late_fee_override_reason',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'is_historical' => 'boolean',
        'affects_cash' => 'boolean',
        'affects_profit' => 'boolean',
        'affects_credit_history' => 'boolean',
        'amount' => 'decimal:2',
        'previous_loan_balance' => 'decimal:2',
        'new_loan_balance' => 'decimal:2',
        'capital_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'late_interest_amount' => 'decimal:2',
        'interest_exonerated_amount' => 'decimal:2',
        'installments_advanced_count' => 'integer',
        'schedule_before' => 'array',
        'schedule_after' => 'array',
        'annulled_at' => 'datetime',
        'late_fee_amount'=>'decimal:2','late_fee_paid'=>'decimal:2','late_fee_waived'=>'decimal:2','late_fee_days'=>'integer','late_fee_waived_at'=>'datetime',
        'late_fee_calculated'=>'decimal:2','late_fee_charged'=>'decimal:2','late_fee_exonerated'=>'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function details()
    {
        return $this->hasMany(LoanPaymentDetail::class);
    }

    public function cashMovements()
    {
        return $this->morphMany(CashMovement::class, 'related');
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
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
        $lastCode = self::withTrashed()
            ->whereNotNull('payment_number')
            ->where('payment_number', 'like', 'COB-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('payment_number');

        $lastNumber = 0;

        if ($lastCode && preg_match('/COB-(\d+)/', $lastCode, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'COB-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
