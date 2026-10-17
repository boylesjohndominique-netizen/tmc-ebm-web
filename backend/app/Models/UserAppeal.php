<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message a banned user submits from the mobile app asking the
 * administrator to review (and possibly lift) their ban. Appeals are
 * accepted without authentication because banning revokes every token;
 * the sender is identified by username/email instead.
 */
class UserAppeal extends Model
{
    protected $fillable = ['user_id', 'message', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
