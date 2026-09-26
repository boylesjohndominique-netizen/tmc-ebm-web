<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Throwable;

/**
 * Broadcast helper that never lets a WebSocket hiccup break an API call.
 *
 * Events use ShouldBroadcastNow (synchronous delivery), so if the Reverb
 * server happens to be down the pusher client would throw inside the
 * request. We catch and log instead — data writes must always succeed,
 * realtime is a bonus layer, and clients also refetch on focus as a
 * safety net.
 */
class SafeBroadcast
{
    public static function send(ShouldBroadcast $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            report($e); // logged; never surfaced to the API caller
        }
    }
}
