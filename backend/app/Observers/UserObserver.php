<?php

namespace App\Observers;

use App\Events\UsersChanged;
use App\Models\User;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Broadcasts user-account changes — registrations to verify, approvals,
 * bans, edits and deletions. User data feeds the Users page, dashboards
 * (user counts) and the notification recipient lists.
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->broadcast();
    }

    public function updated(User $user): void
    {
        $this->broadcast();
    }

    public function deleted(User $user): void
    {
        $this->broadcast();
    }

    /** Broadcast only once the surrounding transaction (if any) commits. */
    private function broadcast(): void
    {
        DB::afterCommit(fn () => SafeBroadcast::send(new UsersChanged()));
    }
}
