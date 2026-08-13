<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\TopbarNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_marking_one_notification_read_does_not_mark_other_notifications_read(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $first = TopbarNotification::create([
            'module' => 'Operation',
            'entity' => 'TripSchedule',
            'action' => 'created',
            'record_id' => 1,
            'message' => 'First schedule notification.',
            'created_by' => $user->id,
        ]);

        $second = TopbarNotification::create([
            'module' => 'Operation',
            'entity' => 'Attendance',
            'action' => 'updated',
            'record_id' => 2,
            'message' => 'Second attendance notification.',
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->postJson(route('admin.notifications.read', $first))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this
            ->actingAs($user)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertDatabaseHas('topbar_notification_reads', [
            'user_id' => $user->id,
            'notification_id' => $first->id,
        ]);

        $this->assertDatabaseMissing('topbar_notification_reads', [
            'user_id' => $user->id,
            'notification_id' => $second->id,
        ]);

        $this
            ->actingAs($user)
            ->postJson(route('topbar.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(
            0,
            DB::table('topbar_notification_reads')
                ->where('user_id', $user->id)
                ->count()
        );
    }
}
