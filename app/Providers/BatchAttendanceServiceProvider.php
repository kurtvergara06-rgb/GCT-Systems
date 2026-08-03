<?php

namespace App\Providers;

use App\Http\Controllers\Operation\BatchAttendanceController;
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
    }
}
