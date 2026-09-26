<?php

namespace App\Events;

/**
 * Marker for events pushed to the private "staff" WebSocket channel.
 *
 * These events do NOT carry the changed record itself — they are cheap
 * "something changed" signals. Clients respond by refetching the affected
 * endpoint, so lists, dashboards and analytics stay correct without any
 * manual refreshing. (Carrying payloads would risk leaking sensitive data
 * and duplicating authorization logic on the client.)
 */
interface StaffDataChanged
{
    /** Stable machine name clients switch on, e.g. "equipment", "borrowings". */
    public function broadcastResource(): string;
}
