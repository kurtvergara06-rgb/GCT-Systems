<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationDriverAttendanceUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_attendance_displays_ddr_verified_history_without_manual_marking(): void
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
            ->assertSeeText('Automatically verified from DDR')
            ->assertDontSeeText('Record Daily Attendance')
            ->assertDontSeeText('Import Data')
            ->assertSee('data-server-filter="true"', false);
    }

    public function test_attendance_page_shows_four_date_scoped_kpi_cards(): void
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
            ->assertViewHas('present', 0)
            ->assertViewHas('assignedPresent', 0)
            ->assertViewHas('unassignedPresent', 0)
            ->assertViewHas('attendanceRecords', 0)
            ->assertSeeText('Assigned Present')
            ->assertSeeText('Unassigned Present')
            ->assertSeeText('Attendance Records');
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
        $this->assertStringNotContainsString(
            'openImportDriverAttendanceModal',
            $view
        );
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
