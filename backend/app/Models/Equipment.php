<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    protected $fillable = [
        'category_id','name','description','quantity',
        'available_quantity','condition','status','image'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'available_quantity' => 'integer'
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
