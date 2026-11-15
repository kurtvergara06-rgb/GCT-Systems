<?php

namespace Tests\Feature\Account;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_replace_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->put(route('account.profile.photo.update'), [
                'avatar' => UploadedFile::fake()->image('profile.jpg', 400, 400)->size(500),
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('success', 'Profile photo updated successfully.');

        $firstPath = $user->fresh()->avatar_path;
        $this->assertNotNull($firstPath);
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($user)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('data-avatar-upload-form', false)
            ->assertSee('data-avatar-crop-modal', false)
            ->assertSee('Crop your photo')
            ->assertSee('Crop & Upload', false)
            ->assertSee($user->fresh()->profilePhotoUrl(), false);

        $this->actingAs($user)
            ->put(route('account.profile.photo.update'), [
                'avatar' => UploadedFile::fake()->image('replacement.png', 320, 320)->size(400),
            ])
            ->assertRedirect(route('account.profile'));

        $replacementPath = $user->fresh()->avatar_path;
        $this->assertNotSame($firstPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($replacementPath);
    }

    public function test_profile_photo_rejects_unsupported_files_and_oversized_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->put(route('account.profile.photo.update'), [
                'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHasErrors('avatar');

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->put(route('account.profile.photo.update'), [
                'avatar' => UploadedFile::fake()->image('large.jpg')->size(2049),
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_user_can_remove_their_profile_photo(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('existing.jpg')->store('profile-photos', 'public');
        $user = User::factory()->create(['avatar_path' => $path]);

        $this->actingAs($user)
            ->delete(route('account.profile.photo.destroy'))
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('success', 'Profile photo removed successfully.');

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_account_settings_shows_disabled_dark_mode_placeholder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Dark Mode')
            ->assertSee('Coming soon')
            ->assertSee('role="switch"', false)
            ->assertSee('aria-checked="false"', false)
            ->assertSee('disabled', false);
    }

    public function test_admin_account_management_displays_saved_profile_photos(): void
    {
        Storage::fake('public');
        $photoPath = UploadedFile::fake()->image('staff-profile.jpg')->store('profile-photos', 'public');
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $staff = User::factory()->create([
            'name' => 'Profile Photo Staff',
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
            'avatar_path' => $photoPath,
        ]);

        $photoUrl = $staff->profilePhotoUrl();

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Profile Photo Staff')
            ->assertSee('src="'.$photoUrl.'"', false)
            ->assertSee('data-avatar-url="'.$photoUrl.'"', false);
    }
}
