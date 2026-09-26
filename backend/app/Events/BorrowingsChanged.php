<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signal that borrowing requests changed — new submissions from students,
 * approvals/rejections by staff, stock reservations. Listeners refetch
 * /api/admin/borrowings (and dashboards/analytics which include borrowing
 * stats).
 */
class BorrowingsChanged implements ShouldBroadcastNow, StaffDataChanged
{
    use Dispatchable;

    public function broadcastResource(): string
    {
        return 'borrowings';
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
