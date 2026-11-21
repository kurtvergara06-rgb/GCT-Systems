<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteCacheUniquenessTest extends TestCase
{
    public function test_personnel_and_batch_attendance_route_names_are_registered_once(): void
    {
        $names = [
            'operation.personnel.drivers',
            'operation.personnel.mechanics',
            'maintenance.personnel.mechanics',
            'maintenance.personnel.mechanics.store',
            'maintenance.personnel.mechanics.update',
            'maintenance.personnel.mechanics.deactivate',
            'maintenance.personnel.mechanics.activate',
            'maintenance.personnel.mechanics.specializations.store',
            'maintenance.personnel.mechanics.specializations.destroy',
            'operation.attendance.batch.roster',
            'operation.attendance.batch.store',
            'maintenance.attendance.batch.roster',
            'maintenance.attendance.batch.store',
        ];

        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->countBy();

        foreach ($names as $name) {
            $this->assertSame(1, $registered->get($name, 0), "Route name [{$name}] must be unique for route:cache.");
        }
    }
}
