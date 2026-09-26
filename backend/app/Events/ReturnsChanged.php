<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signal that return requests changed — new submissions, confirmations
 * (which restock equipment and complete borrowings), rejections.
 */
class ReturnsChanged implements ShouldBroadcastNow, StaffDataChanged
{
    use Dispatchable;

    public function broadcastResource(): string
    {
        return 'returns';
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
