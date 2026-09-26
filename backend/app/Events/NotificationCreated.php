<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A notification was created for its recipient. Broadcast on the user's
 * private channel; the client updates the bell badge and inbox instantly
 * (replacing the old 15-second polling). Only scalars travel the wire.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $recipientId,
        public readonly int $notificationId,
        public readonly string $type,
    ) {
    }

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientId}")];
    }

    /** Short wire name (Echo listens on '.notification.created'). */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notificationId,
            'type' => $this->type,
        ];
    }
}
