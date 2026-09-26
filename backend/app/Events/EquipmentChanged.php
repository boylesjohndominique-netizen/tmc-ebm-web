<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signal that equipment data changed (created / updated / deleted, plus
 * stock reserved or restocked by borrowing/return processing).
 * Listeners refetch /api/equipment to stay in sync.
 */
class EquipmentChanged implements ShouldBroadcastNow, StaffDataChanged
{
    use Dispatchable;

    public function broadcastResource(): string
    {
        return 'equipment';
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
