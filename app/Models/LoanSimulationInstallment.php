<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanSimulationInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_simulation_id',
        'installment_number',
        'due_date',
        'opening_balance',
        'principal_amount',
        'interest_amount',
        'installment_amount',
        'closing_balance',
    ];

    protected $casts = [
        'due_date' => 'date',
        'opening_balance' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    public function simulation()
    {
        return $this->belongsTo(LoanSimulation::class, 'loan_simulation_id');
    }
}
