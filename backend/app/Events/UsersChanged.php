<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signal that user accounts changed — registrations, verification, bans,
 * edits, deletions. Also fires when a user's permission overrides change,
 * because role/permission data drives menus and guards everywhere.
 */
class UsersChanged implements ShouldBroadcastNow, StaffDataChanged
{
    use Dispatchable;

    public function broadcastResource(): string
    {
        return 'users';
    }

    /** Short wire name (Echo listens on '.changed'). */
    public function broadcastAs(): string
    {
        return 'changed';
    }

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff')];
    }

    public function broadcastWith(): array
    {
        return ['resource' => $this->broadcastResource()];
    }
}
