<?php

namespace Database\Seeders;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\ReturnRequest;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Stress-test seeder.
 *
 * Generates a large volume of realistic data to exercise pagination,
 * filtering, dashboards and reports:
 *
 *   - 120 users  (5 admin, 60 student, 40 faculty, 15 staff)
 *   - ~40 equipment items across 5 categories
 *   - 400 borrowing requests (Pending / Approved / Rejected / Cancelled)
 *   - ~70 return requests   (Pending / Confirmed / Rejected)
 *   - notifications for every stress user + admin alerts
 *
 * Deterministic: same output on every run (fixed RNG seed).
 * Re-runnable: cleans up its own previous rows first (stress users are
 * identified by the @stress.tmc.edu.ph email domain).
 *
 * Run with:
 *   php artisan db:seed --class=StressTestSeeder
 */
class StressTestSeeder extends Seeder
{
    private const SEED = 20260917;

    private const USER_COUNTS = [
        'Administrator' => 5,
        'Student' => 60,
        'Faculty' => 40,
        'Staff' => 15,
    ];

    private const REQUEST_COUNTS = [
        'Pending' => 180,
        'Approved' => 130,
        'Rejected' => 50,
        'Cancelled' => 40,
    ];

    private const CATEGORIES = [
        'Information Technology' => 'Computers and IT accessories',
        'Audio Visual' => 'AV and presentation equipment',
        'Office Equipment' => 'Common office machines',
        'Electrical' => 'Cables, power and storage devices',
        'Science Laboratory' => 'Laboratory instruments',
    ];

    private const EQUIPMENT = [
        // [name, category, quantity, description]
        ['Gaming Laptop', 'Information Technology', 10, 'High-spec laptop for multimedia classes.'],
        ['Tablet', 'Information Technology', 25, 'Android tablet for e-learning activities.'],
        ['Chromebook', 'Information Technology', 30, 'Lightweight laptop for web-based tasks.'],
        ['Computer Mouse', 'Information Technology', 50, 'USB mouse for computer laboratory use.'],
        ['Projector Screen', 'Audio Visual', 6, 'Pull-down projector screen for lectures.'],
        ['LED TV', 'Audio Visual', 8, '42-inch LED television for presentations.'],
        ['Video Camera', 'Audio Visual', 6, 'Camcorder for school events coverage.'],
        ['Tripod', 'Audio Visual', 15, 'Camera tripod for documentation.'],
        ['Microphone', 'Audio Visual', 20, 'Wired microphone for seminars.'],
        ['Wireless Microphone', 'Audio Visual', 12, 'Wireless mic set for school programs.'],
        ['Speaker', 'Audio Visual', 10, 'Portable speaker for campus events.'],
        ['Amplifier', 'Audio Visual', 4, 'Audio amplifier for assemblies.'],
        ['Portable Sound System', 'Audio Visual', 6, 'Complete portable PA system.'],
        ['Headphones', 'Audio Visual', 30, 'Stereo headphones for language lab.'],
        ['Document Camera', 'Audio Visual', 3, 'Overhead document camera for lectures.'],
        ['Printer', 'Office Equipment', 6, 'Multifunction inkjet printer.'],
        ['Scanner', 'Office Equipment', 5, 'Flatbed document scanner.'],
        ['Photocopier', 'Office Equipment', 3, 'Standalone office photocopier.'],
        ['Laminating Machine', 'Office Equipment', 4, 'A3 laminating machine.'],
        ['Paper Shredder', 'Office Equipment', 3, 'Cross-cut paper shredder.'],
        ['Extension Reel', 'Electrical', 10, '10-meter extension cord reel.'],
        ['Power Strip', 'Electrical', 20, '6-outlet surge-protected power strip.'],
        ['VGA Cable', 'Electrical', 30, 'VGA cable for projector connection.'],
        ['USB Hub', 'Electrical', 20, '4-port USB 3.0 hub.'],
        ['Flash Drive', 'Electrical', 40, '32GB USB flash drive.'],
        ['External DVD Drive', 'Electrical', 10, 'USB external DVD writer.'],
        ['Power Bank', 'Electrical', 15, '10,000mAh power bank.'],
        ['Microscope', 'Science Laboratory', 12, 'Compound light microscope for biology.'],
        ['Telescope', 'Science Laboratory', 2, 'Refractor telescope for astronomy club.'],
        ['Scientific Calculator', 'Science Laboratory', 45, 'Graphing calculator for math classes.'],
        ['Multimeter', 'Science Laboratory', 10, 'Digital multimeter for electronics.'],
        ['Lab Thermometer', 'Science Laboratory', 30, 'Digital laboratory thermometer.'],
        ['Digital Scale', 'Science Laboratory', 8, 'Precision digital weighing scale.'],
        ['Centrifuge', 'Science Laboratory', 2, 'Benchtop centrifuge for chemistry lab.'],
        ['Hot Plate', 'Science Laboratory', 4, 'Laboratory hot plate with stirrer.'],
    ];

