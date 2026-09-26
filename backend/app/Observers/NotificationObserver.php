<?php

namespace App\Observers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Pushes new notifications to their recipient's private channel, so the
 * bell badge and inbox update instantly (replacing 15s polling).
 */
class NotificationObserver
{
    public function created(Notification $notification): void
    {
        // Broadcast only once the surrounding transaction (if any) commits,
        // so the client never refetches data it can't see yet.
        DB::afterCommit(function () use ($notification) {
            SafeBroadcast::send(new NotificationCreated(
                recipientId: (int) $notification->user_id,
                notificationId: (int) $notification->id,
                type: (string) ($notification->type ?? 'System'),
            ));
        });
    }
}
