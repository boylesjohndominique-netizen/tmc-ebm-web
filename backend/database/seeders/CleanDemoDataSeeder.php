<?php

namespace Database\Seeders;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Clears ALL demo/staff/student users — leaving only the Super Admin
 * (username: admin) — together with everything they own:
 *
 *   - return requests, notifications, borrowing items, borrowings
 *   - notifications owned by the super admin are also cleared (they are
 *     demo artifacts), but the admin account itself is preserved.
 *
 * EQUIPMENT IS NOT TOUCHED — quantities are left exactly as they are.
 *
 * Run with:
 *   php artisan db:seed --class=CleanDemoDataSeeder
 */
class CleanDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('Clearing demo users (equipment is NOT touched)...');

        DB::transaction(function () {
            // Everything owned by non-admin users goes first.
            $this->deleteOwnedData(User::where('username', '!=', 'admin')->pluck('id'));

            // The super admin stays, but their demo notifications go.
            Notification::whereHas('user', fn ($q) => $q->where('username', 'admin'))->delete();

            // Remove any leftover notification/user rows orphaned by earlier
            // partial demo seeding.
            Notification::whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'notifications.user_id');
            })->delete();

            // Every non-super-admin user is removed (students, faculty,
            // staff, demo/stress administrators), by id rather than email
            // domain so nothing can be missed.
            $deleted = User::where('is_super_admin', false)->delete();

            $this->command->info("Deleted {$deleted} user(s). Super admin preserved.");
        });

        $remaining = User::count();
        $this->command->info("Users remaining: {$remaining} (should be 1 — the super admin).");
    }

    private function deleteOwnedData($userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $borrowingIds = BorrowingRequest::whereIn('user_id', $userIds)->pluck('id');

        ReturnRequest::whereIn('borrowing_request_id', $borrowingIds)->delete();
        ReturnRequest::whereIn('user_id', $userIds)->delete();
        Notification::whereIn('user_id', $userIds)->delete();
        BorrowingItem::whereIn('borrowing_request_id', $borrowingIds)->delete();
        BorrowingRequest::whereIn('id', $borrowingIds)->delete();
    }
}
