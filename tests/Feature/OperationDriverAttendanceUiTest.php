<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationDriverAttendanceUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_attendance_exposes_the_permissioned_recording_workflow(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('driver-attendance'))
            ->assertOk()
            ->assertSeeText('DDR-Verified Attendance History')
            ->assertDontSeeText('Record Daily Attendance')
            ->assertSee('data-server-filter="true"', false);
    }

    public function test_attendance_page_shows_ddr_verified_historical_kpis_without_daily_tabs(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('driver-attendance'))
            ->assertOk()
            ->assertViewHas('verifiedDrivers', 0)
            ->assertViewHas('verifiedTripDays', 0)
            ->assertViewHas('attendanceRecords', 0)
            ->assertSeeText('Verified Drivers')
            ->assertSeeText('Verified Service Dates')
            ->assertSeeText('Verified Attendance Records')
            ->assertDontSeeText("Today's Attendance")
            ->assertDontSeeText('Assigned Present')
            ->assertDontSeeText('Unassigned Present');
    }

    public function test_driver_attendance_filters_and_modals_follow_partial_ui_rules(): void
    {
        $view = file_get_contents(
            resource_path('views/Operation/Attendance/driver-attendance.blade.php')
        );
        $driverJs = file_get_contents(
            resource_path('js/Operation/Attendance/driver-attendance.js')
        );
        $batchJs = file_get_contents(
            resource_path('js/Operation/Attendance/batch-attendance.js')
        );

        $this->assertStringContainsString(
            'data-server-filter="true"',
            $view
        );
        $this->assertStringNotContainsString(
            'this.form.submit()',
            $view
        );
        $this->assertStringContainsString('@if($canEditOperation)', $view);
        $this->assertStringNotContainsString('openImportDriverAttendanceModal', $view);
        $this->assertStringNotContainsString('resources/js/Operation/Attendance/batch-attendance.js', $view);
        $this->assertStringContainsString('resources/js/Operation/Attendance/driver-attendance.js', $view);
        $this->assertStringContainsString('Service Date (optional)', $view);
        $this->assertStringNotContainsString('attendance-record-tabs', $view);
        $this->assertStringNotContainsString('attendanceAutoToday', $driverJs);
        $this->assertStringNotContainsString(
            'event.target === modal',
            $driverJs
        );
        $this->assertStringNotContainsString(
            'event.target === overlay',
            $batchJs
        );
        $this->assertStringContainsString(
            'All active drivers from the Driver Master List load automatically',
            $batchJs
        );
    }
}
