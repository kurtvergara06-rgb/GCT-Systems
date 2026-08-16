<?php

use App\Http\Controllers\Operation\AutoSchedulingController;
use App\Http\Controllers\Operation\BusController;
use App\Http\Controllers\Operation\DriverAttendanceController;
use App\Http\Controllers\Operation\MechanicAttendanceController;
use App\Http\Controllers\Operation\RouteController;
use App\Http\Controllers\Operation\TripAssignmentController;
use App\Http\Controllers\Operation\TripScheduleController;
use Illuminate\Support\Facades\Route;

Route::view(
    '/operation/dashboard',
    'Operation.dashboard-operation'
)->name('dashboard-operation');

Route::controller(BusController::class)
    ->prefix('bus-master-list')
    ->group(function () {
        Route::get('/', 'index')->name('bus-master-list');
        Route::post('/', 'store')->name('bus-master-list.store');
        Route::post('/import', 'import')->name('bus-master-list.import');
        Route::put('/{bus}', 'update')->name('bus-master-list.update');
        Route::delete('/{bus}', 'destroy')->name('bus-master-list.destroy');
    });

Route::controller(DriverAttendanceController::class)
    ->prefix('driver-attendance')
    ->group(function () {
        Route::get('/', 'index')->name('driver-attendance');
        Route::post('/', 'store')->name('driver-attendance.store');
        Route::post('/import', 'import')->name('driver-attendance.import');
        Route::put('/{driverAttendance}', 'update')->name('driver-attendance.update');
        Route::delete('/{driverAttendance}', 'destroy')->name('driver-attendance.destroy');
    });

Route::redirect('/attendance', '/driver-attendance')->name('attendance');

Route::controller(MechanicAttendanceController::class)
    ->prefix('mechanic-attendance')
    ->group(function () {
        Route::get('/', 'index')->name('mechanic-attendance');
        Route::post('/', 'store')->name('mechanic-attendance.store');
        Route::put('/{mechanicAttendance}', 'update')->name('mechanic-attendance.update');
        Route::delete('/{mechanicAttendance}', 'destroy')->name('mechanic-attendance.destroy');
        Route::post('/import', 'import')->name('mechanic-attendance.import');
    });

Route::redirect('/available-mechanics', '/mechanic-attendance')
    ->name('available-mechanics');

Route::get('/operation/routes', [RouteController::class, 'index'])
    ->name('operation.routes');
Route::post('/operation/routes', [RouteController::class, 'store'])
    ->name('operation.routes.store');
Route::put('/operation/routes/{shuttleRoute}', [RouteController::class, 'update'])
    ->name('operation.routes.update');
Route::delete('/operation/routes/{shuttleRoute}', [RouteController::class, 'destroy'])
    ->name('operation.routes.destroy');
Route::get('/operation/routes/location-search', [RouteController::class, 'searchLocations'])
    ->middleware('throttle:60,1')
    ->name('operation.routes.location-search');
Route::post('/operation/routes/calculate', [RouteController::class, 'calculateRoute'])
    ->middleware('throttle:60,1')
    ->name('operation.routes.calculate');

Route::controller(TripScheduleController::class)
    ->prefix('operation/trip-schedule')
    ->group(function () {
        Route::get('/', 'index')->name('trip-schedule');
        Route::post('/', 'store')->name('trip-schedule.store');
        Route::put('/{tripSchedule}', 'update')->name('trip-schedule.update');
        Route::delete('/{tripSchedule}', 'destroy')->name('trip-schedule.destroy');
    });

Route::controller(TripAssignmentController::class)
    ->prefix('operation/driver-bus-assignment')
    ->group(function () {
        Route::get('/', 'index')->name('driver-bus-assignment');
        Route::post('/', 'store')->name('driver-bus-assignment.store');
        Route::put('/{tripAssignment}', 'update')->name('driver-bus-assignment.update');
        Route::delete('/{tripAssignment}', 'destroy')->name('driver-bus-assignment.destroy');
    });

Route::controller(AutoSchedulingController::class)
    ->prefix('operation/auto-scheduling')
    ->group(function () {
        Route::get('/', 'index')->name('auto-scheduling');
        Route::post('/generate', 'generate')->name('auto-scheduling.generate');
        Route::post('/confirm', 'confirm')->name('auto-scheduling.confirm');
        Route::post('/resolve', 'resolve')->name('auto-scheduling.resolve');
    });

Route::redirect('/operation/auto-dispatch', '/operation/auto-scheduling')
    ->name('auto-dispatch');

Route::view(
    '/operation/trip-records',
    'Operation.Trip_Records.trip-records'
)->name('trip-records');
