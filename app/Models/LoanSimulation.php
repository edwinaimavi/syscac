<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanSimulation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'member_id',
        'simulation_date',
        'amount',
        'interest_rate',
        'interest_type',
        'term_months',
        'start_date',
        'first_payment_date',
        'amortization_method',
        'fixed_principal',
        'total_interest',
        'total_payment',
        'observation',
        'status',
        'effect_reason',
        'effected_by',
        'effected_at',
        'guarantor_member_id',
        'requires_guarantor',
        'member_type_at_evaluation',
        'member_contribution_count_at_evaluation',
        'member_total_contributions_at_evaluation',
        'loan_limit_without_guarantor',
        'guarantor_total_contributions_at_evaluation',
        'guarantor_requirement_reason',
        'converted_loan_id',
        'converted_at',
        'converted_by',
        'created_by',
        'updated_by',
        'annulled_by',
        'annulled_at',
    ];

    protected $casts = [
        'simulation_date' => 'date',
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'start_date' => 'date',
        'first_payment_date' => 'date',
        'fixed_principal' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_payment' => 'decimal:2',
        'converted_at' => 'datetime',
        'annulled_at' => 'datetime',
        'effected_at' => 'datetime',
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

    public function installments()
    {
        return $this->hasMany(LoanSimulationInstallment::class);
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

    public function effecter()
    {
        return $this->belongsTo(User::class, 'effected_by');
    }

    public function convertedLoan()
    {
        return $this->belongsTo(Loan::class, 'converted_loan_id');
    }

    public function converter()
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public static function nextCode(): string
    {
        $lastCode = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'SIM-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('code');

        $lastNumber = 0;

        if ($lastCode && preg_match('/SIM-(\d+)/', $lastCode, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'SIM-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
