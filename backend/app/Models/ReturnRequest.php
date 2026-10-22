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
        'condition_notes', 'overdue_fee_paid', 'admin_verified_at', 'admin_verified_by', 'admin_remarks',
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
        'overdue_fee_paid' => 'boolean',
        'admin_verified_at' => 'datetime',
    ];

    protected $hidden = ['qr_token', 'receipt_snapshot'];

    protected $appends = ['receipt', 'payment_status'];

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
            'condition_notes' => $this->condition_notes,
            'overdue_fee_paid' => $this->overdue_fee_paid,
            'payment_status' => $this->payment_status,
            'admin_verified_at' => $this->admin_verified_at?->toISOString(),
            'admin_remarks' => $this->admin_remarks,
        ]);
    }

    public function getPaymentStatusAttribute(): string
    {
        if (! $this->handover_processed_at && ! $this->returned_at) {
            return 'Not assessed';
        }
        if ((float) $this->overdue_fee <= 0) {
            return 'Not required';
        }

        return $this->overdue_fee_paid === null ? 'Not recorded' : ($this->overdue_fee_paid ? 'Paid' : 'Not paid');
    }

    public function adminVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_verified_by');
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
