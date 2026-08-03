<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guarantor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'member_id',
        'dni',
        'first_name',
        'last_name',
        'full_name',
        'phone',
        'address',
        'photo_path',
        'occupation',
        'relationship',
        'observation',
        'status',
        'created_by',
        'updated_by',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function guaranteedMembers()
    {
        return $this->belongsToMany(Member::class, 'member_guarantors')
            ->withPivot(['relationship_type', 'is_main', 'observation', 'status'])
            ->withTimestamps();
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'GAR-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^GAR-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'GAR-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
