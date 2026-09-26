<?php

namespace Database\Seeders;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Small demo dataset so the admin pages have something realistic to
 * show. Complements the base DatabaseSeeder (~4 borrowings) with
 * status variety across several demo borrowers:
 *
 *   - 12 borrowing requests: Pending / Approved / Rejected / Completed
 *   - 3 return requests:     Pending / Confirmed
 *   - matching notifications for the borrowers
 *
 * Re-runnable: cleans up its own previous rows first (demo users are
 * identified by the @demo.tmc.edu.ph email domain).
 *
 * Run with:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password123';

    /**
     * Demo borrowers: [username, first, middle, last, role].
     * mid initials are null ~40% of the time, like real registrations.
     */
    private const USERS = [
        ['maria.santos', 'Maria', 'R', 'Santos', 'Student'],
        ['juan.reyes', 'Juan', null, 'Reyes', 'Student'],
        ['ana.cruz', 'Ana', 'B', 'Cruz', 'Faculty'],
        ['mark.villanueva', 'Mark', null, 'Villanueva', 'Staff'],
        ['grace.mendoza', 'Grace', 'L', 'Mendoza', 'Student'],
        ['carlo.rivera', 'Carlo', null, 'Rivera', 'Faculty'],
    ];

    public function run(): void
    {
        $this->cleanPreviousDemoData();

        $admin = User::where('username', 'admin')->firstOrFail();
        $today = Carbon::today();

        // ------------------------------------------------------------
        // Users (verified Active, so they can log in on the mobile app)
        // ------------------------------------------------------------
        $studentRole = Role::where('name', 'Student')->firstOrFail();
        $facultyRole = Role::where('name', 'Faculty')->firstOrFail();
        $staffRole = Role::where('name', 'Staff')->firstOrFail();

        $users = [];

        foreach (self::USERS as [$username, $first, $mid, $last, $role]) {
            $users[$username] = User::firstOrCreate(
                ['username' => $username],
                [
                    'role_id' => match ($role) {
                        'Student' => $studentRole->id,
                        'Faculty' => $facultyRole->id,
                        default => $staffRole->id,
                    },
                    'first_name' => $first,
                    'middle_name' => $mid,
                    'last_name' => $last,
                    'email' => str_replace('.', '_', $username).'@demo.tmc.edu.ph',
                    'contact_number' => '09'.str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                    'password' => self::DEMO_PASSWORD,
                    'account_status' => 'Active',
                    'verified_at' => $today->copy()->subDays(30),
                    'verified_by' => $admin->id,
                    'created_at' => $today->copy()->subDays(35),
                ],
            );
        }

        $eq = fn (string $name) => Equipment::where('name', $name)->firstOrFail();

        // ------------------------------------------------------------
        // Borrowing requests
        // ------------------------------------------------------------
        $requests = [
            // --- Pending: the main review queue for demos --------------
            ['maria.santos', 'Pending', 2, 9, 'For our IT capstone defense next week.', [
                ['Gaming Laptop', 2], ['HDMI Cable', 2],
            ]],
            ['ana.cruz', 'Pending', 1, 4, 'Audio setup for the freshman orientation seminar.', [
                ['Wireless Microphone', 2], ['Speaker', 1],
            ]],
            ['mark.villanueva', 'Pending', 0, 6, 'Office inventory documentation this week.', [
                ['Scanner', 1], ['Printer', 1],
            ]],
            ['juan.reyes', 'Pending', -1, 7, 'Biology class experiment on pond water samples.', [
                ['Microscope', 4], ['Lab Thermometer', 6],
            ]],

            // --- Approved: active borrowings, some overdue -------------
            ['grace.mendoza', 'Approved', -4, 2, 'Approved — pick up at the equipment office.', [
                ['Tablet', 3],
            ]],
            ['carlo.rivera', 'Approved', -9, -2, 'Approved for the research exhibit.', [
                ['LED TV', 1], ['Tripod', 1],
            ]],
            ['maria.santos', 'Approved', -14, -5, 'Approved by the property custodian.', [
                ['Scientific Calculator', 5],
            ]],
            ['ana.cruz', 'Approved', -20, -13, 'Approved for lecture recording.', [
                ['Video Camera', 1], ['Tripod', 1],
            ]],

            // --- Rejected ----------------------------------------------
            ['juan.reyes', 'Rejected', -3, 5, 'Requested quantity exceeds current stock.', [
                ['Flash Drive', 10],
            ]],
            ['grace.mendoza', 'Rejected', -6, 1, 'Borrower has an unsettled overdue item.', [
                ['Chromebook', 2],
            ]],

            // --- Completed (borrowed, returned, confirmed) -------------
            ['carlo.rivera', 'Completed', -25, -16, 'Approved — for the faculty workshop.', [
                ['Power Strip', 3], ['Extension Reel', 2],
            ]],
            ['mark.villanueva', 'Completed', -30, -21, 'Approved for campus event coverage.', [
                ['Wireless Microphone', 1], ['Amplifier', 1],
            ]],
        ];

        $processedStatuses = ['Approved', 'Rejected', 'Completed'];

        foreach ($requests as [$username, $status, $borrowOffset, $returnOffset, $remarks, $items]) {
            $user = $users[$username];
            $borrowDate = $today->copy()->addDays($borrowOffset);
            $expectedReturn = $today->copy()->addDays($returnOffset);
            $createdAt = $borrowDate->copy()->subDays(2)->setTime(9, 30);
            $processed = in_array($status, $processedStatuses, true);

            $borrowing = BorrowingRequest::create([
                'user_id' => $user->id,
                'borrowing_date' => $borrowDate->toDateString(),
                'expected_return_date' => $expectedReturn->toDateString(),
                'status' => $status,
                'remarks' => $remarks,
                'processed_by' => $processed ? $admin->id : null,
                'created_at' => $createdAt,
                'updated_at' => $processed ? $createdAt->copy()->addHours(5) : $createdAt,
            ]);

            foreach ($items as [$equipmentName, $quantity]) {
                BorrowingItem::create([
                    'borrowing_request_id' => $borrowing->id,
                    'equipment_id' => $eq($equipmentName)->id,
                    'quantity' => $quantity,
                ]);
            }

            // --------------------------------------------------------
            // Side effects that mirror the real process() flow
            // --------------------------------------------------------

            if ($status === 'Approved') {
                foreach ($items as [$equipmentName, $quantity]) {
                    Equipment::where('name', $equipmentName)->decrement('available_quantity', $quantity);
                }
            }

            if ($processed) {
                [$title, $type] = match ($status) {
                    'Approved' => ['Borrowing Request Approved', 'Success'],
                    'Rejected' => ['Borrowing Request Rejected', 'Request'],
                    default => ['Borrowing Completed', 'Success'],
                };

                Notification::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'message' => "Your borrowing request #{$borrowing->id} ({$borrowDate->toDateString()}) was {$status}.",
                    'type' => $type,
                    'created_at' => $createdAt->copy()->addHours(5),
                ]);
            }
        }

        // ------------------------------------------------------------
        // Return requests on the overdue Approved borrowings
        // ------------------------------------------------------------
        $returnSpecs = [
            // [username, borrowOffset, return status, condition, remarks, processed?]
            ['carlo.rivera', -9, 'Pending', null, null, false],
            ['maria.santos', -14, 'Confirmed', 'Good', 'All items returned complete.', true],
            ['ana.cruz', -20, 'Confirmed', 'Fair', 'Camera strap missing; noted for follow-up.', true],
        ];

        foreach ($returnSpecs as [$username, $borrowOffset, $returnStatus, $condition, $remarks, $processed]) {
            $user = $users[$username];
            $expectedReturn = $today->copy()->addDays($borrowOffset + 7);
            $returnedAt = $expectedReturn->copy()->addDays(1)->setTime(10, 15);

            $borrowing = BorrowingRequest::where('user_id', $user->id)
                ->where('status', 'Approved')
                ->whereDate('borrowing_date', $today->copy()->addDays($borrowOffset)->toDateString())
                ->first();

            if (! $borrowing) {
                continue;
            }

            ReturnRequest::create([
                'borrowing_request_id' => $borrowing->id,
                'user_id' => $user->id,
                'returned_at' => $returnStatus === 'Pending' ? null : $returnedAt,
                'status' => $returnStatus,
                'condition_on_return' => $condition,
                'remarks' => $remarks,
                'processed_by' => $processed ? $admin->id : null,
                'created_at' => $returnedAt->copy()->subHours(6),
                'updated_at' => $processed ? $returnedAt->copy()->addHours(3) : now(),
            ]);

            if ($returnStatus === 'Confirmed') {
                // Restock + complete the parent borrowing, like process() does.
                $borrowing->items()->with('equipment')->get()->each(function ($item) {
                    Equipment::whereKey($item->equipment_id)->increment('available_quantity', $item->quantity);
                });

                $borrowing->update(['status' => 'Completed']);

                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Return Confirmed',
                    'message' => "Your return for request #{$borrowing->id} has been confirmed.",
                    'type' => 'Success',
                    'created_at' => $returnedAt->copy()->addHours(3),
                ]);
            }
        }

        $this->command->info('Demo data seeded: 6 users, 12 borrowing requests, 3 return requests.');
    }

    private function cleanPreviousDemoData(): void
    {
        $demoUserIds = User::where('email', 'like', '%@demo.tmc.edu.ph')->pluck('id');

        if ($demoUserIds->isEmpty()) {
            return;
        }

        $this->command->info('Cleaning previous demo data...');

        $borrowingIds = BorrowingRequest::whereIn('user_id', $demoUserIds)->pluck('id');

        ReturnRequest::whereIn('borrowing_request_id', $borrowingIds)->delete();
        Notification::whereIn('user_id', $demoUserIds)->delete();
        BorrowingItem::whereIn('borrowing_request_id', $borrowingIds)->delete();
        BorrowingRequest::whereIn('id', $borrowingIds)->delete();
        User::whereIn('id', $demoUserIds)->delete();

        // Restore stock the same way the stress seeder does: no remaining
        // demo borrowings can hold units.
        DB::table('equipment')->update([
            'available_quantity' => DB::raw('quantity'),
            'updated_at' => now(),
        ]);
    }
}
