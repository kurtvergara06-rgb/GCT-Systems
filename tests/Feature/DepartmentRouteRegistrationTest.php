<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DepartmentRouteRegistrationTest extends TestCase
{
    public function test_department_route_files_register_key_existing_route_names(): void
    {
        $routeNames = [
            'maintenance-dashboard',
            'job-orders',
            'purchase-requests',
            'warehouse.dashboard',
            'inventory',
            'part-requests',
            'dashboard-purchase',
            'purchase-orders',
            'dashboard-operation',
            'bus-master-list',
            'operation.routes',
            'trip-schedule',
            'driver-bus-assignment',
            'auto-scheduling',
            'admin.dashboard',
            'admin.users',
            'batch-file-processing',
            'analytics',
            'analytics.overview',
            'analytics.descriptive',
        ];

        foreach ($routeNames as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "Expected route [{$routeName}] to remain registered after route organization."
            );
        }
    }
}
