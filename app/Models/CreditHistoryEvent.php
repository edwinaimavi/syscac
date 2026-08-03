<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditHistoryEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'registered_at' => 'datetime',
        'amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
    ];

    public function member() { return $this->belongsTo(Member::class); }
    public function loan() { return $this->belongsTo(Loan::class); }
    public function installment() { return $this->belongsTo(LoanInstallment::class, 'loan_installment_id'); }
    public function payment() { return $this->belongsTo(LoanPayment::class, 'loan_payment_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
