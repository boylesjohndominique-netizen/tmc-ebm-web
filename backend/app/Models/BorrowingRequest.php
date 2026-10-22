<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BorrowingRequest extends Model
{
    protected $fillable = [
        'user_id', 'borrowing_date', 'expected_return_date',
        'status', 'remarks', 'processed_by', 'transaction_code', 'qr_token',
        'receipt_snapshot', 'approved_at', 'pickup_starts_at', 'pickup_ends_at',
        'pickup_status', 'pickup_processed_at', 'pickup_processed_by', 'pickup_remarks',
    ];

    protected $casts = [
        'borrowing_date' => 'date',
        'expected_return_date' => 'date',
        'receipt_snapshot' => 'array',
        'approved_at' => 'datetime',
        'pickup_starts_at' => 'datetime',
        'pickup_ends_at' => 'datetime',
        'pickup_processed_at' => 'datetime',
    ];

    protected $hidden = ['qr_token', 'receipt_snapshot'];

    protected $appends = ['receipt'];

    protected static function booted(): void
    {
        static::creating(function (BorrowingRequest $borrowing) {
            do {
                $code = (string) random_int(100000000000, 999999999999);
            } while (static::where('transaction_code', $code)->exists());

            $borrowing->transaction_code = $code;
            $borrowing->qr_token = bin2hex(random_bytes(32));
        });
    }

    public function getReceiptAttribute(): ?array
    {
        if (! $this->receipt_snapshot) {
            return null;
        }

        return array_merge($this->receipt_snapshot, [
            'transaction_code' => $this->transaction_code,
            'qr_payload' => 'TMCEBMS:'.$this->qr_token,
            'status' => $this->status,
            'pickup_status' => $this->pickup_status,
            'approved_at' => $this->approved_at?->toISOString(),
            'pickup_starts_at' => $this->pickup_starts_at?->toISOString(),
            'pickup_ends_at' => $this->pickup_ends_at?->toISOString(),
            'pickup_processed_at' => $this->pickup_processed_at?->toISOString(),
            'pickup_remarks' => $this->pickup_remarks,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** Return requests submitted for this borrowing (first is the active one). */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class, 'borrowing_request_id');
    }

    public function latestReturn(): HasOne
    {
        return $this->hasOne(ReturnRequest::class, 'borrowing_request_id')->latestOfMany();
    }
}
