<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberGuarantor extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'guarantor_member_id',
        'guarantor_id',
        'relationship_type',
        'is_main',
        'observation',
        'status',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function guarantor()
    {
        return $this->belongsTo(Guarantor::class);
    }

    public function guarantorMember()
    {
        return $this->belongsTo(Member::class, 'guarantor_member_id');
    }
}
