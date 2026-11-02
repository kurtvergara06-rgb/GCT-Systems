<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpsPdfReviewTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_pdf_interval_keeps_fractional_minutes_and_engine_hours(): void
    {
        $batch = BatchUpload::create([
            'file_name' => 'short.pdf',
            'stored_name' => 'short.pdf',
            'file_path' => 'gps-batches/short.pdf',
            'file_type' => 'pdf',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => 'Multiple Buses',
            'status' => 'In Review',
        ]);
        $record = GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'record_no' => 'NBI-5231-1.7245',
            'bus_no' => 'NBI-5231',
            'total_minutes' => 0.0333,
            'idling_minutes' => 0.0333,
            'in_motion_minutes' => 0,
            'engine_hours' => 0.000556,
            'mileage_km' => 0,
        ])->fresh();

        $this->assertEqualsWithDelta(0.0333, (float) $record->total_minutes, 0.00005);
        $this->assertSame('0.000556', $record->engine_hours);
        $this->assertSame('0.00', $record->mileage_km);
    }

    public function test_review_table_shows_original_pdf_values_and_flags_missing_source_values(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $batch = BatchUpload::create([
            'file_name' => 'engine-hours.pdf',
            'stored_name' => 'engine-hours.pdf',
            'file_path' => 'gps-batches/engine-hours.pdf',
            'file_type' => 'pdf',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => 'Multiple Buses',
            'uploaded_by' => $admin->id,
            'status' => 'In Review',
            'total_records' => 1,
            'processed_records' => 1,
            'failed_records' => 0,
        ]);
        GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'record_no' => 'NGA-6363-1.1',
            'bus_no' => 'NGA-6363',
            'grouping' => '09-01-2026 04:05:45',
            'beginning_at' => '2026-09-01 04:05:45',
            'initial_location' => 'Lipa-Rosario Rd, Padre Garcia',
            'ending_at' => '2026-09-01 08:21:13',
            'final_location' => 'San Pedro Rd, Malvar',
            'engine_hours' => 4.26,
            'total_minutes' => 255,
            'in_motion_minutes' => 104,
            'idling_minutes' => 150,
            'mileage_km' => null,
            'raw_data' => [
                'source_values' => [
                    'engine_hours' => '4:15:28',
                    'total_time' => '4:15:28',
                    'in_motion' => '1:44:31',
                    'idling' => '2:30:57',
                    'mileage' => '-----',
                ],
                'source_warnings' => ['The source row has missing mileage.'],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('batch-file-processing', ['batch_id' => $batch->id]))
            ->assertOk()
            ->assertSee('Lipa-Rosario Rd, Padre Garcia')
            ->assertSee('San Pedro Rd, Malvar')
            ->assertSee('4:15:28')
            ->assertSee('1:44:31')
            ->assertSee('-----')
            ->assertSee('Needs Review');
    }

    public function test_confirming_engine_activity_keeps_bus_route_and_odometer_values(): void
    {
        $admin = User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $bus = Bus::create([
            'bus_no' => 'NGA-6363',
            'status' => 'Active',
            'route_grouping' => 'Lipa Route',
            'latest_gps_km' => 12000,
        ]);
        $batch = BatchUpload::create([
            'file_name' => 'engine-hours.pdf',
            'stored_name' => 'engine-hours.pdf',
            'file_path' => 'gps-batches/engine-hours.pdf',
            'file_type' => 'pdf',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => 'Multiple Buses',
            'uploaded_by' => $admin->id,
            'status' => 'In Review',
        ]);
        GpsTripRecord::create([
            'batch_upload_id' => $batch->id,
            'record_no' => 'NGA-6363-1.1',
            'bus_no' => 'NGA-6363',
            'grouping' => '09-01-2026 04:05:45',
            'trip_type' => 'Engine Activity',
            'beginning_at' => '2026-09-01 04:05:45',
            'ending_at' => '2026-09-01 08:21:13',
            'mileage_km' => 50,
            'source_format' => 'GPS Engine Hours Report',
        ]);

        $this->actingAs($admin)
            ->patch(route('batch-file-processing.confirm', $batch))
            ->assertRedirect();

        $this->assertSame('Processed', $batch->fresh()->status);
        $this->assertSame('Lipa Route', $bus->fresh()->route_grouping);
        $this->assertSame(12000.0, (float) $bus->fresh()->latest_gps_km);
    }
}
