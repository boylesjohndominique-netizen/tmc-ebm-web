<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (Laravel Reverb)
|--------------------------------------------------------------------------
|
| The React admin dashboard subscribes to two private channels:
|
|   staff        — every authenticated staff-side account (non-Student).
|                  Carries data-change signals: equipment, borrowings,
|                  returns, users, permissions.
|
|   user.{id}    — the user's own notification feed (notification bell).
|
| Students are mobile-app only and cannot subscribe to either channel.
|
*/

Broadcast::channel('staff', function ($user) {
    return $user instanceof User && ! $user->isStudent();
});

Broadcast::channel('user.{id}', function ($user, int $id) {
    return (int) $user->id === $id;
});
