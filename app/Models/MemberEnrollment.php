<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'member_id', 'enrollment_date', 'amount', 'payment_method', 'payment_reference', 'voucher_path', 'receipt_id', 'observation', 'status', 'created_by', 'updated_by', 'annulled_by', 'annulled_at'];

    protected $casts = ['enrollment_date' => 'date', 'amount' => 'decimal:2', 'annulled_at' => 'datetime'];

    public function member() { return $this->belongsTo(Member::class); }
    public function receipt() { return $this->belongsTo(Receipt::class); }
    public function cashMovement() { return $this->morphOne(CashMovement::class, 'related'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public static function nextCode(): string
    {
        $last = self::withTrashed()->where('code', 'like', 'INS-%')->orderByDesc('id')->lockForUpdate()->value('code');
        $number = $last && preg_match('/INS-(\d+)/', $last, $matches) ? (int) $matches[1] : 0;
        return 'INS-' . str_pad((string) ($number + 1), 6, '0', STR_PAD_LEFT);
    }
}
