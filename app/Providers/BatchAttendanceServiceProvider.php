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
        Route::middleware(['web', 'auth', 'role:operation:head,operation:staff,admin:head'])
            ->prefix('operation/attendance/batch')
            ->group(function (): void {
                Route::get('/{type}', [BatchAttendanceController::class, 'roster'])
                    ->middleware('system.permission:operation,view')
                    ->whereIn('type', ['driver', 'mechanic'])
                    ->name('operation.attendance.batch.roster');

                Route::post('/{type}', [BatchAttendanceController::class, 'store'])
                    ->middleware('system.permission:operation,edit')
                    ->whereIn('type', ['driver', 'mechanic'])
                    ->name('operation.attendance.batch.store');
            });

        // Maintenance attendance uses Maintenance permissions, never Operation permissions.
        Route::middleware(['web', 'auth', 'role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin'])
            ->prefix('maintenance/attendance/batch')
            ->group(function (): void {
                Route::get('/mechanic', [BatchAttendanceController::class, 'roster'])
                    ->defaults('type', 'mechanic')
                    ->middleware('system.permission:maintenance,view')
                    ->name('maintenance.attendance.batch.roster');
                Route::post('/mechanic', [BatchAttendanceController::class, 'store'])
                    ->defaults('type', 'mechanic')
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.attendance.batch.store');
            });

        Route::middleware(['web', 'auth', 'role:operation:head,operation:staff,admin:head'])
            ->prefix('operation/personnel')
            ->group(function (): void {
                Route::get('/drivers', [PersonnelController::class, 'drivers'])
                    ->middleware('system.permission:operation,view')
                    ->name('operation.personnel.drivers');
                Route::post('/drivers', [PersonnelController::class, 'storeDriver'])
                    ->middleware('system.permission:operation,edit')
                    ->name('operation.personnel.drivers.store');
                Route::put('/drivers/{driver}', [PersonnelController::class, 'updateDriver'])
                    ->middleware('system.permission:operation,edit')
                    ->name('operation.personnel.drivers.update');
                Route::patch('/drivers/{driver}/deactivate', [PersonnelController::class, 'deactivateDriver'])
                    ->middleware('system.permission:operation,edit')
                    ->name('operation.personnel.drivers.deactivate');

            });

        // Preserve old bookmarked Mechanics URL while enforcing new Maintenance ownership.
        Route::middleware(['web', 'auth', 'role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin', 'system.permission:maintenance,view'])
            ->get('/operation/personnel/mechanics', fn () => redirect()->route('maintenance.personnel.mechanics'))
            ->name('operation.personnel.mechanics');

        Route::middleware(['web', 'auth', 'role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin'])
            ->prefix('maintenance/mechanics')
            ->group(function (): void {
                Route::get('/', [PersonnelController::class, 'mechanics'])
                    ->middleware('system.permission:maintenance,view')
                    ->name('maintenance.personnel.mechanics');
                Route::delete('/specializations/{specialization}', [PersonnelController::class, 'deleteMechanicSpecialization'])
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.personnel.mechanics.specializations.destroy');
                Route::post('/specializations', [PersonnelController::class, 'storeMechanicSpecialization'])
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.personnel.mechanics.specializations.store');
                Route::post('/', [PersonnelController::class, 'storeMechanic'])
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.personnel.mechanics.store');
                Route::put('/{mechanic}', [PersonnelController::class, 'updateMechanic'])
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.personnel.mechanics.update');
                Route::patch('/{mechanic}/deactivate', [PersonnelController::class, 'deactivateMechanic'])
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.personnel.mechanics.deactivate');
                Route::patch('/{mechanic}/activate', [PersonnelController::class, 'activateMechanic'])
                    ->middleware('system.permission:maintenance,edit')
                    ->name('maintenance.personnel.mechanics.activate');
            });
    }
}
