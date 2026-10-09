<?php

use App\Http\Controllers\Operation\AutoSchedulingController;
use App\Http\Controllers\Operation\BusController;
use App\Http\Controllers\Operation\DailyDriverReportController;
use App\Http\Controllers\Operation\DriverAttendanceController;
use App\Http\Controllers\Operation\IncidentController;
use App\Http\Controllers\Operation\MaintenanceReferralController;
use App\Http\Controllers\Operation\MechanicAttendanceController;
use App\Http\Controllers\Operation\RouteController;
use App\Http\Controllers\Operation\TripAssignmentController;
use App\Http\Controllers\Operation\TripRecordController;
use App\Http\Controllers\Operation\TripScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:operation:head,operation:staff,admin:head')->group(function () {

    Route::view('/operation/dashboard', 'Operation.dashboard-operation')->name('dashboard-operation');

    Route::controller(BusController::class)
        ->prefix('bus-master-list')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('bus-master-list');
            Route::post('/', 'store')
                ->middleware('system.permission:operation,edit')
                ->name('bus-master-list.store');
            Route::put('/{bus}', 'update')
                ->middleware('system.permission:operation,edit')
                ->name('bus-master-list.update');
            Route::delete('/{bus}', 'destroy')
                ->middleware('system.permission:operation,edit')
                ->name('bus-master-list.destroy');
        });

    Route::controller(DriverAttendanceController::class)
        ->prefix('driver-attendance')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('driver-attendance');
            Route::post('/', 'store')->middleware('system.permission:operation,edit')->name('driver-attendance.store');
            Route::post('/import', 'import')->middleware('system.permission:operation,edit')->name('driver-attendance.import');
            Route::put('/{driverAttendance}', 'update')->middleware('system.permission:operation,edit')->name('driver-attendance.update');
            Route::delete('/{driverAttendance}', 'destroy')->middleware('system.permission:operation,edit')->name('driver-attendance.destroy');
        });

    Route::redirect('/attendance', '/driver-attendance')->name('attendance');

    Route::get('/operation/routes', [RouteController::class, 'index'])->name('operation.routes');
    Route::post('/operation/routes', [RouteController::class, 'store'])->name('operation.routes.store');
    Route::put('/operation/routes/{shuttleRoute}', [RouteController::class, 'update'])->name('operation.routes.update');
    Route::delete('/operation/routes/{shuttleRoute}', [RouteController::class, 'destroy'])->name('operation.routes.destroy');
    Route::get('/operation/routes/location-search', [RouteController::class, 'searchLocations'])->middleware('throttle:60,1')->name('operation.routes.location-search');
    Route::get('/operation/routes/reverse-location', [RouteController::class, 'reverseLocation'])->middleware('throttle:60,1')->name('operation.routes.reverse-location');
    Route::post('/operation/routes/calculate', [RouteController::class, 'calculateRoute'])->middleware('throttle:60,1')->name('operation.routes.calculate');

    Route::controller(TripScheduleController::class)
        ->prefix('operation/trip-schedule')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('trip-schedule');
            Route::post('/', 'store')
                ->middleware('system.permission:operation,edit')
                ->name('trip-schedule.store');
            Route::put('/{tripSchedule}', 'update')
                ->middleware('system.permission:operation,edit')
                ->name('trip-schedule.update');
            Route::delete('/{tripSchedule}', 'destroy')
                ->middleware('system.permission:operation,edit')
                ->name('trip-schedule.destroy');
        });

    Route::controller(TripAssignmentController::class)
        ->prefix('operation/driver-bus-assignment')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('driver-bus-assignment');
            Route::get('/availability/{tripSchedule}', 'availability')
                ->name('driver-bus-assignment.availability');
            Route::post('/', 'store')
                ->middleware('system.permission:operation,edit')
                ->name('driver-bus-assignment.store');
            Route::put('/{tripAssignment}', 'update')
                ->middleware('system.permission:operation,edit')
                ->name('driver-bus-assignment.update');
            Route::delete('/{tripAssignment}', 'destroy')
                ->middleware('system.permission:operation,edit')
                ->name('driver-bus-assignment.destroy');
        });

    Route::controller(AutoSchedulingController::class)
        ->prefix('operation/auto-scheduling')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('auto-scheduling');
            Route::post('/generate', 'generate')->name('auto-scheduling.generate');
            Route::post('/confirm', 'confirm')
                ->middleware('system.permission:operation,edit')
                ->name('auto-scheduling.confirm');
            Route::post('/resolve', 'resolve')
                ->middleware('system.permission:operation,edit')
                ->name('auto-scheduling.resolve');
        });

    Route::redirect('/operation/auto-dispatch', '/operation/auto-scheduling')->name('auto-dispatch');
    Route::get('/operation/trip-records', [TripRecordController::class, 'index'])->name('trip-records');
    Route::get('/operation/trip-records/export', [TripRecordController::class, 'export'])->middleware('system.permission:operation,view')->name('trip-records.export');

    Route::controller(DailyDriverReportController::class)->prefix('operation/daily-driver-reports')->group(function () {
        Route::get('/', 'index')->name('daily-driver-reports');
        Route::get('/create', 'create')->name('daily-driver-reports.create');
        Route::post('/', 'store')->name('daily-driver-reports.store');
        Route::get('/schedule-lookup', 'scheduleLookup')->middleware('throttle:60,1')->name('daily-driver-reports.schedule-lookup');
        Route::get('/{dailyDriverReport}', 'show')->name('daily-driver-reports.show');
    });

    Route::post('/operation/incidents/{incident}/maintenance-referral', [MaintenanceReferralController::class, 'store'])
        ->name('incidents.maintenance-referral.store');

    Route::controller(IncidentController::class)->prefix('operation/incidents')->group(function () {
        Route::get('/', 'index')->name('incidents');
        Route::get('/create', 'create')->name('incidents.create');
        Route::post('/', 'store')->name('incidents.store');
        Route::get('/{incident}', 'show')->name('incidents.show');
        Route::put('/{incident}', 'update')->name('incidents.update');
        Route::post('/{incident}/dispatch', 'dispatchReplacement')->name('incidents.dispatch');
        Route::post('/{incident}/response', 'addResponse')->name('incidents.response');
    });
});

Route::middleware('role:operation:head,operation:staff,maintenance:head,maintenance:staff,admin:head')
    ->controller(MechanicAttendanceController::class)
    ->prefix('mechanic-attendance')
    ->group(function () {
        Route::get('/', 'index')->name('mechanic-attendance');
        Route::post('/', 'store')->middleware(['role:operation:head,operation:staff,admin:head', 'system.permission:operation,edit'])->name('mechanic-attendance.store');
        Route::put('/{mechanicAttendance}', 'update')->middleware(['role:operation:head,operation:staff,admin:head', 'system.permission:operation,edit'])->name('mechanic-attendance.update');
        Route::delete('/{mechanicAttendance}', 'destroy')->middleware(['role:operation:head,operation:staff,admin:head', 'system.permission:operation,edit'])->name('mechanic-attendance.destroy');
        Route::post('/import', 'import')->middleware(['role:operation:head,operation:staff,admin:head', 'system.permission:operation,edit'])->name('mechanic-attendance.import');
    });

Route::redirect('/available-mechanics', '/mechanic-attendance')
    ->middleware('role:operation:head,operation:staff,maintenance:head,maintenance:staff,admin:head')
    ->name('available-mechanics');
