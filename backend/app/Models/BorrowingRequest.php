<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowingRequest extends Model
{
    protected $fillable = [
        'user_id','borrowing_date','expected_return_date',
        'status','remarks','processed_by'
    ];

    protected $casts = [
        'borrowing_date' => 'date',
        'expected_return_date' => 'date'
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(BorrowingItem::class); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
