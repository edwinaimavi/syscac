<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberBeneficiary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'member_id', 'full_name', 'dni', 'relationship', 'phone', 'address',
        'percentage', 'birth_date', 'is_minor', 'observation', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'birth_date' => 'date',
        'is_minor' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
