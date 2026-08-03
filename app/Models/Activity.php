<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'activity_date',
        'description',
        'total_income',
        'total_expense',
        'profit',
        'status',
        'closed_at',
        'closed_by',
        'created_by',
        'updated_by',
        'annulled_by',
        'annulled_at',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'total_income' => 'decimal:2',
        'total_expense' => 'decimal:2',
        'profit' => 'decimal:2',
        'closed_at' => 'datetime',
        'annulled_at' => 'datetime',
    ];

    public function movements()
    {
        return $this->hasMany(ActivityMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function annuller()
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public static function nextCode(): string
    {
        $lastNumber = self::withTrashed()
            ->whereNotNull('code')
            ->where('code', 'like', 'ACT-%')
            ->lockForUpdate()
            ->pluck('code')
            ->reduce(function (int $max, string $code) {
                return preg_match('/^ACT-(\d+)$/', $code, $matches)
                    ? max($max, (int) $matches[1])
                    : $max;
            }, 0);

        return 'ACT-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
