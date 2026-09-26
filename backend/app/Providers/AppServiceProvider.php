<?php

namespace App\Providers;

use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\UserPermission;
use App\Observers\BorrowingRequestObserver;
use App\Observers\EquipmentObserver;
use App\Observers\NotificationObserver;
use App\Observers\ReturnRequestObserver;
use App\Observers\UserObserver;
use App\Observers\UserPermissionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Every mutation to these models is pushed to connected staff
     * dashboards over WebSockets (see app/Observers + app/Events).
     */
    public function boot(): void
    {
        Equipment::observe(EquipmentObserver::class);
        BorrowingRequest::observe(BorrowingRequestObserver::class);
        ReturnRequest::observe(ReturnRequestObserver::class);
        User::observe(UserObserver::class);
        UserPermission::observe(UserPermissionObserver::class);
        Notification::observe(NotificationObserver::class);
    }
}
