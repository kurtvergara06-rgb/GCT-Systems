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

    public function test_legacy_today_tab_redirects_to_same_ddr_verified_history_view(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        foreach (['today', 'history'] as $tab) {
            $this->actingAs($user)
                ->get(route('driver-attendance', ['tab' => $tab]))
                ->assertOk()
                ->assertSeeText('DDR-Verified Attendance History')
                ->assertSeeText('Service Date (optional)')
                ->assertDontSeeText("Today's Attendance")
                ->assertViewHas('verifiedDrivers', 0);
        }

        $this->actingAs($user)
            ->get(route('driver-attendance', ['attendance_date' => '2026-11-01']))
            ->assertOk()
            ->assertViewHas('summaryDate', '2026-11-01');
    }

    public function test_verified_attendance_groups_trip_date_and_schedule_and_labels_view_action(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Attendance/driver-attendance.blade.php'));
        $styles = file_get_contents(resource_path('css/Operation/Attendance/driver-attendance.css'));

        $this->assertStringContainsString('<th>Scheduled Date &amp; Time</th>', $view);
        $this->assertStringContainsString('class="attendance-trip-datetime"', $view);
        $this->assertStringContainsString('class="attendance-trip-date"', $view);
        $this->assertStringContainsString('class="attendance-trip-time"', $view);
        $this->assertStringNotContainsString('class="attendance-assignment-schedule"', $view);
        $this->assertStringContainsString('$attendance->attendance_date?->format(\'M d, Y\')', $view);
        $this->assertStringContainsString('$primaryTrip->departureDateTime()->format(\'g:i A\')', $view);
        $this->assertStringContainsString('$primaryTrip->estimatedArrivalDateTime()->format(\'g:i A\')', $view);
        $this->assertStringNotContainsString('<th>Time-in</th>', $view);
        $this->assertStringNotContainsString("'time_in' => 'Time In'", $view);
        $this->assertStringContainsString('colspan="6"', $view);
        $this->assertStringContainsString('data-time-in=', $view);
        $this->assertStringContainsString("class=\"attendance-status-cell\"", $view);
        $this->assertStringContainsString('attendance-view-action open-view-driver-attendance-modal', $view);
        $this->assertStringContainsString('<span>View</span>', $view);
        $this->assertStringContainsString('aria-label="View attendance for', $view);
        $this->assertStringContainsString('button.attendance-view-action', $styles);
        $this->assertStringContainsString('.attendance-assignment-schedule', $styles);
        $this->assertStringContainsString('.attendance-status-cell .gct-pill', $styles);
        $this->assertStringContainsString('Six columns: separate scheduled service date/time', $styles);
    }

    public function test_attendance_view_modal_lists_all_trips_in_a_read_only_table(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Attendance/driver-attendance.blade.php'));
        $script = file_get_contents(resource_path('js/Operation/Attendance/driver-attendance.js'));
        $styles = file_get_contents(resource_path('css/Operation/Attendance/driver-attendance.css'));

        $this->assertStringContainsString('data-trip-assignments="{{ $modalTripAssignments->toJson() }}"', $view);
        $this->assertStringContainsString('$historicalAssignments->map(function ($assignment)', $view);
        $this->assertStringContainsString('->values();', $view);
        $this->assertStringContainsString('id="attendanceModalTripsBody"', $view);
        $this->assertStringContainsString('id="attendanceModalTripsCount"', $view);
        $this->assertStringContainsString('<th scope="col">Trip ID</th>', $view);
        $this->assertStringContainsString('<th scope="col">Scheduled Time</th>', $view);
        $this->assertStringContainsString('renderAttendanceTrips(button);', $script);
        $this->assertStringContainsString('trips.map(trip =>', $script);
        $this->assertStringContainsString('escapeAttendanceHtml(trip.trip_id', $script);
        $this->assertStringNotContainsString("['Current Assignment', button.dataset.busAssignment", $script);
        $this->assertStringContainsString('max-height: 285px;', $styles);
        $this->assertStringContainsString('.attendance-modal-trips-table th', $styles);
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
