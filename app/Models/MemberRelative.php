<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberRelative extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'name',
        'relationship',
        'birth_date',
        'observation',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
