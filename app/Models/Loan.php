<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_simulation_id',
        'refinancing_id',
        'member_id',
        'guarantor_member_id',
        'requires_guarantor',
        'member_type_at_evaluation',
        'member_contribution_count_at_evaluation',
        'member_total_contributions_at_evaluation',
        'loan_limit_without_guarantor',
        'guarantor_total_contributions_at_evaluation',
        'guarantor_requirement_reason',
        'loan_number',
        'requested_amount',
        'approved_amount',
        'interest_rate',
        'interest_type',
        'term_months',
        'start_date',
        'first_payment_date',
        'payment_frequency',
        'amortization_method',
        'fixed_principal',
        'total_interest',
        'total_amount',
        'current_balance',
        'disbursed_amount',
        'disbursement_payment_method',
        'disbursement_reference',
        'disbursement_voucher_path',
        'disbursement_receipt_id',
        'approved_at',
        'disbursed_at',
        'status',
        'purpose',
        'observation',
        'created_by',
        'updated_by',
        'approved_by',
        'disbursed_by',
        'annulled_by',
        'annulled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'first_payment_date' => 'date',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'fixed_principal' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'annulled_at' => 'datetime',
        'requires_guarantor' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function guarantorMember()
    {
        return $this->belongsTo(Member::class, 'guarantor_member_id');
    }

    public function simulation()
    {
        return $this->belongsTo(LoanSimulation::class, 'loan_simulation_id');
    }

    public function refinancing()
    {
        return $this->belongsTo(LoanRefinancing::class, 'refinancing_id');
    }

    public function installments()
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function refinancings()
    {
        return $this->hasMany(LoanRefinancing::class, 'original_loan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disburser()
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function disbursementReceipt()
    {
        return $this->belongsTo(Receipt::class, 'disbursement_receipt_id');
    }

    public function annuller()
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('loan_number')
            ->where('loan_number', 'like', 'PRE-%')
            ->lockForUpdate()
            ->pluck('loan_number')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^PRE-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'PRE-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
