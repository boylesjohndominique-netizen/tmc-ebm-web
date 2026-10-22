<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function user(string $role = 'Staff'): User
    {
        $name = strtolower($role).User::count();

        return User::create([
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
            'first_name' => $role, 'last_name' => 'Tester',
            'username' => $name, 'email' => $name.'@example.test',
            'password' => 'test-password', 'account_status' => 'Active',
            'email_verified_at' => now(),
        ]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4V0AAAAASUVORK5CYII='
        ));
    }

    public function test_all_website_roles_can_set_their_own_photo_and_load_it_again(): void
    {
        foreach (['Administrator', 'Staff', 'Faculty'] as $role) {
            $user = $this->user($role);
            Sanctum::actingAs($user);
            $path = $this->postJson('/api/profile/avatar', ['avatar' => $this->image()])
                ->assertOk()->assertJsonPath('user.id', $user->id)->json('user.profile_photo_path');
            $this->assertNotEmpty($path);
            $this->assertSame($path, $user->fresh()->profile_photo_path);
            Storage::disk('local')->assertExists($path);
            Sanctum::actingAs($user->fresh());
            $this->getJson('/api/profile')->assertOk()->assertJsonPath('profile_photo_path', $path);
            $this->getJson('/api/me')->assertOk()->assertJsonPath('profile_photo_path', $path);
            $this->get('/api/user-avatar/'.basename($path))->assertOk()->assertHeader('Content-Type', 'image/png');
        }
    }

    public function test_replacing_a_photo_removes_the_previous_image(): void
    {
        $user = $this->user();
        Storage::disk('local')->put('user-avatars/old.png', 'old');
        $user->update(['profile_photo_path' => 'user-avatars/old.png']);
        Sanctum::actingAs($user);
        $path = $this->postJson('/api/profile/avatar', ['avatar' => $this->image()])
            ->assertOk()->json('user.profile_photo_path');
        Storage::disk('local')->assertMissing('user-avatars/old.png');
        Storage::disk('local')->assertExists($path);
    }

    public function test_invalid_uploads_preserve_the_existing_photo(): void
    {
        $user = $this->user();
        Storage::disk('local')->put('user-avatars/old.png', 'old');
        $user->update(['profile_photo_path' => 'user-avatars/old.png']);
        Sanctum::actingAs($user);
        foreach ([
            [],
            ['avatar' => UploadedFile::fake()->createWithContent('notes.txt', 'not an image')],
            ['avatar' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
            ['avatar' => UploadedFile::fake()->create('large.png', 5121, 'image/png')],
        ] as $invalid) {
            $this->postJson('/api/profile/avatar', $invalid)->assertUnprocessable()->assertJsonValidationErrors('avatar');
            $this->assertSame('user-avatars/old.png', $user->fresh()->profile_photo_path);
            Storage::disk('local')->assertExists('user-avatars/old.png');
        }
    }

    public function test_uploading_or_removing_a_photo_cannot_change_another_account(): void
    {
        $user = $this->user();
        $other = $this->user();
        $other->update(['profile_photo_path' => 'user-avatars/other.png']);
        Storage::disk('local')->put('user-avatars/other.png', 'other');
        Sanctum::actingAs($user);
        $path = $this->postJson('/api/profile/avatar', ['avatar' => $this->image(), 'user_id' => $other->id])
            ->assertOk()->assertJsonPath('user.id', $user->id)->json('user.profile_photo_path');
        Sanctum::actingAs($user->fresh());
        $this->deleteJson('/api/profile/avatar', ['user_id' => $other->id])->assertOk()
            ->assertJsonPath('user.profile_photo_path', null);
        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame('user-avatars/other.png', $other->fresh()->profile_photo_path);
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists('user-avatars/other.png');
    }

    public function test_an_account_without_a_photo_can_remove_it_safely(): void
    {
        Sanctum::actingAs($this->user());
        $this->deleteJson('/api/profile/avatar')->assertOk()->assertJsonPath('user.profile_photo_path', null);
    }

    public function test_avatar_changes_require_authentication(): void
    {
        $this->postJson('/api/profile/avatar', ['avatar' => $this->image()])->assertUnauthorized();
        $this->deleteJson('/api/profile/avatar')->assertUnauthorized();
    }

    public function test_mobile_avatar_upload_keeps_its_existing_response_contract(): void
    {
        $user = $this->user('Student');
        Sanctum::actingAs($user);
        $this->postJson('/api/mobile/profile/avatar', ['avatar' => $this->image()])->assertOk()
            ->assertJsonPath('message', 'Profile picture updated.')
            ->assertJsonPath('user.id', $user->id)->assertJsonPath('user.is_id_verified', false);
    }
}
