<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An account was banned. Pushed to that user's private channel so a mobile
 * client that is still connected (the ban revokes its API tokens, but the
 * WebSocket stays open) can immediately show the ban notice, offer an
 * appeal, and sign out instead of silently failing on the next request.
 *
 * A dedicated event (rather than relying on the generic notification ping)
 * lets the reason travel on the wire, so the client does not need an
 * authenticated request to learn why it was banned.
 */
class UserBanned implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $reason,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    /** Short wire name (the client listens on '.user.banned'). */
    public function broadcastAs(): string
    {
        return 'user.banned';
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
            'banned_at' => now()->toIso8601String(),
        ];
    }
}
