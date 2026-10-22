<?php

namespace Tests\Feature;

use App\Models\BorrowingRequest;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReturnHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    private User $staff;

    private Equipment $equipment;

    private BorrowingRequest $borrowing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(1, 0));
        Mail::fake();
        $this->student = $this->user('Student', 'student');
        $this->admin = $this->user('Administrator', 'admin');
        $this->staff = $this->user('Staff', 'staff');
        $category = Category::create(['name' => 'AV']);
        $this->equipment = Equipment::create([
            'category_id' => $category->id, 'name' => 'Projector',
            'description' => 'Projector, HDMI cable and remote',
            'quantity' => 5, 'available_quantity' => 3, 'condition' => 'Good', 'status' => 'Available',
        ]);
        $this->borrowing = BorrowingRequest::create([
            'user_id' => $this->student->id, 'status' => 'Approved', 'pickup_status' => 'Received',
            'borrowing_date' => '2026-10-22', 'expected_return_date' => '2026-10-24',
            'receipt_snapshot' => [
                'borrower' => ['name' => 'Student Tester', 'id_number' => '24-123456'],
                'borrowing_date' => '2026-10-22', 'expected_return_date' => '2026-10-24',
                'items' => [['equipment_id' => $this->equipment->id, 'name' => 'Projector', 'quantity' => 2]],
            ],
        ]);
        $this->borrowing->items()->create(['equipment_id' => $this->equipment->id, 'quantity' => 2]);
    }

    private function user(string $role, string $name): User
    {
        return User::create([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'first_name' => ucfirst($name), 'last_name' => 'Tester',
            'id_number' => '24-123456', 'username' => $name,
            'email' => $name.'@example.test', 'password' => 'test-password',
            'account_status' => 'Active', 'verified_at' => now(),
        ]);
    }

    private function submit(): ReturnRequest
    {
        Sanctum::actingAs($this->student);
        $response = $this->postJson("/api/mobile/borrowings/{$this->borrowing->id}/return", [
            'remarks' => 'Finished class presentation', 'status' => 'Confirmed',
            'condition_on_return' => 'Good', 'returned_at' => now()->toISOString(),
        ])->assertCreated()->assertJsonPath('data.status', 'Pending')->assertJsonPath('data.returned_at', null);

        return ReturnRequest::findOrFail($response->json('data.id'));
    }

    private function approve(ReturnRequest $return, int $windowHours = 3): ReturnRequest
    {
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/returns/{$return->id}/process", [
            'status' => 'Approved', 'remarks' => 'Bring all accessories to AV office',
            'handover_starts_at' => now()->addHour()->toISOString(),
            'handover_ends_at' => now()->addHours($windowHours)->toISOString(),
        ])->assertOk()->assertJsonPath('data.handover_status', 'Awaiting');

        return $return->fresh();
    }

    private function confirmation(ReturnRequest $return, bool $qr = false): array
    {
        return [
            'code' => $qr ? $return->receipt['qr_payload'] : $return->transaction_code,
            'status' => 'Accepted', 'method' => $qr ? 'qr' : 'manual',
            'equipment_checked' => true,
            'condition_on_return' => 'Good',
            'overdue_fee_paid' => false, 'quoted_overdue_fee' => 0,
        ];
    }

    public function test_approval_issues_a_separate_receipt_without_restocking_or_completing_the_loan(): void
    {
        $return = $this->approve($this->submit());
        $this->assertMatchesRegularExpression('/^\d{12}$/', $return->transaction_code);
        $this->assertNotSame($this->borrowing->transaction_code, $return->transaction_code);
        $this->assertNotSame($this->borrowing->qr_token, $return->qr_token);
        $this->assertSame('TMCEBMS:RETURN:'.$return->qr_token, $return->receipt['qr_payload']);
        $this->assertSame('return', $return->receipt['kind']);
        $this->assertSame('Projector', $return->receipt['items'][0]['name']);
        $this->assertSame('24-123456', $return->receipt['borrower']['id_number']);
        $this->assertSame('Finished class presentation', $return->receipt['borrower_remarks']);
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
        $this->assertSame('Approved', $this->borrowing->fresh()->status);
        Sanctum::actingAs($this->student);
        $this->postJson("/api/mobile/borrowings/{$this->borrowing->id}/return")->assertUnprocessable();
        $this->getJson('/api/mobile/borrowings')->assertOk()
            ->assertJsonPath('0.returns.0.receipt.transaction_code', $return->transaction_code)
            ->assertJsonMissingPath('0.returns.0.qr_token');
        $this->assertDatabaseHas('notifications', ['user_id' => $this->student->id, 'title' => 'Return Request Approved']);
    }

    public function test_staff_inspection_waits_for_admin_verification_before_restocking(): void
    {
        $return = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $this->getJson('/api/admin/returns/receipt?code='.$return->transaction_code)
            ->assertOk()->assertJsonPath('data.kind', 'return');
        $this->postJson('/api/admin/returns/handover', $this->confirmation($return))
            ->assertOk()->assertJsonPath('data.status', 'Awaiting Verification')
            ->assertJsonPath('data.verified_student_id', '24-123456')
            ->assertJsonPath('data.handover_staff_name', 'Staff Tester');
        $this->postJson('/api/admin/returns/handover', $this->confirmation($return))->assertConflict();
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
        $this->assertSame('Approved', $this->borrowing->fresh()->status);
        $this->assertNull($return->fresh()->returned_at);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'title' => 'Return Inspection Awaiting Verification']);
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])->assertOk();
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])->assertConflict();
        $this->assertSame(5, $this->equipment->fresh()->available_quantity);
        $this->assertSame('Completed', $this->borrowing->fresh()->status);
        $this->assertSame($this->admin->id, $return->fresh()->processed_by);
        $this->assertSame($this->staff->id, $return->fresh()->handover_processed_by);
        $this->assertNotNull($return->fresh()->returned_at);
        $this->assertSame($this->admin->id, $return->fresh()->admin_verified_by);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->student->id, 'title' => 'Equipment Return Verified']);
    }

    public function test_return_qr_requires_the_right_window_and_cannot_be_replayed(): void
    {
        $return = $this->approve($this->submit());
        Sanctum::actingAs($this->staff);
        $payload = $this->confirmation($return, true);
        $this->postJson('/api/admin/returns/handover', $payload)->assertConflict();
        $this->travel(2)->hours();
        $this->postJson('/api/admin/returns/handover', $payload)->assertOk();
        $this->postJson('/api/admin/returns/handover', $payload)->assertConflict();
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_expired_receipts_and_wrong_qr_types_cannot_confirm_returns(): void
    {
        $return = $this->approve($this->submit());
        $this->travel(4)->hours();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', $this->confirmation($return, true))->assertConflict();
        $this->postJson('/api/admin/returns/handover', array_replace($this->confirmation($return, true), [
            'code' => 'TMCEBMS:'.$this->borrowing->qr_token,
        ]))->assertUnprocessable();
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_uninspected_or_damaged_equipment_cannot_be_accepted(): void
    {
        $return = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        foreach ([['equipment_checked' => false], ['condition_on_return' => 'Damaged']] as $invalid) {
            $this->postJson('/api/admin/returns/handover', array_replace($this->confirmation($return), $invalid))->assertUnprocessable();
        }
        $this->assertSame('Approved', $return->fresh()->status);
        $this->assertSame('Approved', $this->borrowing->fresh()->status);
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_acceptance_uses_the_student_id_already_on_the_receipt(): void
    {
        $return = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', array_replace($this->confirmation($return), [
            'student_id_number' => 'WRONG-ID',
        ]))->assertOk()->assertJsonPath('data.verified_student_id', '24-123456');
    }

    public function test_damaged_return_rejection_keeps_loan_open_and_reissued_receipts_are_unique(): void
    {
        $return = $this->approve($this->submit());
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', [
            'code' => $return->transaction_code, 'status' => 'Rejected', 'method' => 'manual',
            'condition_on_return' => 'Damaged', 'remarks' => 'Cracked lens; equipment was not accepted.',
            'condition_notes' => 'Cracked lens', 'quoted_overdue_fee' => 0, 'overdue_fee_paid' => false,
        ])->assertOk()->assertJsonPath('data.handover_status', 'Rejected');
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
        $this->assertSame('Approved', $this->borrowing->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'title' => 'Return Inspection Awaiting Verification']);
        Sanctum::actingAs($this->student);
        $this->postJson("/api/mobile/borrowings/{$this->borrowing->id}/return")->assertUnprocessable();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])->assertUnprocessable();
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Rejected', 'remarks' => 'Verified cracked lens and staff rejection.'])->assertOk();
        $replacement = $this->approve($this->submit());
        $this->assertNotSame($return->transaction_code, $replacement->transaction_code);
        $this->assertNotSame($return->qr_token, $replacement->qr_token);
        Sanctum::actingAs($this->student);
        $this->getJson('/api/mobile/borrowings')->assertOk()->assertJsonCount(2, '0.returns')
            ->assertJsonPath('0.returns.0.id', $replacement->id);
    }

    public function test_student_cannot_confirm_and_admin_review_cannot_bypass_staff_inspection(): void
    {
        $return = $this->submit();
        Sanctum::actingAs($this->staff);
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Approved'])->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])->assertConflict();
        $return = $this->approve($return);
        Sanctum::actingAs($this->student);
        $this->postJson('/api/admin/returns/handover', $this->confirmation($return))->assertForbidden();
        $this->getJson('/api/admin/returns/receipt?code='.$return->transaction_code)->assertForbidden();
        $other = $this->user('Student', 'other');
        Sanctum::actingAs($other);
        $this->postJson("/api/mobile/borrowings/{$this->borrowing->id}/return")->assertForbidden();
        $this->getJson('/api/mobile/borrowings')->assertOk()->assertExactJson([]);
        $this->staff->permissions()->create(['module' => 'returns', 'actions' => ['view']]);
        $this->staff->unsetRelation('permissions');
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', $this->confirmation($return))->assertForbidden();
    }

    public function test_overdue_fee_uses_staff_receipt_time_instead_of_student_submission_time(): void
    {
        Setting::set(Setting::OVERDUE_FEE_PER_DAY, 7.5);
        $return = $this->approve($this->submit(), 144);
        $this->travel(5)->days();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', array_replace($this->confirmation($return), ['quoted_overdue_fee' => 22.5, 'overdue_fee_paid' => true]))
            ->assertOk()->assertJsonPath('data.overdue_days', 3)->assertJsonPath('data.overdue_fee', 22.5);
        $this->travel(2)->days();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])
            ->assertOk()->assertJsonPath('data.overdue_fee', 22.5);
        $this->assertSame('2026-10-27', $return->fresh()->returned_at->toDateString());
    }

    public function test_unpaid_fee_can_be_recorded_as_rejected_and_verified_by_admin(): void
    {
        Setting::set(Setting::OVERDUE_FEE_PER_DAY, 10);
        $return = $this->approve($this->submit(), 144);
        $this->travel(3)->days();
        Sanctum::actingAs($this->staff);
        $this->getJson('/api/admin/returns/receipt?code='.$return->transaction_code)
            ->assertOk()->assertJsonPath('data.fee_quote.overdue_fee', 10)->assertJsonPath('data.fee_quote.overdue_days', 1);
        $payload = array_replace($this->confirmation($return), ['quoted_overdue_fee' => 10]);
        $this->postJson('/api/admin/returns/handover', $payload)->assertUnprocessable();
        $this->postJson('/api/admin/returns/handover', array_replace($payload, ['status' => 'Rejected', 'remarks' => 'Overdue fee not paid']))
            ->assertOk()->assertJsonPath('data.payment_status', 'Not paid')->assertJsonPath('data.condition_on_return', 'Good');
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])->assertUnprocessable();
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Rejected', 'remarks' => 'Verified unpaid fee.'])->assertOk();
        $this->assertSame('Approved', $this->borrowing->fresh()->status);
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_staff_must_supply_condition_notes_and_payment_answer(): void
    {
        $return = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $payload = array_replace($this->confirmation($return), ['status' => 'Rejected', 'condition_on_return' => 'Damaged', 'remarks' => 'Broken equipment']);
        $this->postJson('/api/admin/returns/handover', $payload)->assertUnprocessable();
        $payload['condition_notes'] = 'The projector lens is cracked.';
        unset($payload['overdue_fee_paid']);
        $this->postJson('/api/admin/returns/handover', $payload)->assertUnprocessable();
        $this->assertSame('Approved', $return->fresh()->status);
    }

    public function test_changed_fee_quote_requires_staff_to_check_payment_again(): void
    {
        Setting::set(Setting::OVERDUE_FEE_PER_DAY, 10);
        $return = $this->approve($this->submit(), 144);
        $this->travel(3)->days();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', array_replace($this->confirmation($return), ['overdue_fee_paid' => true]))->assertConflict();
        $this->assertSame('Approved', $return->fresh()->status);
        $this->assertSame(3, $this->equipment->fresh()->available_quantity);
    }

    public function test_staff_cannot_perform_final_admin_verification(): void
    {
        $return = $this->approve($this->submit());
        $this->travel(2)->hours();
        Sanctum::actingAs($this->staff);
        $this->postJson('/api/admin/returns/handover', $this->confirmation($return))->assertOk();
        $this->putJson("/api/admin/returns/{$return->id}/process", ['status' => 'Confirmed'])->assertForbidden();
        $this->assertSame('Awaiting Verification', $return->fresh()->status);
    }
}
