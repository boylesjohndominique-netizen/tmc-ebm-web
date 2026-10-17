<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $fillable = [
        'borrowing_request_id','user_id','returned_at',
        'status','condition_on_return','remarks','processed_by',
        'overdue_days','overdue_fee'
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'overdue_days' => 'integer',
        'overdue_fee' => 'float',
    ];

    public function borrowing(): BelongsTo { return $this->belongsTo(BorrowingRequest::class, 'borrowing_request_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
