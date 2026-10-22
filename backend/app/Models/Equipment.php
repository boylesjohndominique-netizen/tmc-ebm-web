<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Equipment extends Model
{
    protected $fillable = [
        'category_id', 'name', 'description', 'quantity',
        'available_quantity', 'condition', 'status', 'image', 'images',
        'pickup_location', 'min_borrowing_days', 'max_borrowing_days',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'available_quantity' => 'integer',
        'images' => 'array',
        'min_borrowing_days' => 'integer',
        'max_borrowing_days' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function allowsBorrowingPeriod(string $borrowingDate, string $returnDate): bool
    {
        $days = (int) Carbon::parse($borrowingDate)->diffInDays(Carbon::parse($returnDate), false);

        return $days >= 1
            && ($this->min_borrowing_days === null || $days >= $this->min_borrowing_days)
            && ($this->max_borrowing_days === null || $days <= $this->max_borrowing_days);
    }
}
