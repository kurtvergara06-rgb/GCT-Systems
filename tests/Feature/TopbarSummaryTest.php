<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\TopbarNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_read_and_clear_topbar_notifications(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        TopbarNotification::create([
            'module' => 'Operation',
            'entity' => 'TripSchedule',
            'action' => 'created',
            'record_id' => 1,
            'message' => 'A trip schedule was created.',
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath(
                'notifications.0.message',
                'A trip schedule was created.'
            );

        $this
            ->actingAs($user)
            ->postJson(route('topbar.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this
            ->actingAs($user)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
