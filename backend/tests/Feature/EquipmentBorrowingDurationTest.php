<?php

namespace Tests\Feature;

use App\Models\BorrowingRequest;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentBorrowingDurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private array $equipmentData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(1, 0));
        Mail::fake();
        $this->admin = $this->user('Administrator');
        $this->student = $this->user('Student');
        $this->equipmentData = [
            'category_id' => Category::create(['name' => 'AV'])->id,
            'name' => 'Projector', 'quantity' => 5, 'condition' => 'Good',
            'pickup_location' => 'AV Office, Building A, Room 102',
            'min_borrowing_days' => 10, 'max_borrowing_days' => 15,
        ];
    }

    private function user(string $role): User
    {
        return User::create([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'first_name' => $role, 'last_name' => 'Tester', 'id_number' => '24-123456',
            'username' => strtolower($role), 'email' => strtolower($role).'@example.test',
            'password' => 'test-password', 'account_status' => 'Active', 'verified_at' => now(),
        ]);
    }

    private function equipment(): Equipment
    {
        return Equipment::create($this->equipmentData + ['available_quantity' => 5, 'status' => 'Available']);
    }

    private function borrow(Equipment $equipment, string $returnDate, string $borrowingDate = '2026-10-24')
    {
        Sanctum::actingAs($this->student);

        return $this->postJson('/api/mobile/borrow', [
            'equipment_id' => $equipment->id, 'quantity' => 1,
            'borrowing_date' => $borrowingDate, 'expected_return_date' => $returnDate, 'purpose' => 'Class',
        ]);
    }

    public function test_admin_can_save_edit_and_clear_duration_limits_and_mobile_receives_the_details(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/equipment', $this->equipmentData)->assertCreated()
            ->assertJsonPath('pickup_location', $this->equipmentData['pickup_location'])
            ->assertJsonPath('min_borrowing_days', 10)->json('id');
        $this->putJson("/api/admin/equipment/{$id}", array_replace($this->equipmentData, [
            'pickup_location' => 'Library desk', 'max_borrowing_days' => 20,
        ]))->assertOk()->assertJsonPath('pickup_location', 'Library desk')
            ->assertJsonPath('max_borrowing_days', 20);
        Sanctum::actingAs($this->student);
        $this->getJson("/api/mobile/equipment/{$id}")->assertOk()
            ->assertJsonPath('pickup_location', 'Library desk')->assertJsonPath('min_borrowing_days', 10);
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/equipment/{$id}", array_replace($this->equipmentData, [
            'min_borrowing_days' => null, 'max_borrowing_days' => null,
        ]))->assertOk()->assertJsonPath('min_borrowing_days', null)->assertJsonPath('max_borrowing_days', null);
    }

    public function test_invalid_partial_and_reversed_duration_limits_are_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        foreach ([
            ['max_borrowing_days' => null],
            ['min_borrowing_days' => null],
            ['max_borrowing_days' => 9],
            ['min_borrowing_days' => 0],
            ['min_borrowing_days' => -1],
            ['min_borrowing_days' => 10.5],
            ['max_borrowing_days' => 3651],
            ['pickup_location' => str_repeat('a', 256)],
        ] as $invalid) {
            $this->postJson('/api/admin/equipment', array_replace($this->equipmentData, $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('equipment', 0);
    }

    public function test_students_cannot_change_equipment_rules(): void
    {
        $equipment = $this->equipment();
        Sanctum::actingAs($this->student);
        $this->putJson("/api/admin/equipment/{$equipment->id}", $this->equipmentData)->assertForbidden();
    }

    public function test_ten_and_fifteen_day_loans_are_allowed_including_across_months(): void
    {
        $equipment = $this->equipment();
        $this->borrow($equipment, '2026-11-03')->assertCreated();
        $this->borrow($equipment, '2026-11-08')->assertCreated();
        $this->borrow($equipment, '2027-01-10', '2026-12-31')->assertCreated();
        $this->assertDatabaseCount('borrowing_requests', 3);
    }

    public function test_nine_and_sixteen_day_loans_cannot_be_submitted_even_with_direct_api_calls(): void
    {
        $equipment = $this->equipment();
        $this->borrow($equipment, '2026-11-02')->assertUnprocessable();
        $this->borrow($equipment, '2026-11-09')->assertUnprocessable();
        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_legacy_equipment_remains_unrestricted_and_exact_duration_is_supported(): void
    {
        $equipment = $this->equipment();
        $equipment->update(['min_borrowing_days' => null, 'max_borrowing_days' => null]);
        $equipment->forceFill(['borrowing_starts_on' => '2020-01-01', 'borrowing_ends_on' => '2020-01-02'])->save();
        $this->borrow($equipment, '2026-10-25')->assertCreated();
        $this->borrow($equipment, '2027-02-01')->assertCreated();
        $equipment->update(['min_borrowing_days' => 10, 'max_borrowing_days' => 10]);
        $this->borrow($equipment, '2026-11-03')->assertCreated();
        $this->borrow($equipment, '2026-11-04')->assertUnprocessable();
    }

    public function test_approval_rechecks_changed_duration_limits_without_reserving_stock(): void
    {
        $equipment = $this->equipment();
        $id = $this->borrow($equipment, '2026-11-03')->assertCreated()->json('data.id');
        $equipment->update(['min_borrowing_days' => 11]);
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/borrowings/{$id}/process", [
            'status' => 'Approved', 'pickup_starts_at' => '2026-10-24T01:00:00Z',
            'pickup_ends_at' => '2026-10-24T03:00:00Z',
        ])->assertUnprocessable();
        $this->assertSame(5, $equipment->fresh()->available_quantity);
        $this->assertSame('Pending', BorrowingRequest::findOrFail($id)->status);
    }

    public function test_receipt_keeps_the_approved_pickup_location_when_equipment_is_edited(): void
    {
        $equipment = $this->equipment();
        $id = $this->borrow($equipment, '2026-11-03')->assertCreated()->json('data.id');
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/borrowings/{$id}/process", [
            'status' => 'Approved', 'pickup_starts_at' => '2026-10-24T01:00:00Z',
            'pickup_ends_at' => '2026-10-24T03:00:00Z',
        ])->assertOk();
        $equipment->update(['pickup_location' => 'Library']);
        $this->assertSame($this->equipmentData['pickup_location'], BorrowingRequest::findOrFail($id)->receipt['items'][0]['pickup_location']);
    }
}
