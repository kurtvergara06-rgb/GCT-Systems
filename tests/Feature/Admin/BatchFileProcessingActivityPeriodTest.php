<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\BatchFileProcessingController;
use App\Models\Admin\GpsTripRecord;
use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BatchFileProcessingActivityPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_activity_period_record_is_preserved_and_flagged_for_review(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create());

        $records = [
            [
                'Record No.' => 'R-001',
                'Bus No.' => 'GCT-101',
                'Grouping' => 'Talisay - SM Seaside',
                'Trip Type' => 'Trip',
                'Beginning' => '2026-09-10 05:30:00',
                'Initial Location' => 'Talisay',
                'End' => '2026-09-10 19:30:00',
                'Final Location' => 'SM Seaside',
                'Duration' => '14:00:00',
                'Total Time' => '14:00:00',
                'In Motion' => '03:00:00',
                'Idling' => '04:06:00',
                'Mileage' => '101',
            ],
            [
                'Record No.' => 'R-002',
                'Bus No.' => 'GCT-101',
                'Grouping' => 'Talisay - SM Seaside',
                'Trip Type' => 'Trip',
                'Beginning' => '2026-09-11 13:39:00',
                'Initial Location' => 'Talisay',
                'End' => '2026-09-11 14:25:00',
                'Final Location' => 'SM Seaside',
                'Duration' => '00:46:00',
                'Total Time' => '00:50:00',
                'In Motion' => '00:43:00',
                'Idling' => '00:03:00',
                'Mileage' => '18',
            ],
        ];

        $file = UploadedFile::fake()->createWithContent(
            'gps.json',
            json_encode(['records' => $records])
        );

        $this->post(route('batch-file-processing.upload'), [
            'gps_file' => $file,
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
        ])->assertRedirect();

        // Both records are stored - long activity periods are preserved,
        // never rejected or deleted.
        $saved = GpsTripRecord::query()->orderBy('id')->get();
        $this->assertCount(2, $saved);

        $long = $saved->firstWhere('duration_minutes', 840);
        $normal = $saved->firstWhere('duration_minutes', 46);

        $this->assertNotNull($long, 'long activity record must be preserved');
        $this->assertSame(840, (int) $long->duration_minutes);
        $this->assertSame(840, (int) $long->total_minutes);
        $this->assertSame('GCT-101', (string) $long->bus_no);

        // The activity-period warning is stored in raw_data (non-destructive).
        $rawLong = $long->raw_data ?? [];
        $this->assertArrayHasKey('activity_period_warning', $rawLong);
        $this->assertStringContainsString(
            'vehicle activity period',
            strtolower((string) $rawLong['activity_period_warning'])
        );
        $this->assertStringContainsString(
            'operations review',
            strtolower((string) $rawLong['activity_period_warning'])
        );

        // The batch is still in review (the record was not rejected).
        $this->assertSame('In Review', (string) $long->batchUpload->status);

        // A normal trip record is unaffected and has no such warning.
        $this->assertNotNull($normal, 'normal record must be preserved');
        $this->assertSame(46, (int) $normal->duration_minutes);
        $this->assertArrayNotHasKey(
            'activity_period_warning',
            $normal->raw_data ?? []
        );
    }

    public function test_normal_gps_records_are_stored_without_warning(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create());

        $records = [[
            'Record No.' => 'R-100',
            'Bus No.' => 'GCT-102',
            'Grouping' => 'Ayala Center - IT Park',
            'Trip Type' => 'Trip',
            'Beginning' => '2026-09-12 08:00:00',
            'Initial Location' => 'Ayala',
            'End' => '2026-09-12 08:30:00',
            'Final Location' => 'IT Park',
            'Duration' => '00:30:00',
            'Total Time' => '00:32:00',
            'In Motion' => '00:28:00',
            'Idling' => '00:02:00',
            'Mileage' => '8',
        ]];

        $file = UploadedFile::fake()->createWithContent(
            'gps.json',
            json_encode(['records' => $records])
        );

        $this->post(route('batch-file-processing.upload'), [
            'gps_file' => $file,
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
        ])->assertRedirect();

        $saved = GpsTripRecord::query()->get();
        $this->assertCount(1, $saved);

        $record = $saved->first();
        $this->assertSame(30, (int) $record->duration_minutes);
        $this->assertSame(32, (int) $record->total_minutes);
        $this->assertArrayNotHasKey(
            'activity_period_warning',
            $record->raw_data ?? []
        );
    }

    public function test_duration_to_minutes_parses_clock_times_and_units_precisely(): void
    {
        $controller = app(BatchFileProcessingController::class);

        $durationToMinutes = function ($value) use ($controller) {
            $method = new \ReflectionMethod($controller, 'durationToMinutes');
            $method->setAccessible(true);

            return $method->invoke($controller, $value);
        };

        // HH:MM:SS durations keep their seconds as a fraction of a minute.
        $this->assertEqualsWithDelta(427.6, $durationToMinutes('07:07:36'), 1e-9);
        $this->assertEqualsWithDelta(840.95, $durationToMinutes('14:00:57'), 1e-9);
        $this->assertEqualsWithDelta(427.0, $durationToMinutes('07:07'), 1e-9);

        // Explicit unit-ed values, including decimal hours.
        $this->assertEqualsWithDelta(510.0, $durationToMinutes('8.5 hours'), 1e-9);
        $this->assertEqualsWithDelta(90.0, $durationToMinutes('1 hr 30 min'), 1e-9);
        $this->assertEqualsWithDelta(45.0, $durationToMinutes('45 mins'), 1e-9);

        // Bare numerics are treated as minutes.
        $this->assertEqualsWithDelta(30.0, $durationToMinutes('30'), 1e-9);

        // Empty / null inputs yield null.
        $this->assertNull($durationToMinutes(''));
        $this->assertNull($durationToMinutes(null));
    }
}