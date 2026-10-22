<?php

use App\Services\BorrowingPickupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('borrowings:expire-pickups', function (BorrowingPickupService $service) {
    $expired = $service->expireDue();
    $this->info("Expired {$expired} pickup receipt(s).");
})->purpose('Expire uncollected pickup receipts and notify students.');

Schedule::command('borrowings:expire-pickups')->everyMinute()->withoutOverlapping();
