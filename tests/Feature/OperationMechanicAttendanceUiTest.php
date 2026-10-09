<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationMechanicAttendanceUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mechanic_attendance_uses_daily_attendance_as_the_primary_ui(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('mechanic-attendance'))
            ->assertOk()
            ->assertSeeText('Record Daily Attendance')
            ->assertDontSeeText('Import Data')
            ->assertSee('data-server-filter="true"', false);
    }

    public function test_mechanic_attendance_filters_and_modals_follow_partial_ui_rules(): void
    {
        $view = file_get_contents(
            resource_path('views/Operation/Attendance/mechanic-attendance.blade.php')
        );
        $mechanicJs = file_get_contents(
            resource_path('js/Operation/Attendance/mechanic-attendance.js')
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
            'openImportAttendanceModal',
            $view
        );
        $this->assertStringNotContainsString(
            'event.target === modal',
            $mechanicJs
        );
        $this->assertStringContainsString(
            'Active mechanics from the Mechanic Master List load automatically',
            $batchJs
        );
        $this->assertStringNotContainsString(
            'id="batchReload"',
            $batchJs
        );
    }
}
