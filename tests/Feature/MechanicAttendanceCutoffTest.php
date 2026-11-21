<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MechanicAttendanceCutoffTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_unselected_mechanic_stays_unrecorded_until_manila_cutoff(): void
    {
        $head = User::factory()->create([
            'department' => 'Maintenance', 'role' => 'head', 'status' => 'Active',
        ]);
        Mechanic::create([
            'mechanic_id' => 'MC-CUTOFF-A',
            'mechanic_name' => 'Checked Mechanic',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);
        Mechanic::create([
            'mechanic_id' => 'MC-CUTOFF-B',
            'mechanic_name' => 'Unchecked Mechanic',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 16:59:00', 'Asia/Manila'));
        $this->actingAs($head)->postJson(route('maintenance.attendance.batch.store'), [
            'attendance_date' => '2026-11-19',
            'rows' => [[
                'person_id' => 'MC-CUTOFF-A',
                'name' => 'Checked Mechanic',
                'shift' => 'Morning',
                'time_in' => '08:00',
                'status' => 'Present',
            ]],
        ])->assertOk()->assertJsonPath('saved', 1);
        $this->assertDatabaseMissing('mechanic_attendances', ['mechanic_id' => 'MC-CUTOFF-B']);

        Artisan::call('maintenance:close-mechanic-attendance');
        $this->assertDatabaseMissing('mechanic_attendances', ['mechanic_id' => 'MC-CUTOFF-B']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 17:11:00', 'Asia/Manila'));
        Artisan::call('maintenance:close-mechanic-attendance');
        $this->assertDatabaseHas('mechanic_attendances', [
            'mechanic_id' => 'MC-CUTOFF-B', 'status' => 'Absent',
            'time_in' => null,
        ]);
        $this->assertDatabaseHas('mechanic_attendances', [
            'mechanic_id' => 'MC-CUTOFF-A', 'status' => 'Present',
        ]);
        Artisan::call('maintenance:close-mechanic-attendance');
        $this->assertSame(2, MechanicAttendance::count());
    }

    public function test_checked_only_selection_and_unmarked_availability_are_in_the_shared_modal(): void
    {
        $js = File::get(resource_path('js/Operation/Attendance/batch-attendance.js'));
        $controller = File::get(app_path('Http/Controllers/Operation/BatchAttendanceController.php'));

        $this->assertStringContainsString("row.dataset.recorded !== 'true' && row.querySelector('[data-row-select]').checked", $js);
        $this->assertStringContainsString("setSelectedStatus(row, 'Unmarked')", $js);
        $this->assertStringContainsString("'status' => " . '$' . "status ?: 'Unmarked'", $controller);
        $this->assertStringContainsString("return 'Not recorded';", $controller);
    }
}
