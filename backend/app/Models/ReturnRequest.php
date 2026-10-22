<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $fillable = [
        'borrowing_request_id', 'user_id', 'returned_at',
        'status', 'condition_on_return', 'remarks', 'processed_by',
        'overdue_days', 'overdue_fee', 'transaction_code', 'qr_token', 'receipt_snapshot',
        'approved_at', 'handover_starts_at', 'handover_ends_at', 'handover_status',
        'handover_processed_at', 'handover_processed_by', 'handover_remarks',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'overdue_days' => 'integer',
        'overdue_fee' => 'float',
        'receipt_snapshot' => 'array',
        'approved_at' => 'datetime',
        'handover_starts_at' => 'datetime',
        'handover_ends_at' => 'datetime',
        'handover_processed_at' => 'datetime',
    ];

    protected $hidden = ['qr_token', 'receipt_snapshot'];

    protected $appends = ['receipt'];

    protected static function booted(): void
    {
        static::creating(function (ReturnRequest $return) {
            do {
                $code = (string) random_int(100000000000, 999999999999);
            } while (static::where('transaction_code', $code)->exists() ||
                BorrowingRequest::where('transaction_code', $code)->exists());

            $return->transaction_code = $code;
            $return->qr_token = bin2hex(random_bytes(32));
        });
    }

    public function getReceiptAttribute(): ?array
    {
        if (! $this->receipt_snapshot) {
            return null;
        }

        return array_merge($this->receipt_snapshot, [
            'kind' => 'return',
            'transaction_code' => $this->transaction_code,
            'qr_payload' => 'TMCEBMS:RETURN:'.$this->qr_token,
            'status' => $this->status,
            'handover_status' => $this->handover_status,
            'approved_at' => $this->approved_at?->toISOString(),
            'handover_starts_at' => $this->handover_starts_at?->toISOString(),
            'handover_ends_at' => $this->handover_ends_at?->toISOString(),
            'handover_processed_at' => $this->handover_processed_at?->toISOString(),
            'handover_remarks' => $this->handover_remarks,
            'condition_on_return' => $this->condition_on_return,
            'returned_at' => $this->returned_at?->toISOString(),
            'overdue_days' => $this->overdue_days,
            'overdue_fee' => $this->overdue_fee,
        ]);
    }

    public function handoverProcessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_processed_by');
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(BorrowingRequest::class, 'borrowing_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
