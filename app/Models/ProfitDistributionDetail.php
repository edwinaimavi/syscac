<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfitDistributionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'profit_distribution_id',
        'member_id',
        'actions_considered',
        'months_considered',
        'action_month',
        'calculation_breakdown',
        'shares_quantity',
        'participation_percentage',
        'profit_amount',
        'paid_amount',
        'payment_method',
        'payment_reference',
        'voucher_path',
        'receipt_id',
        'status',
        'paid_at',
        'paid_by',
        'observation',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'actions_considered' => 'decimal:4',
        'action_month' => 'decimal:4',
        'calculation_breakdown' => 'array',
        'participation_percentage' => 'decimal:4',
        'profit_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function distribution()
    {
        return $this->belongsTo(ProfitDistribution::class, 'profit_distribution_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function cashMovement()
    {
        return $this->morphOne(CashMovement::class, 'related');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
