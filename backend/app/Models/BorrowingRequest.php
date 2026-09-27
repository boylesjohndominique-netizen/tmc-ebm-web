<?php

namespace App\Models;

use App\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Return requests submitted for this borrowing (first is the active one). */
    public function returns(): HasMany { return $this->hasMany(ReturnRequest::class, 'borrowing_request_id'); }

    public function latestReturn(): HasOne { return $this->hasOne(ReturnRequest::class, 'borrowing_request_id')->latestOfMany(); }
}
