<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeRouteRenderingTest extends TestCase
{
    use RefreshDatabase;

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
        $user = User::factory()->create();

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

    public function test_data_history_page_renders_cleanly_without_pagination_buttons(): void
    {
        $user = User::factory()->create();

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

