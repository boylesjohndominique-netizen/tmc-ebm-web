<?php

namespace App\Observers;

use App\Events\EquipmentChanged;
use App\Events\ReturnsChanged;
use App\Models\ReturnRequest;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Broadcasts return-request lifecycle changes. Confirmations also restock
 * equipment (a bulk query update, which does not fire Equipment events),
 * so an EquipmentChanged signal is emitted alongside the return signal.
 */
class ReturnRequestObserver
{
    public function created(ReturnRequest $returnRequest): void
    {
        $this->broadcastReturn();
    }

    public function updated(ReturnRequest $returnRequest): void
    {
        $this->broadcastReturn();

        if ($returnRequest->wasChanged('status') && $returnRequest->status === 'Confirmed') {
            // Confirming a return restocks equipment — make stock counters
            // refresh everywhere too.
            DB::afterCommit(fn () => SafeBroadcast::send(new EquipmentChanged()));
        }
    }

    public function deleted(ReturnRequest $returnRequest): void
    {
        $this->broadcastReturn();
    }

    /** Broadcast only once the surrounding transaction (if any) commits. */
    private function broadcastReturn(): void
    {
        DB::afterCommit(fn () => SafeBroadcast::send(new ReturnsChanged()));
    }
}
