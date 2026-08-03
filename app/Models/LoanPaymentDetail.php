<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanPaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_payment_id',
        'loan_installment_id',
        'principal_paid',
        'interest_paid',
        'amount_paid',
        'previous_balance',
        'new_balance',
        'observation',
        'late_fee_paid','late_fee_waived','late_fee_days',
    ];

    protected $casts = [
        'principal_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'late_fee_paid'=>'decimal:2','late_fee_waived'=>'decimal:2','late_fee_days'=>'integer',
    ];

    public function payment()
    {
        return $this->belongsTo(LoanPayment::class, 'loan_payment_id');
    }

    public function installment()
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }
}
