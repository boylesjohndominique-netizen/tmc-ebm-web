<?php

namespace App\Observers;

use App\Events\UsersChanged;
use App\Models\UserPermission;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Permission overrides change what a user can see and do across the whole
 * system, so they surface as a "users changed" signal — the Permissions
 * page refreshes its list, and anyone editing that account sees fresh data.
 */
class UserPermissionObserver
{
    public function created(UserPermission $permission): void
    {
        $this->broadcast();
    }

    public function updated(UserPermission $permission): void
    {
        $this->broadcast();
    }

    public function deleted(UserPermission $permission): void
    {
        $this->broadcast();
    }

    /** Broadcast only once the surrounding transaction (if any) commits. */
    private function broadcast(): void
    {
        DB::afterCommit(fn () => SafeBroadcast::send(new UsersChanged()));
    }
}
