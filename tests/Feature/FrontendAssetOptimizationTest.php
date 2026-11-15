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
        foreach ([
            resource_path('views/Operation/Attendance/driver-attendance.blade.php'),
            resource_path('views/Operation/Attendance/mechanic-attendance.blade.php'),
        ] as $view) {
            $source = file_get_contents($view);

            $this->assertStringContainsString(
                'resources/css/Operation/Attendance/batch-attendance.css',
                $source
            );
            $this->assertStringContainsString(
                'resources/js/Operation/Attendance/batch-attendance.js',
                $source
            );
        }
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
}
