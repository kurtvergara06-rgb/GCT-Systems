<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\ActivityLog;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityLogProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_logs_show_user_profile_photo_when_available(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/system-admin.jpg', 'avatar');

        $admin = User::factory()->create([
            'name' => 'System Administrator',
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'avatar_path' => 'avatars/system-admin.jpg',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'System Admin',
            'department' => 'Admin',
            'activity' => 'Updated status of Account',
            'module' => 'Admin',
            'reference' => '10',
            'event_type' => 'Updated',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs'))
            ->assertOk()
            ->assertSee('activity-user-avatar-image', false)
            ->assertSee('avatars/system-admin.jpg', false);
    }

    public function test_activity_logs_fall_back_to_initials_when_profile_is_unavailable(): void
    {
        $admin = User::factory()->create([
            'name' => 'System Administrator',
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'avatar_path' => null,
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'System Admin',
            'department' => 'Admin',
            'activity' => 'Logged into FROMS',
            'module' => 'Admin',
            'event_type' => 'Login',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs'))
            ->assertOk()
            ->assertSee('SA');
    }
}
