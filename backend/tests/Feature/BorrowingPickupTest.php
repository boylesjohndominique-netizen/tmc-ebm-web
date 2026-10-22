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

class BorrowingPickupTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    private User $staff;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(1, 0));
        Mail::fake();
        $this->student = $this->user('Student', 'student');
        $this->admin = $this->user('Administrator', 'admin');
        $this->staff = $this->user('Staff', 'staff');
        $category = Category::create(['name' => 'Audio Visual']);
        $this->equipment = Equipment::create([
            'category_id' => $category->id, 'name' => 'Projector',
            'description' => 'HDMI projector with remote', 'quantity' => 5,
            'available_quantity' => 5, 'condition' => 'Good', 'status' => 'Available',
        ]);
    }

    private function user(string $role, string $username): User
    {
        return User::create([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'first_name' => ucfirst($username), 'last_name' => 'Tester',
            'id_number' => '24-123456', 'username' => $username,
            'email' => $username.'@example.test', 'password' => 'test-password',
            'account_status' => 'Active', 'verified_at' => now(),
        ]);
    }

    private function submit(): BorrowingRequest
    {
        Sanctum::actingAs($this->student);
        $response = $this->postJson('/api/mobile/borrow', [
            'equipment_id' => $this->equipment->id, 'quantity' => 2,
            'borrowing_date' => '2026-10-22', 'expected_return_date' => '2026-10-24',
            'purpose' => 'Class presentation', 'notes' => 'Bring HDMI cable',
        ])->assertCreated();

        return BorrowingRequest::findOrFail($response->json('data.id'));
    }

    private function approve(BorrowingRequest $borrowing): BorrowingRequest
    {
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/borrowings/{$borrowing->id}/process", [
            'status' => 'Approved', 'remarks' => 'Collect from AV office',
            'pickup_starts_at' => now()->addHour()->toISOString(),
            'pickup_ends_at' => now()->addHours(3)->toISOString(),
        ])->assertOk();

        return $borrowing->fresh();
    }

    public function test_each_request_has_unique_random_codes_and_approval_issues_a_detailed_receipt(): void
    {
        $first = $this->submit();
        $second = $this->submit();
        $this->assertMatchesRegularExpression('/^\d{12}$/', $first->transaction_code);
        $this->assertNotSame($first->transaction_code, $second->transaction_code);
        $this->assertNotSame($first->qr_token, $second->qr_token);
        $this->assertNull($first->receipt);
        $approved = $this->approve($first);
        $this->assertSame('Awaiting', $approved->pickup_status);
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
        $this->assertSame('Projector', $approved->receipt['items'][0]['name']);
        $this->assertSame(2, $approved->receipt['items'][0]['quantity']);
        $this->assertStringContainsString('Class presentation', $approved->receipt['borrower_remarks']);
        $this->assertStringContainsString('Bring HDMI cable', $approved->receipt['borrower_remarks']);
        $this->assertSame('Collect from AV office', $approved->receipt['approval_remarks']);
        $this->assertSame('24-123456', $approved->receipt['borrower']['id_number']);
        $this->assertSame('TMCEBMS:'.$approved->qr_token, $approved->receipt['qr_payload']);
        $this->equipment->update(['name' => 'Renamed projector']);
        $this->assertSame('Projector', $approved->fresh()->receipt['items'][0]['name']);

        Sanctum::actingAs($this->student);
        $rows = $this->getJson('/api/mobile/borrowings')->assertOk()->assertJsonCount(2)->json();
        $row = collect($rows)->firstWhere('id', $approved->id);
        $this->assertSame($approved->transaction_code, $row['receipt']['transaction_code']);
        $this->assertArrayNotHasKey('qr_token', $row);
        $other = $this->user('Student', 'other');
        Sanctum::actingAs($other);
        $this->getJson('/api/mobile/borrowings')->assertOk()->assertExactJson([]);
    }

    public function test_staff_can_find_the_number_and_confirm_pickup_once_with_a_qr_scan(): void
    {
        $borrowing = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $this->getJson('/api/admin/borrowings/receipt?code='.$borrowing->transaction_code)
            ->assertOk()->assertJsonPath('data.borrower.name', 'Student Tester');
        $this->getJson('/api/admin/borrowings/receipt?code='.$borrowing->receipt['qr_payload'])
            ->assertOk()->assertJsonPath('data.borrower.id_number', '24-123456');
        $this->assertSame('Awaiting', $borrowing->fresh()->pickup_status);
        $payload = ['code' => $borrowing->receipt['qr_payload'], 'status' => 'Received', 'method' => 'qr'];
        $this->postJson('/api/admin/borrowings/pickup', $payload)
            ->assertOk()->assertJsonPath('data.pickup_status', 'Received');
        $this->postJson('/api/admin/borrowings/pickup', $payload)->assertConflict();
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
        $this->assertSame($this->staff->id, $borrowing->fresh()->pickup_processed_by);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'title' => 'Equipment Pickup Confirmed']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->student->id, 'title' => 'Equipment Pickup Confirmed']);
        Sanctum::actingAs($this->student);
        $this->postJson("/api/mobile/borrowings/{$borrowing->id}/return")->assertCreated();
    }

    public function test_manual_handover_works_and_returns_are_blocked_before_pickup(): void
    {
        $borrowing = $this->approve($this->submit());
        Sanctum::actingAs($this->student);
        $this->postJson("/api/mobile/borrowings/{$borrowing->id}/return")->assertUnprocessable();
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/borrowings/pickup', [
            'code' => $borrowing->transaction_code, 'status' => 'Received', 'method' => 'manual',
        ])->assertOk()->assertJsonPath('data.pickup_status', 'Received');
    }

    public function test_early_or_expired_scans_are_blocked_and_no_show_rejection_restores_stock_once(): void
    {
        $borrowing = $this->approve($this->submit());
        Sanctum::actingAs($this->staff);
        $scan = ['code' => $borrowing->receipt['qr_payload'], 'status' => 'Received', 'method' => 'qr'];
        $this->postJson('/api/admin/borrowings/pickup', $scan)->assertConflict();
        $this->travel(4)->hours();
        $this->postJson('/api/admin/borrowings/pickup', $scan)->assertConflict();
        $reject = [
            'code' => $borrowing->transaction_code, 'status' => 'Rejected',
            'method' => 'manual', 'remarks' => 'Student did not arrive during the pickup window.',
        ];
        $this->postJson('/api/admin/borrowings/pickup', $reject)->assertOk()
            ->assertJsonPath('data.status', 'Rejected')->assertJsonPath('data.pickup_status', 'Rejected');
        $this->postJson('/api/admin/borrowings/pickup', $reject)->assertConflict();
        $this->postJson('/api/admin/borrowings/pickup', $scan)->assertConflict();
        $this->assertSame(5, $this->equipment->fresh()->available_quantity);
        $this->assertSame($this->admin->id, $borrowing->fresh()->processed_by);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'title' => 'Equipment Pickup Rejected']);
        Sanctum::actingAs($this->student);
        $this->postJson("/api/mobile/borrowings/{$borrowing->id}/return")->assertUnprocessable();
    }

    public function test_permissions_validation_and_invalid_codes_are_enforced(): void
    {
        $borrowing = $this->submit();
        Sanctum::actingAs($this->staff);
        $this->putJson("/api/admin/borrowings/{$borrowing->id}/process", ['status' => 'Rejected'])->assertForbidden();
        $borrowing = $this->approve($borrowing);
        Sanctum::actingAs($this->student);
        $this->getJson('/api/admin/borrowings/receipt?code='.$borrowing->transaction_code)->assertForbidden();
        $this->postJson('/api/admin/borrowings/pickup', [
            'code' => $borrowing->transaction_code, 'status' => 'Received', 'method' => 'manual',
        ])->assertForbidden();
        Sanctum::actingAs($this->staff);
        $this->getJson('/api/admin/borrowings/receipt?code=not-a-receipt')->assertUnprocessable();
        $this->getJson('/api/admin/borrowings/receipt?code=100000000000')->assertNotFound();
        $this->postJson('/api/admin/borrowings/pickup', [
            'code' => $borrowing->transaction_code, 'status' => 'Rejected', 'method' => 'manual',
        ])->assertUnprocessable();
        $this->postJson('/api/admin/borrowings/pickup', [
            'code' => $borrowing->transaction_code, 'status' => 'Received', 'method' => 'qr',
        ])->assertUnprocessable();
        $this->staff->permissions()->create(['module' => 'borrowings', 'actions' => ['view']]);
        $this->staff->unsetRelation('permissions');
        $this->getJson('/api/admin/borrowings/receipt?code='.$borrowing->transaction_code)->assertForbidden();
    }

    public function test_approval_requires_a_valid_window_and_cannot_reserve_stock_twice(): void
    {
        $borrowing = $this->submit();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/borrowings/{$borrowing->id}/process", ['status' => 'Approved'])->assertUnprocessable();
        $borrowing = $this->approve($borrowing);
        $this->putJson("/api/admin/borrowings/{$borrowing->id}/process", [
            'status' => 'Approved', 'pickup_starts_at' => now()->addHour()->toISOString(),
            'pickup_ends_at' => now()->addHours(3)->toISOString(),
        ])->assertConflict();
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_admin_rejection_does_not_issue_a_receipt_or_reserve_stock(): void
    {
        $borrowing = $this->submit();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/borrowings/{$borrowing->id}/process", [
            'status' => 'Rejected', 'remarks' => 'Equipment needed for a college event.',
        ])->assertOk()->assertJsonPath('data.receipt', null);
        $this->assertSame(5, $this->equipment->fresh()->available_quantity);
    }

    public function test_pickup_uses_the_receipt_student_and_does_not_record_unperformed_checks(): void
    {
        $borrowing = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/borrowings/pickup', [
            'code' => $borrowing->transaction_code, 'status' => 'Received', 'method' => 'manual',
            'student_id_number' => 'WRONG-ID', 'equipment_checked' => true,
        ])->assertOk()->assertJsonPath('data.borrower.id_number', '24-123456')
            ->assertJsonPath('data.pickup_status', 'Received');
        $this->assertArrayNotHasKey('verified_student_id', $borrowing->fresh()->receipt_snapshot);
        $this->assertArrayNotHasKey('equipment_checked', $borrowing->fresh()->receipt_snapshot);
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_staff_can_reject_a_reviewed_qr_receipt_and_release_stock_once(): void
    {
        $borrowing = $this->approve($this->submit());
        Sanctum::actingAs($this->staff);
        $this->getJson('/api/admin/borrowings/receipt?code='.$borrowing->receipt['qr_payload'])->assertOk();
        $payload = [
            'code' => $borrowing->receipt['qr_payload'], 'status' => 'Rejected', 'method' => 'qr',
            'remarks' => 'Student could not collect the equipment.',
        ];
        $this->postJson('/api/admin/borrowings/pickup', $payload)->assertOk()
            ->assertJsonPath('data.pickup_status', 'Rejected');
        $this->assertSame('qr', $borrowing->fresh()->receipt_snapshot['pickup_method']);
        $this->assertSame(5, $this->equipment->fresh()->available_quantity);
        $this->postJson('/api/admin/borrowings/pickup', $payload)->assertConflict();
        $this->assertSame(5, $this->equipment->fresh()->available_quantity);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->student->id, 'title' => 'Equipment Pickup Rejected']);
    }
}
