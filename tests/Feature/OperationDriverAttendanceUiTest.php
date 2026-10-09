<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationDriverAttendanceUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_attendance_uses_daily_attendance_as_the_primary_ui(): void
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
            ->assertSeeText('Record Daily Attendance')
            ->assertDontSeeText('Import Data')
            ->assertSee('data-server-filter="true"', false);
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
            'Active drivers from the Driver Master List load automatically',
            $batchJs
        );
    }
}
