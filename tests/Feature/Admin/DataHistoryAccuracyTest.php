<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\BatchUpload;
use App\Models\Admin\DataActivity;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataHistoryAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_processed_files_count_tracks_processed_batches_not_completed_log_events(): void
    {
        $admin = User::factory()->create(['department' => 'Admin', 'role' => 'head', 'status' => 'Active']);
        $batch = BatchUpload::query()->create([
            'file_name' => 'gps-processed.csv',
            'stored_name' => 'gps-processed.csv',
            'file_path' => 'gps-batches/gps-processed.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'data_origin' => 'unknown',
            'status' => 'Processed',
            'uploaded_by' => $admin->id,
        ]);

        foreach ([1, 2] as $index) {
            DataActivity::query()->create([
                'activity_type' => 'Batch Processing',
                'module' => 'Operation',
                'data_type' => 'GPS Trip Records',
                'file_name' => 'gps-processed.csv',
                'source' => 'GPS',
                'status' => 'Completed',
                'total_records' => 1,
                'successful_records' => 1,
                'failed_records' => 0,
                'skipped_records' => 0,
                'processed_by' => $admin->id,
                'reference_type' => 'batch_upload',
                'reference_id' => $batch->id,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.data-history'));
        $response->assertOk();
        $this->assertSame(
            DataActivity::query()->where('activity_type', 'Batch Processing')->where('status', 'Completed')->count(),
            $response->viewData('stats')['successful']
        );
        $this->assertSame(1, $response->viewData('stats')['processed_files']);
    }
}
