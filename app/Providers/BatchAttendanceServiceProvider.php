<?php

namespace App\Providers;

use App\Http\Controllers\Operation\BatchAttendanceController;
use App\Http\Controllers\Operation\PersonnelController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BatchAttendanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('operation/attendance/batch')
            ->group(function (): void {
                Route::get('/{type}', [BatchAttendanceController::class, 'roster'])
                    ->whereIn('type', ['driver', 'mechanic'])
                    ->name('operation.attendance.batch.roster');

                Route::post('/{type}', [BatchAttendanceController::class, 'store'])
                    ->whereIn('type', ['driver', 'mechanic'])
                    ->name('operation.attendance.batch.store');
            });

        Route::middleware(['web', 'auth'])
            ->prefix('operation/personnel')
            ->group(function (): void {
                Route::get('/drivers', [PersonnelController::class, 'drivers'])
                    ->name('operation.personnel.drivers');
                Route::post('/drivers', [PersonnelController::class, 'storeDriver'])
                    ->name('operation.personnel.drivers.store');

                Route::get('/mechanics', [PersonnelController::class, 'mechanics'])
                    ->name('operation.personnel.mechanics');
                Route::post('/mechanics', [PersonnelController::class, 'storeMechanic'])
                    ->name('operation.personnel.mechanics.store');
            });
    }
}
