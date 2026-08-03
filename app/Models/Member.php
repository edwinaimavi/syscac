<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reentry_from_member_id',
        'code',
        'first_name',
        'last_name',
        'full_name',
        'dni',
        'birth_date',
        'admission_date',
        'member_type',
        'member_type_selected',
        'retirement_date',
        'current_job',
        'address',
        'civil_status',
        'spouse_name',
        'phone',
        'reference_name',
        'reference_dni',
        'reference_phone',
        'photo_path',
        'status',
        'observation',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'admission_date' => 'date',
        'retirement_date' => 'date',
    ];

    public function relatives()
    {
        return $this->hasMany(MemberRelative::class);
    }

    public function beneficiaries()
    {
        return $this->hasMany(MemberBeneficiary::class);
    }

    public function shares()
    {
        return $this->hasMany(MemberShare::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function loanSimulations()
    {
        return $this->hasMany(LoanSimulation::class);
    }

    public function loanPayments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function solidarityMovements()
    {
        return $this->hasMany(SolidarityMovement::class);
    }

    public function activityMovements()
    {
        return $this->hasMany(ActivityMovement::class);
    }

    public function profitDistributionDetails()
    {
        return $this->hasMany(ProfitDistributionDetail::class);
    }

    public function creditHistory()
    {
        return $this->hasOne(CreditHistory::class);
    }

    public function creditHistoryEvents()
    {
        return $this->hasMany(CreditHistoryEvent::class);
    }

    public function accountClosures()
    {
        return $this->hasMany(MemberAccountClosure::class);
    }

    public function previousMember()
    {
        return $this->belongsTo(self::class, 'reentry_from_member_id');
    }

    public function subsequentReentries()
    {
        return $this->hasMany(self::class, 'reentry_from_member_id');
    }

    public function scopeAvailableForLoanOperations(Builder $query): Builder
    {
        return $query
            ->where('status', 'vigente')
            ->whereNull('retirement_date')
            ->whereDoesntHave('accountClosures', fn (Builder $closure) => $closure
                ->whereIn('status', ['calculado', 'pendiente_regularizacion', 'en_proceso', 'cerrado']));
    }

    public function scopeEligibleGuarantors(Builder $query): Builder
    {
        return $query
            ->where('status', 'vigente')
            ->whereNull('retirement_date')
            ->where(function (Builder $age) {
                $age->whereNull('birth_date')->orWhereDate('birth_date', '<=', today()->subYears(18));
            })
            ->whereDoesntHave('accountClosures', fn (Builder $closure) => $closure
                ->whereIn('status', ['calculado', 'pendiente_regularizacion', 'en_proceso', 'cerrado']));
    }

    public function hasPendingWithdrawalProcess(): bool
    {
        return $this->accountClosures()
            ->whereIn('status', ['calculado', 'pendiente_regularizacion', 'en_proceso'])
            ->exists();
    }

    public function hasConfirmedWithdrawal(): bool
    {
        return $this->status !== 'vigente'
            || $this->retirement_date !== null
            || $this->accountClosures()->where('status', 'cerrado')->exists();
    }

    public function canRequestLoan(): bool
    {
        return ! $this->hasConfirmedWithdrawal() && ! $this->hasPendingWithdrawalProcess();
    }

    public function canBeGuarantor(): bool
    {
        return $this->canRequestLoan() && ! $this->isMinor();
    }

    public function isMinor(): bool
    {
        return $this->birth_date !== null && $this->birth_date->gt(today()->subYears(18));
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function enrollments()
    {
        return $this->hasMany(MemberEnrollment::class);
    }

    public function guaranteedMembers()
    {
        return $this->hasMany(MemberGuarantor::class, 'guarantor_member_id');
    }

    public function guarantorLinks()
    {
        return $this->hasMany(MemberGuarantor::class);
    }

    public function guarantors()
    {
        return $this->belongsToMany(Guarantor::class, 'member_guarantors')
            ->withPivot(['relationship_type', 'is_main', 'observation', 'status'])
            ->withTimestamps();
    }

    public function guarantorRecord()
    {
        return $this->hasOne(Guarantor::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function calculatedMemberType(): string
    {
        return self::calculateMemberTypeByAdmissionDate($this->admission_date);
    }

    public static function calculateMemberTypeByAdmissionDate(mixed $admissionDate): string
    {
        if (! $admissionDate) return 'nuevo';
        return \Carbon\Carbon::parse($admissionDate)->lte(now()->subYear()->startOfDay()) ? 'antiguo' : 'nuevo';
    }

    public function syncCalculatedMemberType(): string
    {
        $type = $this->calculatedMemberType();
        if ($this->member_type !== $type) {
            $this->forceFill(['member_type' => $type])->saveQuietly();
        }
        return $type;
    }

    public function isNewMember(): bool { return $this->calculatedMemberType() === 'nuevo'; }
    public function isOldMember(): bool { return $this->calculatedMemberType() === 'antiguo'; }
}
