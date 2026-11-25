<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLegacyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_accounts_cannot_access_legacy_admin_import_routes(): void
    {
        $departmentUser = User::factory()->create(['department' => 'Operation', 'role' => 'head', 'status' => 'Active']);

        $this->actingAs($departmentUser)->get(route('batch-file-processing.generic.profiles'))->assertForbidden();
        $this->getJson(route('admin.import-export.activities.recent'))->assertForbidden();
        $this->postJson(route('admin.import-export.export'))->assertForbidden();
    }

    public function test_admin_bookmarks_reach_supported_pages_and_admins_can_read_transfer_activity(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);

        $this->actingAs($admin)->get(route('admin.import-export'))->assertRedirect(route('admin.data-history'));
        $this->getJson(route('admin.import-export.activities.recent'))->assertOk();
    }

    public function test_retired_analytics_overview_bookmark_redirects_to_descriptive_analytics(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);

        $this->actingAs($admin)
            ->get('/analytics/overview')
            ->assertRedirect(route('analytics.descriptive'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="#admin-ml-health"', false)
            ->assertSee('id="admin-ml-health"', false)
            ->assertDontSee('/analytics/overview');
    }
}
