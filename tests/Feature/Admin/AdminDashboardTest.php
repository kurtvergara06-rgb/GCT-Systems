<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\ActivityLog;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\User;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_access_redesigned_admin_dashboard_with_all_metrics(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'Head',
            'status' => 'Active',
        ]);

        // Create sample batch upload
        BatchUpload::create([
            'file_name' => 'telematics_test_batch.csv',
            'stored_name' => 'telematics_test_batch_123.csv',
            'file_path' => 'batches/telematics_test_batch_123.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'data_origin' => 'genuine',
            'status' => 'processed',
            'total_records' => 120,
            'processed_records' => 120,
            'failed_records' => 0,
            'progress_percentage' => 100,
            'uploaded_by' => $admin->id,
        ]);

        // Create sample activity log
        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'Head',
            'department' => 'Admin',
            'activity' => 'Logged into Admin Module',
            'module' => 'Authentication',
            'reference' => 'AUTH-001',
            'event_type' => 'security',
            'details' => 'User logged in successfully',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser',
        ]);

        Cache::put('admin-dashboard:ai-model-health:v4', [], 120);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewIs('Admin.admin-dashboard');

        // Render response content so View Composer runs
        $content = $response->getContent();

        // Verify key view data keys
        $response->assertViewHas('departmentDistribution');
        $response->assertViewHas('batchMetrics');
        $response->assertViewHas('recentBatches');
        $response->assertViewHas('metricsDetail');
        $response->assertViewHas('fleetMetrics');
        $response->assertViewHas('recentActivityLogs');
        $response->assertViewHas('recentUsers');

        // Verify HTML markup rendering for new sections
        $this->assertStringContainsString('admin-dashboard-main', $content);
        $this->assertStringContainsString('admin-command-metrics', $content);
        $this->assertStringNotContainsString('Cross-Department Operational Supervision', $content);
        $this->assertStringNotContainsString('admin-kpi-grid', $content);
        $this->assertStringContainsString('AI &amp; Machine Learning Health', $content);
        $this->assertStringContainsString('Action Required', $content);
        $this->assertStringContainsString('Recent Activity', $content);
        $this->assertStringContainsString('Quick Actions', $content);
        $this->assertStringNotContainsString('adminUsersTable', $content);
        $this->assertStringNotContainsString('admin-batch-panel', $content);
        $this->assertStringContainsString('telematics_test_batch.csv', $content);
    }


    public function test_admin_dashboard_shows_five_ml_cards_from_cached_model_health(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'Head',
            'status' => 'Active',
        ]);

        $statuses = collect([
            ['key' => 'eta', 'reported_sample_count' => 362],
            ['key' => 'fuel', 'reported_sample_count' => 156],
            ['key' => 'delay', 'reported_sample_count' => 500],
            ['key' => 'inventory', 'reported_sample_count' => 47872],
            ['key' => 'scheduling', 'reported_sample_count' => null],
        ])->map(fn (array $entry) => (object) array_merge([
            'state' => 'Provenance Not Verified',
            'tone' => 'not-ready',
            'sample_count' => 0,
            'count_label' => 'Verification Pending',
            'availability' => 'Service reachable',
            'reachable' => true,
            'data_source' => 'Source Not Verified',
            'dataset_type' => 'GCT operational records',
            'model_version' => '1.0.0',
            'reason' => 'Status verified by model endpoint.',
            'details' => [],
        ], $entry))->all();

        // Simulate the real cache payload: plain PHP arrays, never objects.
        Cache::put(
            'admin-dashboard:ai-model-health:v4',
            array_map(fn (object $status): array => (array) $status, $statuses),
            120
        );

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('AI &amp; Machine Learning Health', false)
            ->assertSee('ETA Prediction')
            ->assertSee('Fuel Prediction')
            ->assertSee('Delay Prediction')
            ->assertSee('Inventory Forecast')
            ->assertSee('Auto Scheduling')
            ->assertSee('Verification Pending')
            ->assertSee('Awaiting data')
            ->assertSee('No operational data yet')
            ->assertSee('Waiting for new records')
            ->assertSee('Service reachable')
            ->assertSee('Source Not Verified')
            ->assertDontSee('373 verified records')
            ->assertDontSee('47,872 verified records')
            ->assertSee('data-ml-details', false)
            ->assertSee('adminMlDetailsDialog', false)
            ->assertDontSee('65%');
    }

    public function test_empty_database_is_distinguished_from_an_unconfirmed_ml_service(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'Head', 'status' => 'Active']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Awaiting data')
            ->assertSee('No operational data yet')
            ->assertSee('ML service status not confirmed')
            ->assertDontSee('Service Unavailable')
            ->assertDontSee('Data unavailable')
            ->assertDontSee('Service reachable');
        $this->assertSame(5, substr_count($response->getContent(), '>No operational data yet</p>'));
    }

    public function test_new_records_update_the_empty_state_even_when_model_status_is_cached(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'Head', 'status' => 'Active']);
        Cache::put('admin-dashboard:ai-model-health:v4', [], 120);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Awaiting data');

        $batchId = DB::table('batch_uploads')->insertGetId([
            'file_name' => 'qa.csv', 'stored_name' => 'qa.csv', 'file_path' => 'qa.csv',
            'file_type' => 'csv', 'module' => 'Operation', 'data_type' => 'GPS Trip Records',
            'status' => 'processed', 'uploaded_by' => $admin->id,
        ]);
        DB::table('gps_trip_records')->insert(['batch_upload_id' => $batchId, 'bus_no' => 'QA-ONLY']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk()->assertSee('ML service status not confirmed')->assertDontSee('Service reachable');
        $this->assertSame(1, substr_count($response->getContent(), '>No operational data yet</p>'));
    }

    public function test_admin_dashboard_service_returns_expected_data_structure(): void
    {
        $service = app(AdminDashboardService::class);
        $data = $service->data();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('departmentDistribution', $data);
        $this->assertArrayHasKey('batchMetrics', $data);
        $this->assertArrayHasKey('fleetMetrics', $data);
        $this->assertArrayHasKey('metricsDetail', $data);
        $this->assertArrayHasKey('pendingApplications', $data);
        $this->assertArrayHasKey('activitiesToday', $data);
        $this->assertArrayHasKey('securityEventsToday', $data);
    }
}
