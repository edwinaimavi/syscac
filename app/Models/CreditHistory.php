<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_paid' => 'decimal:2',
        'average_days_late' => 'decimal:2',
        'active_overdue_amount' => 'decimal:2',
        'last_payment_date' => 'date',
        'last_loan_date' => 'date',
        'calculated_at' => 'datetime',
    ];

    public function member() { return $this->belongsTo(Member::class); }
    public function events() { return $this->hasMany(CreditHistoryEvent::class, 'member_id', 'member_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
