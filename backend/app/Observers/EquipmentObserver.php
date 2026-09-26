<?php

namespace App\Observers;

use App\Events\BorrowingsChanged;
use App\Events\EquipmentChanged;
use App\Models\Equipment;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Broadcasts equipment changes (created / edited / deleted by admins).
 *
 * Stock movements made through query-builder increments (borrow approval /
 * return confirmation) don't fire model events, but those flows also touch
 * BorrowingRequest / ReturnRequest, whose signals make clients refetch
 * equipment data as well.
 */
class EquipmentObserver
{
    public function created(Equipment $equipment): void
    {
        $this->broadcast();
    }

    public function updated(Equipment $equipment): void
    {
        $this->broadcast();
    }

    public function deleted(Equipment $equipment): void
    {
        $this->broadcast();
    }

    /** Broadcast only once the surrounding transaction (if any) commits. */
    private function broadcast(): void
    {
        DB::afterCommit(fn () => SafeBroadcast::send(new EquipmentChanged()));
    }
}
