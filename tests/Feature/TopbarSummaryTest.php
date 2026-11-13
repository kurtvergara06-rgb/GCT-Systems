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

    public function test_unknown_department_cannot_read_other_departments_notifications(): void
    {
        $user = User::factory()->create([
            'department' => 'Unassigned',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        TopbarNotification::create([
            'module' => 'Maintenance',
            'entity' => 'MaintenanceReferral',
            'action' => 'created',
            'record_id' => 42,
            'message' => 'Maintenance-only breakdown information.',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications')
            ->assertJsonCount(0, 'recent_activity');
    }

    public function test_operation_user_cannot_read_maintenance_notification(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        TopbarNotification::create([
            'module' => 'Maintenance',
            'entity' => 'MaintenanceReferral',
            'action' => 'created',
            'record_id' => 42,
            'message' => 'Maintenance-only breakdown information.',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
    }

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

    public function test_maintenance_user_sees_pending_referrals_and_referral_notification_in_topbar(): void
    {
        $maintenanceUser = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $notification = TopbarNotification::create([
            'module' => 'Maintenance',
            'entity' => 'MaintenanceReferral',
            'action' => 'created',
            'record_id' => 42,
            'message' => 'Breakdown incident INC-001 was referred to Maintenance.',
            'created_by' => $maintenanceUser->id,
        ]);

        $response = $this
            ->actingAs($maintenanceUser)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.url', route('maintenance-referrals', [], false))
            ->assertJsonPath('notifications.0.message', 'Breakdown incident INC-001 was referred to Maintenance.');
    }

    public function test_jo_review_is_separate_from_approved_active_work_in_topbar(): void
    {
        \App\Models\Admin\RolePermission::updateOrCreate(
            ['role_key' => 'maintenance_head'],
            [
                'label' => 'Maintenance Head',
                'department' => 'Maintenance',
                'role_type' => 'head',
                'permissions' => ['maintenance' => ['approve' => true]],
            ]
        );

        $head = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $staff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        foreach ([
            ['JO-QA-REVIEW', 'On Going', 'For Review'],
            ['JO-QA-APPROVED', 'On Going', 'Approved'],
            ['JO-QA-HOLD', 'On Hold', 'Approved'],
        ] as [$number, $status, $review]) {
            DB::table('job_orders')->insert([
                'job_order_no' => $number,
                'bus_no' => 'QA-001',
                'problem_issue' => 'Topbar QA',
                'maintenance_type' => 'Corrective',
                'status' => $status,
                'review_status' => $review,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $headActions = collect(app(\App\Services\TopbarSummaryService::class)->summary($head)['pending_actions'])
            ->pluck('count', 'label');
        $this->assertSame(1, $headActions->get('Job orders awaiting Head review'));
        $this->assertSame(1, $headActions->get('Job orders in progress'));
        $this->assertSame(1, $headActions->get('Job orders on hold'));

        $staffActions = collect(app(\App\Services\TopbarSummaryService::class)->summary($staff)['pending_actions'])
            ->pluck('count', 'label');
        $this->assertFalse($staffActions->has('Job orders awaiting Head review'));
        $this->assertSame(1, $staffActions->get('Job orders in progress'));
    }

    public function test_submitted_jo_notification_is_maintenance_only_and_not_an_approval(): void
    {
        $maintenance = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $operation = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        TopbarNotification::create([
            'module' => 'Maintenance',
            'entity' => 'JobOrder',
            'action' => 'submitted_for_review',
            'record_id' => 12,
            'message' => 'JO-2026-0012 submitted for Maintenance Head review (not yet approved).',
            'created_by' => $maintenance->id,
        ]);

        $this->actingAs($maintenance)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('notifications.0.action', 'submitted_for_review')
            ->assertJsonPath('notifications.0.message', 'JO-2026-0012 submitted for Maintenance Head review (not yet approved).');

        $this->actingAs($operation)
            ->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }
    public function test_notification_previews_are_five_and_history_is_paginated_by_department(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        foreach (range(1, 22) as $number) {
            TopbarNotification::create([
                'module' => 'Operation',
                'entity' => 'TripSchedule',
                'action' => 'updated',
                'record_id' => $number,
                'message' => "Trip update {$number}.",
                'created_by' => $user->id,
            ]);
        }
        TopbarNotification::create([
            'module' => 'Maintenance',
            'entity' => 'JobOrder',
            'action' => 'created',
            'record_id' => 99,
            'message' => 'Maintenance-only event.',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonCount(5, 'notifications')
            ->assertJsonCount(5, 'recent_activity')
            ->assertJsonPath('unread_count', 22);

        $this->actingAs($user)->getJson(route('topbar.notifications.history', ['page' => 1]))
            ->assertOk()->assertJsonCount(20, 'items')
            ->assertJsonPath('total', 22)
            ->assertJsonPath('last_page', 2);

        $this->actingAs($user)->getJson(route('topbar.notifications.history', ['page' => 2]))
            ->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('total', 22);

        $this->actingAs($user)->postJson(route('topbar.notifications.read-all'))->assertOk();
        $this->actingAs($user)->getJson(route('topbar.notifications.history', ['state' => 'unread']))
            ->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'items');
    }

    public function test_legacy_scheduler_notification_uses_professional_plural_text(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        TopbarNotification::create([
            'module' => 'Operation', 'entity' => 'TripSchedule',
            'action' => 'updated', 'record_id' => 1,
            'message' => '1 trip status(es) advanced by scheduled times.',
            'created_by' => $user->id,
        ]);
        $this->actingAs($user)->getJson(route('topbar.summary'))
            ->assertOk()
            ->assertJsonPath('notifications.0.message', 'The status of 1 trip was automatically updated based on its schedule.');
    }

    public function test_shared_topbar_has_history_and_semantic_theme_styles(): void
    {
        $blade = file_get_contents(resource_path('views/components/layout/topbar.blade.php'));
        $css = file_get_contents(resource_path('css/Main-styles/topbar.css'));
        $js = file_get_contents(resource_path('js/Main-js/topbar.js'));

        $this->assertStringContainsString('topbarHistoryDialog', $blade);
        $this->assertStringContainsString("route('topbar.notifications.history'", $blade);
        $this->assertStringContainsString('data-topbar-history="activity"', $blade);
        $this->assertStringContainsString('data-topbar-history="notifications"', $blade);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('html[data-gct-theme="dark"]', $css);
        $this->assertStringContainsString('items.slice(0, 5)', $js);
    }

    public function test_paginated_history_respects_each_of_the_five_departments(): void
    {
        $departments = [
            'Operation' => 'Operation',
            'Maintenance' => 'Maintenance',
            'Warehouse' => 'Warehouse',
            'Purchase' => 'Purchase',
        ];
        $creator = User::factory()->create([
            'department' => 'Administration',
            'role' => 'head',
            'status' => 'Active',
        ]);
        foreach ($departments as $department => $module) {
            TopbarNotification::create([
                'module' => $module, 'entity' => 'TripSchedule',
                'action' => 'created', 'record_id' => 1,
                'message' => "{$module} scoped message.",
                'created_by' => $creator->id,
            ]);
        }

        foreach ($departments as $department => $module) {
            $user = User::factory()->create([
                'department' => $department, 'role' => 'staff', 'status' => 'Active',
            ]);
            $history = $this->actingAs($user)->getJson(route('topbar.notifications.history'))
                ->assertOk()
                ->assertJsonCount(1, 'items')
                ->assertJsonPath('items.0.module', $module);
        }

        $this->actingAs($creator)->getJson(route('topbar.notifications.history'))
            ->assertOk()->assertJsonCount(4, 'items');
    }

}
