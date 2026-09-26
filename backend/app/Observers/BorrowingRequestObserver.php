<?php

namespace App\Observers;

use App\Events\BorrowingsChanged;
use App\Models\BorrowingRequest;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Broadcasts borrowing-request lifecycle changes (new student submissions,
 * approvals, rejections, edits) to all connected staff dashboards.
 */
class BorrowingRequestObserver
{
    public function created(BorrowingRequest $borrowing): void
    {
        $this->broadcast();
    }

    public function updated(BorrowingRequest $borrowing): void
    {
        $this->broadcast();
    }

    public function deleted(BorrowingRequest $borrowing): void
    {
        $this->broadcast();
    }

    /** Broadcast only once the surrounding transaction (if any) commits. */
    private function broadcast(): void
    {
        DB::afterCommit(fn () => SafeBroadcast::send(new BorrowingsChanged()));
    }
}
