<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberAccountClosureDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_account_closure_id',
        'item_type',
        'description',
        'amount',
        'sign',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function closure()
    {
        return $this->belongsTo(MemberAccountClosure::class, 'member_account_closure_id');
    }

    public function related()
    {
        return $this->morphTo();
    }
}
