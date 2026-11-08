<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendAssetOptimizationTest extends TestCase
{
    public function test_shared_app_bundle_does_not_eager_load_page_only_attendance_assets(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString(
            "../css/Operation/Attendance/batch-attendance.css",
            $source
        );
        $this->assertStringNotContainsString(
            "./Operation/Attendance/batch-attendance.js",
            $source
        );
        $this->assertStringNotContainsString(
            "./Main-js/partial-navigation.js",
            $source
        );
    }

    public function test_attendance_pages_load_batch_assets_only_where_needed(): void
    {
        $driver = file_get_contents(resource_path('views/Operation/Attendance/driver-attendance.blade.php'));
        $mechanic = file_get_contents(resource_path('views/Operation/Attendance/mechanic-attendance.blade.php'));

        // Both attendance pages expose permission-gated manual/import workflows.
        // Driver attendance is DDR-verified, not a batch entry page.
        $this->assertStringNotContainsString('resources/js/Operation/Attendance/batch-attendance.js', $driver);
        $this->assertStringContainsString('resources/js/Operation/Attendance/driver-attendance.js', $driver);
        $this->assertStringContainsString('resources/css/Operation/Attendance/batch-attendance.css', $mechanic);
        $this->assertStringContainsString('resources/js/Operation/Attendance/batch-attendance.js', $mechanic);
    }

    public function test_batch_attendance_uses_partial_navigation_initializer(): void
    {
        $source = file_get_contents(
            resource_path('js/Operation/Attendance/batch-attendance.js')
        );

        $this->assertStringContainsString(
            "window.GCTPartialNavigation.registerInitializer(",
            $source
        );
        $this->assertStringContainsString(
            "'operation-batch-attendance'",
            $source
        );
        $this->assertStringNotContainsString(
            "document.addEventListener('DOMContentLoaded', () => {",
            $source
        );
    }

    public function test_duplicate_toast_and_data_history_entries_are_removed(): void
    {
        $notificationSettings = file_get_contents(
            resource_path('views/Admin/Settings/notification-settings.blade.php')
        );
        $dataHistory = file_get_contents(
            resource_path('views/Admin/Data_Management/data-history.blade.php')
        );
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringNotContainsString(
            'resources/css/Main-styles/system-toast.css',
            $notificationSettings
        );
        $this->assertStringNotContainsString(
            'resources/js/Main-js/system-toast.js',
            $notificationSettings
        );
        $this->assertStringNotContainsString(
            'resources/js/Admin/Data_Management/data-history.js',
            $dataHistory
        );

        $this->assertStringNotContainsString(
            "'resources/css/Main-styles/system-toast.css',",
            $vite
        );
        $this->assertStringNotContainsString(
            "'resources/js/Main-js/system-toast.js',",
            $vite
        );
        $this->assertStringNotContainsString(
            "'resources/js/Admin/Data_Management/data-history.js',",
            $vite
        );
    }

    public function test_vite_keeps_required_standalone_entries_and_no_unused_bunny_font(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString(
            "'resources/js/Main-js/partial-navigation.js'",
            $vite
        );
        $this->assertStringContainsString(
            "'resources/css/Operation/Attendance/batch-attendance.css'",
            $vite
        );
        $this->assertStringContainsString(
            "'resources/js/Operation/Attendance/batch-attendance.js'",
            $vite
        );
        $this->assertStringNotContainsString(
            "laravel-vite-plugin/fonts",
            $vite
        );
        $this->assertStringNotContainsString(
            "Instrument Sans",
            $vite
        );
    }

    public function test_operation_route_map_pin_styles_are_loaded_only_on_routes_page(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $vite = file_get_contents(base_path('vite.config.js'));
        $routes = file_get_contents(resource_path('views/Operation/Routes/routes-stops.blade.php'));
        $styles = file_get_contents(resource_path('css/Operation/Routes/route-pin-enhancements.css'));

        $this->assertStringNotContainsString(
            "import '../css/Operation/Routes/route-pin-enhancements.css';",
            $app
        );
        $this->assertStringContainsString(
            "'resources/css/Operation/Routes/route-pin-enhancements.css'",
            $vite
        );
        $this->assertStringContainsString(
            "'resources/css/Operation/Routes/route-pin-enhancements.css'",
            $routes
        );
        $this->assertStringContainsString('#routeModal', $styles);
    }


    public function test_auto_scheduling_print_table_has_one_authoritative_column_width_set(): void
    {
        $css = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/auto-dispatch.css'));

        // Older print sections defined and redefined the same seven widths.
        // Keep the final printing contract intact without shadowed copies.
        $expected = [11, 12, 21, 13, 17, 16, 10];
        foreach ($expected as $index => $percent) {
            $selector = 'body:has(.auto-scheduling-page) .auto-schedule-table th:nth-child('.($index + 1).')';
            $pattern = '/'.preg_quote($selector, '/').'\\s*\\{\\s*width:\\s*(\\d+)%\\s*!important;\\s*\\}/';
            $matches = [];
            $count = preg_match_all($pattern, $css, $matches);
            $this->assertSame(1, $count, $selector.' must be defined once');
            $this->assertSame((string) $percent, $matches[1][0], $selector.' width must remain unchanged');
        }
    }


    public function test_operation_trip_and_assignment_action_ownership_stays_separate(): void
    {
        $tripView = file_get_contents(resource_path('views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php'));
        $assignmentView = file_get_contents(resource_path('views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php'));
        $tripJs = file_get_contents(resource_path('js/Operation/Scheduling_And_Dispatch/trip-schedule.js'));
        $assignmentJs = file_get_contents(resource_path('js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js'));

        $this->assertStringContainsString('cancel-service-trip', $tripView);
        $this->assertStringContainsString('cancelServiceTripModal', $tripView);
        $this->assertStringNotContainsString('deleteTripForm-', $tripView);
        $this->assertStringNotContainsString('deleteTripModal', $tripView);

        $this->assertStringContainsString('remove-assignment', $assignmentView);
        $this->assertStringContainsString('removeAssignmentModal', $assignmentView);
        $this->assertStringNotContainsString('cancel-assignment-trip', $assignmentView);
        $this->assertStringNotContainsString('cancelAssignmentTripModal', $assignmentView);

        $this->assertStringContainsString('cancel-service-trip', $tripJs);
        $this->assertStringNotContainsString('deleteTripModal', $tripJs);
        $this->assertStringNotContainsString('cancelAssignmentTripModal', $assignmentJs);
        $this->assertStringContainsString('remove-assignment', $assignmentJs);
    }


    public function test_trip_schedule_calendar_shows_status_breakdown_and_legend(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Operation/TripScheduleController.php'));
        $view = file_get_contents(resource_path('views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php'));
        $script = file_get_contents(resource_path('js/Operation/Scheduling_And_Dispatch/trip-schedule.js'));
        $styles = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/trip-schedule.css'));

        $this->assertStringContainsString("->groupBy('trip_date', 'status')", $controller);
        $this->assertStringContainsString("'Missed' => 'missed'", $controller);
        $this->assertStringContainsString("'Pending DDR' => 'pending_ddr'", $controller);
        $this->assertStringContainsString('trip-calendar-status-legend', $view);
        $this->assertStringContainsString('Multiple dots = different statuses', $view);
        $this->assertStringContainsString('trip-calendar-status-dots', $script);
        $this->assertStringContainsString('statuses = data.statuses', $script);
        $this->assertStringContainsString('.trip-calendar-day-dot.is-missed', $styles);
    }


    public function test_trip_status_legend_styles_load_after_trip_schedule_styles(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Scheduling_And_Dispatch/trip-schedule.blade.php'));
        $vite = file_get_contents(base_path('vite.config.js'));
        $styles = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/trip-calendar-status.css'));

        $base = "'resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css'";
        $status = "'resources/css/Operation/Scheduling_And_Dispatch/trip-calendar-status.css'";
        $this->assertNotFalse(strpos($view, $base));
        $this->assertNotFalse(strpos($view, $status));
        $this->assertLessThan(strpos($view, $status), strpos($view, $base));
        $this->assertStringContainsString($status, $vite);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $styles);
        $this->assertStringContainsString('.trip-calendar-day-dot.is-missed', $styles);
        $this->assertStringContainsString('background-color: #dc2626', $styles);
    }


    public function test_assignment_month_calendar_is_registered_and_preserves_filter_date(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Scheduling_And_Dispatch/driver-bus-assignment.blade.php'));
        $script = file_get_contents(resource_path('js/Operation/Scheduling_And_Dispatch/assignment-calendar.js'));
        $css = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/assignment-calendar.css'));
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString('data-assignment-calendar', $view);
        $this->assertStringContainsString('driver-bus-assignment.calendar', $view);
        $this->assertStringContainsString('Assignment Status Legend', $view);
        $this->assertStringContainsString('name="trip_date"', $view);
        $this->assertStringContainsString("registerInitializer('operation-assignment-calendar'", $script);
        $this->assertStringContainsString("url.searchParams.set('trip_date', date)", $script);
        $this->assertStringContainsString('.assignment-calendar-legend-grid', $css);
        $this->assertStringContainsString('.assignment-calendar-dot.is-missed', $css);
        $this->assertStringContainsString('resources/js/Operation/Scheduling_And_Dispatch/assignment-calendar.js', $vite);
    }

}
