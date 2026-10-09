<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeRouteRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    public function test_trip_records_page_ignores_an_unavailable_sidebar_destination(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('trip-records'))
            ->assertOk();
    }

    public function test_batch_file_processing_page_renders_cleanly_without_pagination_buttons(): void
    {
        $user = $this->adminUser();

        $batch = \App\Models\Admin\BatchUpload::create([
            'file_name' => 'test_gps.csv',
            'stored_name' => 'test_gps.csv',
            'file_type' => 'csv',
            'file_path' => 'uploads/test_gps.csv',
            'status' => 'Processed',
            'total_records' => 2,
            'processed_records' => 2,
            'failed_records' => 0,
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
        ]);

        \App\Models\Admin\GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'bus_no' => 'GCT-101',
            'record_no' => 'REC-001',
            'grouping' => 'Talisay - SM Seaside',
            'trip_type' => 'Trip',
            'beginning_at' => now()->subHour(),
            'ending_at' => now(),
            'duration_minutes' => 60,
        ]);

        \App\Models\Admin\GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'bus_no' => 'GCT-102',
            'record_no' => 'REC-002',
            'grouping' => 'Talisay - SM Seaside',
            'trip_type' => 'Trip',
            'beginning_at' => now()->subHours(2),
            'ending_at' => now()->subHour(),
            'duration_minutes' => 60,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('batch-file-processing', ['batch_id' => $batch->id]));

        $response->assertOk();
        $response->assertSeeText('Showing 2 records');
        $response->assertDontSeeText('Page 1 of');
        $response->assertDontSee('simple-page-button');
    }

    public function test_batch_file_processing_bounds_large_batch_rendering_with_server_pagination(): void
    {
        $user = $this->adminUser();
        $batch = \App\Models\Admin\BatchUpload::create([
            'file_name' => 'large_gps.csv',
            'stored_name' => 'large_gps.csv',
            'file_type' => 'csv',
            'file_path' => 'uploads/large_gps.csv',
            'status' => 'Processed',
            'total_records' => 120,
            'processed_records' => 120,
            'failed_records' => 0,
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
        ]);

        $now = now();
        $rows = collect(range(1, 120))->map(fn (int $number): array => [
            'batch_upload_id' => $batch->id,
            'bus_no' => 'GCT-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'record_no' => 'LARGE-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'grouping' => 'Large Batch Route',
            'trip_type' => 'Trip',
            'beginning_at' => $now->copy()->addMinutes($number),
            'ending_at' => $now->copy()->addMinutes($number + 30),
            'raw_data' => json_encode(['source' => 'large-test', 'row' => $number]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        \App\Models\Admin\GpsTripRecord::query()->insert($rows);

        $response = $this
            ->actingAs($user)
            ->get(route('batch-file-processing', ['batch_id' => $batch->id]));

        $response->assertOk();
        $response->assertSeeText('Showing 1–50 of 120 records');
        $response->assertSee('simple-page-button');

        $this->assertSame(50, $response->viewData('records')->count());
        $this->assertSame(120, $response->viewData('records')->total());
        $this->assertSame(50, $response->viewData('allSelectedRecords')->count());
        $this->assertSame(120, $response->viewData('allSelectedRecords')->total());
        $this->assertFalse($response->viewData('selectedBatch')->relationLoaded('tripRecords'));
    }

    public function test_data_history_page_renders_cleanly_without_pagination_buttons(): void
    {
        $user = $this->adminUser();

        \App\Models\Admin\DataActivity::create([
            'activity_type' => 'Batch Processing',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'file_name' => 'test_history.csv',
            'status' => 'Completed',
            'total_records' => 10,
            'successful_records' => 10,
            'failed_records' => 0,
            'skipped_records' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.data-history'));

        $response->assertOk();
        $response->assertSeeText('Showing 1 record');
        $response->assertDontSee('class="pagination"');
        $response->assertDontSee('page-number');
    }
}
