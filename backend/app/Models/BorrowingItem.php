<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingItem extends Model
{
    protected $fillable = ['borrowing_request_id','equipment_id','quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function borrowing(): BelongsTo { return $this->belongsTo(BorrowingRequest::class, 'borrowing_request_id'); }
    public function equipment(): BelongsTo { return $this->belongsTo(Equipment::class); }
}
