<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInstallment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'opening_balance',
        'principal_amount',
        'interest_amount',
        'installment_amount',
        'paid_amount',
        'capital_paid',
        'interest_paid',
        'interest_exonerated',
        'remaining_amount',
        'closing_balance',
        'status',
        'payment_type',
        'schedule_version',
        'recalculated_at',
        'paid_at',
        'late_days','late_fee_amount','late_fee_paid','late_fee_waived','late_fee_pending','late_fee_calculated_at','late_fee_status','late_fee_setting_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'capital_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'interest_exonerated' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'recalculated_at' => 'datetime',
        'late_days'=>'integer','late_fee_amount'=>'decimal:2','late_fee_paid'=>'decimal:2','late_fee_waived'=>'decimal:2','late_fee_pending'=>'decimal:2','late_fee_calculated_at'=>'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function paymentDetails()
    {
        return $this->hasMany(LoanPaymentDetail::class);
    }
}