    private const FIRST_NAMES = [
        'Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Angel', 'Jose', 'Jhon', 'Mark', 'Joshua',
        'Cristine', 'Angelic', 'Francis', 'Vincent', 'Rafael', 'Carlo', 'Gerald', 'Jomari',
        'Divina', 'Grace', 'Joyce', 'Karen', 'Liza', 'Maricel', 'Nadine', 'Rhea', 'Sofia',
        'Trisha', 'Wendy', 'Zaira',
    ];

    private const LAST_NAMES = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Villanueva',
        'Aquino', 'Rivera', 'Domingo', 'Castillo', 'Flores', 'Ramos', 'Navarro', 'Salazar',
        'Mercado', 'Delgado', ' Tolentino', 'Padilla', 'Fernandez', 'Gutierrez', 'Soriano',
        'Valdez', 'Dela Cruz',
    ];

    private const REJECT_REMARKS = [
        'Equipment reserved for a faculty activity.',
        'Requested quantity exceeds current stock.',
        'Incomplete details on the request form.',
        'Borrower has an unsettled overdue item.',
        'Equipment under maintenance during the requested date.',
    ];

    private const CONDITIONS = ['Good', 'Good', 'Good', 'Fair', 'Damaged'];

    private const BAN_REASONS = [
        'Repeated late returns without notice.',
        'Returned equipment with unreported damage.',
        'Violated the equipment borrowing guidelines.',
        'Misrepresentation during the borrowing process.',
    ];

    public function run(): void
    {
        mt_srand(self::SEED);

        $this->cleanPreviousStressData();
        $this->ensureRoles();

        $this->command->info('Creating users...');
        $users = $this->seedUsers();

        $this->command->info('Creating categories and equipment...');
        $equipment = $this->seedEquipment();

        $this->command->info('Creating borrowing and return requests...');
        [$borrowings, $items, $returns] = $this->seedBorrowings($users, $equipment);

        $this->command->info('Creating notifications...');
        $this->seedNotifications($users);
        $this->seedSystemNotifications();

        $this->recomputeAvailability($equipment, $items, $returns);

        $this->command->info(sprintf(
            'Stress data ready: %d users, %d equipment, %d borrowings, %d items, %d returns.',
            count($users),
            count($equipment),
            count($borrowings),
            count($items),
            count($returns),
        ));
    }

    /* ------------------------------------------------------------------ */

    private function cleanPreviousStressData(): void
    {
        $stressUserIds = User::where('email', 'like', '%@stress.tmc.edu.ph')->pluck('id');

        if ($stressUserIds->isEmpty()) {
            return;
        }

        $this->command->info('Cleaning previous stress data...');

        $borrowingIds = BorrowingRequest::whereIn('user_id', $stressUserIds)->pluck('id');

        ReturnRequest::whereIn('borrowing_request_id', $borrowingIds)->delete();
        Notification::whereIn('user_id', $stressUserIds)->delete();
        BorrowingItem::whereIn('borrowing_request_id', $borrowingIds)->delete();
        BorrowingRequest::whereIn('id', $borrowingIds)->delete();
        User::whereIn('id', $stressUserIds)->delete();
    }

    private function ensureRoles(): void
    {
        foreach (array_keys(self::USER_COUNTS) as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }

    private function roleIds(): array
    {
        return Role::whereIn('name', array_keys(self::USER_COUNTS))->pluck('id', 'name')->all();
    }

    private function seedUsers(): array
    {
        $roleIds = $this->roleIds();
        $users = [];
        $now = Carbon::now();

        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'Administrator'))->pluck('id')->all();

        foreach (self::USER_COUNTS as $role => $count) {
            $prefix = strtolower($role === 'Administrator' ? 'admin' : $role);

            for ($i = 1; $i <= $count; $i++) {
                $first = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
                $last = trim(self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)]);

                // Staff/faculty/admin are Active. Students simulate the
                // mobile-app lifecycle: mostly Active, some still Pending,
                // a few Banned for moderation testing.
                $status = $role === 'Student'
                    ? (match (true) {
                        mt_rand(1, 100) <= 15 => 'Pending',
                        mt_rand(1, 100) <= 10 => 'Banned',
                        default => 'Active',
                    })
                    : 'Active';

                $users[] = User::firstOrCreate(
                    ['username' => sprintf('%s_%02d', $prefix, $i)],
                    [
                        'role_id' => $roleIds[$role],
                        'first_name' => $first,
                        'middle_name' => mt_rand(0, 4) === 0 ? null : strtoupper(self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)][0]),
                        'last_name' => $last,
                        'email' => sprintf('%s_%02d@stress.tmc.edu.ph', $prefix, $i),
                        'contact_number' => sprintf('09%09d', mt_rand(0, 999999999)),
                        'password' => 'password123',
                        'account_status' => $status,
                        'verified_at' => $status === 'Pending' ? null : $now->copy()->subDays(mt_rand(1, 29)),
                        'verified_by' => $status === 'Pending' ? null : $admins[mt_rand(0, count($admins) - 1)],
                        'banned_at' => $status === 'Banned' ? $now->copy()->subDays(mt_rand(1, 20)) : null,
                        'ban_reason' => $status === 'Banned'
                            ? self::BAN_REASONS[mt_rand(0, count(self::BAN_REASONS) - 1)]
                            : null,
                        'created_at' => $now->copy()->subDays(mt_rand(30, 180)),
                    ],
                );
            }
        }

        return $users;
    }

    private function seedEquipment(): array
    {
        $equipment = [];

        foreach (self::EQUIPMENT as [$name, $category, $quantity, $description]) {
            $cat = DB::table('categories')->where('name', $category)->first();

            if (! $cat) {
                $catId = DB::table('categories')->insertGetId([
                    'name' => $category,
                    'description' => self::CATEGORIES[$category],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $catId = $cat->id;
            }

            $eq = DB::table('equipment')->where('name', $name)->first();

            if ($eq) {
                $equipment[] = (object) ['id' => $eq->id, 'quantity' => $eq->quantity, 'name' => $name];
            } else {
                $id = DB::table('equipment')->insertGetId([
                    'category_id' => $catId,
                    'name' => $name,
                    'description' => $description,
                    'quantity' => $quantity,
                    'available_quantity' => $quantity,
                    'condition' => 'Good',
                    'status' => 'Available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $equipment[] = (object) ['id' => $id, 'quantity' => $quantity, 'name' => $name];
            }
        }

        return $equipment;
    }

    /**
     * @return array{0: array, 1: array, 2: array} [borrowings, items, returns]
     */
    private function seedBorrowings(array $users, array $equipment): array
    {
        $admins = User::where('username', 'like', 'admin_%')->pluck('id')->all();
        $borrowers = array_values(array_filter($users, fn ($u) => $u->username !== 'admin' && ! str_starts_with($u->username, 'admin_')));

        $today = Carbon::today();
        $rows = [];

        foreach (self::REQUEST_COUNTS as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $borrower = $borrowers[mt_rand(0, count($borrowers) - 1)];

                match ($status) {
                    'Pending' => $borrowingDate = $today->copy()->subDays(mt_rand(0, 7))->addDays(mt_rand(0, 5)),
                    'Approved' => $borrowingDate = $today->copy()->subDays(mt_rand(1, 60)),
                    default => $borrowingDate = $today->copy()->subDays(mt_rand(1, 45)),
                };

                $expectedReturn = $borrowingDate->copy()->addDays(mt_rand(1, 14));

                // Past-due approved borrowings: keep them Approved so the
                // dashboard "overdue" counter has data to report.
                if ($status === 'Approved' && mt_rand(1, 100) <= 40) {
                    $borrowingDate = $today->copy()->subDays(mt_rand(20, 60));
                    $expectedReturn = $borrowingDate->copy()->addDays(mt_rand(3, 10));
                }

                $createdAt = $borrowingDate->copy()->subDays(mt_rand(0, 2))->setTime(mt_rand(7, 17), mt_rand(0, 59));
                $updatedAt = $createdAt->copy();

                if (in_array($status, ['Approved', 'Rejected'], true)) {
                    $updatedAt = $createdAt->copy()->addHours(mt_rand(1, 30));
                }

                $rows[] = [
                    'user_id' => $borrower->id,
                    'borrowing_date' => $borrowingDate->toDateString(),
                    'expected_return_date' => $expectedReturn->toDateString(),
                    'status' => $status,
                    'remarks' => match ($status) {
                        'Rejected' => self::REJECT_REMARKS[mt_rand(0, count(self::REJECT_REMARKS) - 1)],
                        'Cancelled' => 'Borrower withdrew the request.',
                        'Approved' => 'Approved by the property custodian.',
                        default => null,
                    },
                    'processed_by' => in_array($status, ['Approved', 'Rejected'], true)
                        ? $admins[mt_rand(0, count($admins) - 1)]
                        : null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                    '_expected_return' => $expectedReturn, // helper, stripped before insert
                    '_user_id' => $borrower->id, // helper, stripped before insert
                ];
            }
        }

        // Insert row-by-row and capture the real generated ids (MySQL
        // auto-increment does not reuse ids, so they cannot be predicted
        // from the current max).
        $payload = array_map(
            fn ($r) => collect($r)->except(['_expected_return', '_user_id'])->all(),
            $rows,
        );
        $borrowingIds = [];

        foreach ($payload as $n => $row) {
            $borrowingIds[$n] = DB::table('borrowing_requests')->insertGetId($row);
        }

        // --- Borrowing items -------------------------------------------
        $items = [];

        foreach ($borrowingIds as $n => $id) {
            $row = $rows[$n];
            $count = mt_rand(1, 3);
            $picked = (array) array_rand($equipment, $count);

            foreach ($picked as $index) {
                $eq = $equipment[$index];

                $items[] = [
                    'borrowing_request_id' => $id,
                    'equipment_id' => $eq->id,
                    'quantity' => mt_rand(1, max(1, min(4, intdiv(max($eq->quantity, 1), 8)))),
                    '_user_id' => $row['_user_id'],
                    '_expected_return' => $row['_expected_return'],
                    '_status' => $row['status'],
                ];
            }
        }

        DB::table('borrowing_items')->insert(array_map(
            fn ($i) => collect($i)->except(['_user_id', '_expected_return', '_status'])->all(),
            $items,
        ));

        // --- Return requests for due approved borrowings ---------------
        // One return request per borrowing (a borrowing may contain several
        // items), sampled from borrowings whose expected return has passed.
        $dueByRequest = [];

        foreach ($items as $item) {
            if ($item['_status'] === 'Approved' && $item['_expected_return']->lessThan($today)) {
                $dueByRequest[$item['borrowing_request_id']][] = $item;
            }
        }

        $dueKeys = array_keys($dueByRequest);
        shuffle($dueKeys);
        $dueIds = array_slice($dueKeys, 0, 70);
        $returns = [];

        $statuses = array_merge(
            array_fill(0, 50, 'Confirmed'),
            array_fill(0, 12, 'Pending'),
            array_fill(0, 8, 'Rejected'),
        );

        foreach ($dueIds as $n => $borrowingId) {
            $item = $dueByRequest[$borrowingId][0];
            $status = $statuses[$n % count($statuses)];
            $returnedAt = $item['_expected_return']->copy()->subDays(mt_rand(0, 2))->setTime(mt_rand(8, 16), mt_rand(0, 59));

            $returns[] = [
                'borrowing_request_id' => $borrowingId,
                'user_id' => $item['_user_id'],
                'returned_at' => $status === 'Pending' ? null : $returnedAt,
                'status' => $status,
                'condition_on_return' => $status === 'Pending' ? null : self::CONDITIONS[mt_rand(0, count(self::CONDITIONS) - 1)],
                'remarks' => $status === 'Rejected' ? 'Return rejected: incomplete items submitted.' : null,
                'processed_by' => in_array($status, ['Confirmed', 'Rejected'], true)
                    ? $admins[mt_rand(0, count($admins) - 1)]
                    : null,
                'created_at' => $returnedAt->copy()->subHours(mt_rand(1, 10)),
                'updated_at' => $status === 'Pending' ? now() : $returnedAt->copy()->addHours(mt_rand(1, 20)),
            ];
        }

        if ($returns !== []) {
            DB::table('return_requests')->insert($returns);
        }

        return [$rows, $items, $returns];
    }

    private function seedNotifications(array $users): void
    {
        $rows = [];
        $titles = [
            'Request' => ['Borrowing Request Approved', 'Borrowing Request Rejected', 'Borrowing Request Received'],
            'Return' => ['Return Reminder', 'Return Confirmed', 'Overdue Notice'],
            'System' => ['System Maintenance Advisory', 'New Equipment Available'],
        ];

        foreach ($users as $user) {
            for ($i = 0, $n = mt_rand(2, 5); $i < $n; $i++) {
                $type = ['Request', 'Return', 'System'][mt_rand(0, 2)];

                $rows[] = [
                    'user_id' => $user->id,
                    'title' => $titles[$type][mt_rand(0, count($titles[$type]) - 1)],
                    'message' => 'This is a generated stress-test notification for data volume testing.',
                    'type' => $type,
                    'read_at' => mt_rand(1, 100) <= 40 ? now()->subDays(mt_rand(1, 20)) : null,
                    'created_at' => now()->subDays(mt_rand(0, 25)),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
    }

    private function seedSystemNotifications(): void
    {
        $posts = [
            ['Equipment Borrowing Guidelines Updated', 'Please review the updated borrowing guidelines before submitting requests.', 'System', true],
            ['Scheduled Maintenance', 'The equipment office will be closed this weekend for inventory.', 'System', true],
            ['New Laptops Available', 'Ten new laptops are now available for borrowing.', 'Announcement', true],
            ['Return Reminder Policy', 'Borrowers will receive reminders two days before the due date.', 'System', true],
            ['Overdue Penalties', 'Overdue items must be settled before new requests are approved.', 'System', true],
            ['Semester Break Schedule', 'Borrowing transactions pause during the semester break.', 'Announcement', true],
            ['Old Laboratory Manual', 'Archived notice from the previous academic year.', 'System', false],
            ['Temporary Equipment Recall', 'Old notice: recalled items have all been returned.', 'Announcement', false],
        ];

        foreach ($posts as [$title, $message, $type, $active]) {
            DB::table('system_notifications')->insert([
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'active' => $active,
                'created_at' => now()->subDays(mt_rand(1, 60)),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Recompute available_quantity: approved units are out unless their
     * borrowing has a Confirmed return (restocked).
     */
    private function recomputeAvailability(array $equipment, array $items, array $returns): void
    {
        $confirmedBorrowingIds = collect($returns)
            ->filter(fn ($r) => $r['status'] === 'Confirmed')
            ->pluck('borrowing_request_id')
            ->flip()
            ->all();

        $out = [];

        foreach ($items as $item) {
            if (
                $item['_status'] === 'Approved'
                && ! isset($confirmedBorrowingIds[$item['borrowing_request_id']])
            ) {
                $out[$item['equipment_id']] = ($out[$item['equipment_id']] ?? 0) + $item['quantity'];
            }
        }

        foreach ($equipment as $eq) {
            $available = max(0, $eq->quantity - ($out[$eq->id] ?? 0));

            DB::table('equipment')
                ->where('id', $eq->id)
                ->update(['available_quantity' => $available, 'updated_at' => now()]);
        }
    }
}
