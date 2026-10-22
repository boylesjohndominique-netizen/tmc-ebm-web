<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('Administrator');
        $this->student = $this->user('Student', [
            'account_status' => 'Pending',
            'id_front_path' => 'ids/front.png',
            'id_back_path' => 'ids/back.png',
            'id_submitted_at' => now(),
        ]);
        Sanctum::actingAs($this->admin);
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'first_name' => $role, 'last_name' => 'Tester',
            'username' => strtolower($role), 'email' => strtolower($role).'@example.test',
            'password' => 'test-password', 'account_status' => 'Active',
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function reject(array $data = ['reason' => 'The back of your ID is blurry.'])
    {
        return $this->putJson("/api/admin/users/{$this->student->id}/reject-verification", $data);
    }

    public function test_acceptance_verifies_the_student_and_notifies_them(): void
    {
        $this->putJson("/api/admin/users/{$this->student->id}/verify")->assertOk()
            ->assertJsonPath('user.account_status', 'Active');
        $this->assertTrue($this->student->fresh()->isIdVerified());
        $this->assertEquals($this->admin->id, $this->student->fresh()->verified_by);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id, 'title' => 'Account Verified',
        ]);
    }

    public function test_rejection_keeps_mobile_access_and_delivers_the_reason_to_the_student(): void
    {
        $reason = 'The back of your ID is blurry.';
        $this->reject(['reason' => $reason])->assertOk()->assertJsonPath('user.id_rejection_reason', $reason);
        $this->assertFalse($this->student->fresh()->isIdVerified());
        Sanctum::actingAs($this->student->fresh());
        $this->getJson('/api/mobile/profile')->assertOk()->assertJsonPath('id_rejection_reason', $reason)
            ->assertJsonPath('account_status', 'Pending')->assertJsonPath('is_id_verified', false);
        $this->getJson('/api/mobile/notifications')->assertOk()
            ->assertJsonFragment(['title' => 'ID Verification Rejected'])
            ->assertJsonFragment(['message' => 'Your ID verification was rejected. Reason: '.$reason.' Please submit new front and back ID photos in Profile.']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_rejection_requires_a_nonblank_reason_of_at_most_500_characters(): void
    {
        foreach ([[], ['reason' => '   '], ['reason' => str_repeat('x', 501)]] as $invalid) {
            $this->reject($invalid)->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->assertNull($this->student->fresh()->id_rejection_reason);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_students_and_staff_without_verification_permission_cannot_review(): void
    {
        Sanctum::actingAs($this->student);
        $this->reject()->assertForbidden();
        $this->putJson("/api/admin/users/{$this->student->id}/verify")->assertForbidden();
        $staff = $this->user('Staff');
        UserPermission::create(['user_id' => $staff->id, 'module' => 'users', 'actions' => ['view']]);
        Sanctum::actingAs($staff);
        $this->reject()->assertForbidden();
        $this->putJson("/api/admin/users/{$this->student->id}/verify")->assertForbidden();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_unsubmitted_banned_and_nonstudent_accounts_cannot_be_reviewed(): void
    {
        foreach ([
            ['id_submitted_at' => null],
            ['id_front_path' => null],
            ['account_status' => 'Banned'],
            ['role_id' => $this->admin->role_id],
        ] as $attributes) {
            $original = $this->student->getAttributes();
            $this->student->update($attributes);
            $this->reject()->assertUnprocessable();
            $this->putJson("/api/admin/users/{$this->student->id}/verify")->assertUnprocessable();
            $this->student->setRawAttributes($original)->save();
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_the_same_submission_cannot_receive_multiple_decisions(): void
    {
        $this->reject()->assertOk();
        $this->reject()->assertUnprocessable();
        $this->putJson("/api/admin/users/{$this->student->id}/verify")->assertUnprocessable();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_resubmission_clears_rejection_and_can_be_accepted(): void
    {
        Storage::fake('local');
        $this->reject()->assertOk();
        Sanctum::actingAs($this->student->fresh());
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4V0AAAAASUVORK5CYII=');
        $this->postJson('/api/mobile/id-verification', [
            'id_front' => UploadedFile::fake()->createWithContent('front.png', $image),
            'id_back' => UploadedFile::fake()->createWithContent('back.png', $image),
        ])->assertCreated()->assertJsonPath('user.id_rejection_reason', null)
            ->assertJsonPath('user.is_id_verified', false);
        $this->assertNull($this->student->fresh()->id_rejection_reason);
        Storage::disk('local')->assertExists($this->student->fresh()->id_front_path);
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/users/{$this->student->id}/verify")->assertOk();
        $this->reject()->assertUnprocessable();
        $this->assertSame(2, Notification::where('user_id', $this->student->id)->count());
    }
}
