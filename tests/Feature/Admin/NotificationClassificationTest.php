<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\TopbarNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_counters_match_filtered_categories_with_priority(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);

        foreach ([
            ['action' => 'security_completed', 'message' => 'Authentication security review completed'],
            ['action' => 'updated', 'message' => 'Low stock notice from Warehouse'],
            ['action' => 'created', 'message' => 'A record was created successfully'],
            ['action' => 'updated', 'message' => 'System configuration refreshed'],
        ] as $entry) {
            TopbarNotification::query()->create($entry + [
                'module' => 'Admin',
                'entity' => 'System',
                'record_id' => null,
                'created_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertViewHas('criticalAlerts', 1)
            ->assertViewHas('systemUpdates', 3)
            ->assertViewHas('totalNotifications', 4);

        foreach (['Critical', 'Warning', 'Success', 'Update'] as $type) {
            $response = $this->actingAs($admin)
                ->get(route('admin.notifications', ['type' => $type]));
            $response->assertOk();
            $this->assertSame(1, $response->viewData('notifications')->total(), $type);
            $this->assertSame($type, $response->viewData('notifications')->items()[0]['type']);
        }
    }
}
