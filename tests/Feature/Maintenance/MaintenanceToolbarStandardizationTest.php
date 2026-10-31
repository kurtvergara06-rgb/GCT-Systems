<?php

use App\Http\Controllers\Maintenance\FuelReportController;

it('uses the shared table toolbar on every applicable maintenance record page', function (): void {
    $applicablePages = [
        'referrals.blade.php' => resource_path('views/Maintenance/referrals.blade.php'),
        'job-order.blade.php' => resource_path('views/Maintenance/job-order.blade.php'),
        'pms-scheduling.blade.php' => resource_path('views/Maintenance/pms-scheduling.blade.php'),
        'mechanic-list.blade.php' => resource_path('views/Maintenance/mechanic-list.blade.php'),
        'purchase-requests.blade.php' => resource_path('views/Maintenance/purchase-requests.blade.php'),
        'fuel-reports.blade.php' => resource_path('views/Maintenance/fuel-reports.blade.php'),
        'mechanic_master_list.blade.php' => resource_path('views/Operation/Personnel Management/mechanic_master_list.blade.php'),
        'mechanic-attendance.blade.php' => resource_path('views/Operation/Attendance/mechanic-attendance.blade.php'),
    ];

    foreach ($applicablePages as $page => $path) {
        $source = file_get_contents($path);

        expect($source, $page)->toContain('<x-ui.table-toolbar');
    }

    $dashboard = file_get_contents(resource_path('views/Maintenance/maintenance-dashboard.blade.php'));
    expect($dashboard)->not->toContain('<x-ui.table-toolbar');
});

it('keeps daily monitoring filters and recent fuel record controls independent', function (): void {
    $view = file_get_contents(resource_path('views/Maintenance/fuel-reports.blade.php'));
    $controller = file_get_contents((new ReflectionClass(FuelReportController::class))->getFileName());

    expect($view)
        ->toContain('form-id="dailyFuelMonitoringToolbar"')
        ->toContain(':show-search="false"')
        ->toContain('name="monitor_date"')
        ->toContain('name="monitor_filter"')
        ->toContain('form-id="recentFuelEntriesToolbar"')
        ->toContain('search-label="Search Fuel Entries"')
        ->toContain('name="date_filter"')
        ->toContain("'report_date' => 'Report Date'")
        ->toContain('button-id="openFuelModal"');

    expect($controller)
        ->toContain('TableSort::apply($query, $request')
        ->toContain("'report_date' => 'report_date'")
        ->toContain("'km_per_liter' => 'km_per_liter'");
});
