<?php

use App\Http\Controllers\Operation\AutoSchedulingController;
use App\Http\Controllers\Operation\BusAvailabilityController;
use App\Http\Controllers\Operation\PersonnelController;
use App\Http\Controllers\Operation\BatchAttendanceController;
use App\Http\Controllers\Operation\BusController;
use App\Http\Controllers\Operation\ClientMasterListController;
use App\Http\Controllers\Operation\ClientTripTimeController;
use App\Http\Controllers\Operation\DailyDriverReportController;
use App\Http\Controllers\Operation\DriverAttendanceController;
use App\Http\Controllers\Operation\IncidentController;
use App\Http\Controllers\Operation\MaintenanceReferralController;
use App\Http\Controllers\Operation\MechanicAttendanceController;
use App\Http\Controllers\Operation\OperationDashboardController;
use App\Http\Controllers\Operation\RouteController;
use App\Http\Controllers\Operation\TripAssignmentController;
use App\Http\Controllers\Operation\TripRecordController;
use App\Http\Controllers\Operation\TripScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:operation:head,operation:staff,admin:head')->group(function () {

    Route::get('/operation/dashboard', [OperationDashboardController::class, 'index'])
        ->middleware('system.permission:operation,view')
        ->name('dashboard-operation');

    Route::redirect('/operation/shuttle-bus-management', '/bus-master-list')->name('operation.shuttle-bus-management');
    Route::redirect('/operation/personnel-management/driver-master-list', '/operation/personnel/drivers')->name('operation.personnel-management.driver-master-list');
    Route::redirect('/operation/personnel-management/mechanic-master-list', '/operation/personnel/mechanics')->name('operation.personnel-management.mechanic-master-list');
    Route::redirect('/operation/routes-stops', '/operation/routes')->name('operation.routes-stops');

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
            Route::patch('/{bus}/deactivate', 'deactivate')
                ->middleware('system.permission:operation,edit')
                ->name('bus-master-list.deactivate');
            Route::patch('/{bus}/activate', 'activate')
                ->middleware('system.permission:operation,edit')
                ->name('bus-master-list.activate');
            Route::delete('/{bus}', 'destroy')
                ->middleware('system.permission:operation,edit')
                ->name('bus-master-list.destroy');
        });

    Route::get('/operation/bus-availability', [BusAvailabilityController::class, 'index'])
        ->middleware('system.permission:operation,view')
        ->name('bus-availability');

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

    // Restore the existing Driver and legacy Mechanic Master List URLs.
    // Mechanic writes are additionally guarded by the controller's Maintenance
    // ownership check; Operation can only inspect its legacy list.
    Route::controller(PersonnelController::class)->prefix('operation/personnel')->group(function () {
        Route::get('/drivers', 'drivers')->middleware('system.permission:operation,view')->name('operation.personnel.drivers');
        Route::post('/drivers', 'storeDriver')->middleware('system.permission:operation,edit')->name('operation.personnel.drivers.store');
        Route::put('/drivers/{driver}', 'updateDriver')->middleware('system.permission:operation,edit')->name('operation.personnel.drivers.update');
        Route::patch('/drivers/{driver}/deactivate', 'deactivateDriver')->middleware('system.permission:operation,edit')->name('operation.personnel.drivers.deactivate');

        Route::get('/mechanics', 'mechanics')->middleware('system.permission:operation,view')->name('operation.personnel.mechanics');
        Route::post('/mechanics', 'storeMechanic')->middleware('system.permission:operation,edit')->name('operation.personnel.mechanics.store');
        Route::put('/mechanics/{mechanic}', 'updateMechanic')->middleware('system.permission:operation,edit')->name('operation.personnel.mechanics.update');
        Route::patch('/mechanics/{mechanic}/deactivate', 'deactivateMechanic')->middleware('system.permission:operation,edit')->name('operation.personnel.mechanics.deactivate');
        Route::patch('/mechanics/{mechanic}/activate', 'activateMechanic')->middleware('system.permission:operation,edit')->name('operation.personnel.mechanics.activate');
    });


    Route::get('/operation/routes', [RouteController::class, 'index'])->middleware('system.permission:operation,view')->name('operation.routes');
    Route::post('/operation/routes', [RouteController::class, 'store'])->middleware('system.permission:operation,edit')->name('operation.routes.store');
    Route::put('/operation/routes/{shuttleRoute}', [RouteController::class, 'update'])->middleware('system.permission:operation,edit')->name('operation.routes.update');
    Route::patch('/operation/routes/{shuttleRoute}/status', [RouteController::class, 'updateStatus'])->middleware('system.permission:operation,edit')->name('operation.routes.status');
    Route::get('/operation/routes/location-search', [RouteController::class, 'searchLocations'])->middleware(['system.permission:operation,view', 'throttle:60,1'])->name('operation.routes.location-search');
    Route::get('/operation/routes/reverse-location', [RouteController::class, 'reverseLocation'])->middleware(['system.permission:operation,view', 'throttle:60,1'])->name('operation.routes.reverse-location');
    Route::post('/operation/routes/calculate', [RouteController::class, 'calculateRoute'])->middleware(['system.permission:operation,view', 'throttle:60,1'])->name('operation.routes.calculate');

    Route::get('/operation/clients', [ClientMasterListController::class, 'index'])
        ->middleware('system.permission:operation,view')
        ->name('operation.clients.index');
    Route::post('/operation/clients', [ClientMasterListController::class, 'store'])
        ->middleware('system.permission:operation,edit')
        ->name('operation.clients.store');
    Route::put('/operation/clients/{operationClient}', [ClientMasterListController::class, 'update'])
        ->middleware('system.permission:operation,edit')
        ->name('operation.clients.update');
    Route::post('/operation/clients/{operationClient}/logo', [ClientMasterListController::class, 'updateLogo'])
        ->middleware('system.permission:operation,edit')
        ->name('operation.clients.logo');
    Route::patch('/operation/clients/{operationClient}/status', [ClientMasterListController::class, 'updateStatus'])
        ->middleware('system.permission:operation,edit')
        ->name('operation.clients.status');

    Route::controller(TripScheduleController::class)
        ->prefix('operation/trip-schedule')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('trip-schedule');
            Route::get('/available-dates', 'availableDates')->middleware('throttle:60,1')->name('trip-schedule.available-dates');
            Route::post('/', 'store')
                ->middleware('system.permission:operation,edit')
                ->name('trip-schedule.store');
            Route::put('/{tripSchedule}', 'update')
                ->middleware('system.permission:operation,edit')
                ->name('trip-schedule.update');
            Route::patch('/{tripSchedule}/cancel', 'cancel')
                ->middleware(['system.permission:operation,edit', 'system.permission:operation,approve'])
                ->name('trip-schedule.cancel');
            Route::delete('/{tripSchedule}', 'destroy')
                ->middleware('system.permission:operation,edit')
                ->name('trip-schedule.destroy');
        });

    Route::controller(ClientTripTimeController::class)
        ->prefix('operation/client-trip-times')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('client-trip-times');
            Route::get('/panels/{operationClient}/{category}', 'panel')
                ->name('client-trip-times.panel');
            Route::post('/', 'store')
                ->middleware('system.permission:operation,edit')
                ->name('client-trip-times.store');
            Route::put('/{clientTripTimeConfiguration}', 'update')
                ->middleware('system.permission:operation,edit')
                ->name('client-trip-times.update');
            Route::post('/{clientTripTimeConfiguration}/slots', 'addSlot')
                ->middleware('system.permission:operation,edit')
                ->name('client-trip-times.slots.store');
            Route::put('/slots/{clientTripTimeSlot}', 'updateSlot')
                ->middleware('system.permission:operation,edit')
                ->name('client-trip-times.slots.update');
            Route::delete('/slots/{clientTripTimeSlot}', 'destroySlot')
                ->middleware('system.permission:operation,edit')
                ->name('client-trip-times.slots.destroy');
        });

    Route::controller(TripAssignmentController::class)
        ->prefix('operation/driver-bus-assignment')
        ->middleware('system.permission:operation,view')
        ->group(function () {
            Route::get('/', 'index')->name('driver-bus-assignment');
            Route::get('/calendar', 'calendar')->middleware('throttle:60,1')->name('driver-bus-assignment.calendar');
            Route::get('/print', 'printSheet')->name('driver-bus-assignment.print');
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
            Route::get('/calendar', 'calendar')->name('auto-scheduling.calendar');
            Route::get('/ai/training/status', 'trainingStatus')->name('auto-scheduling.ai.training-status');
            Route::get('/drafts', 'listDrafts')->name('auto-scheduling.drafts.index');
            Route::get('/draft', 'loadDraft')->name('auto-scheduling.draft.load');
            Route::post('/draft', 'saveDraft')->middleware('system.permission:operation,edit')->name('auto-scheduling.draft.save');
            Route::delete('/draft', 'deleteDraft')->middleware('system.permission:operation,edit')->name('auto-scheduling.draft.delete');
            Route::post('/generate', 'generate')->name('auto-scheduling.generate'); // Read-only recommendation preview; confirm/resolve require edit permission.
            Route::post('/edit-preview', 'editPreview')
                ->middleware('system.permission:operation,edit')
                ->name('auto-scheduling.edit-preview');
            Route::post('/confirm', 'confirm')
                ->middleware([
                    'system.permission:operation,edit',
                    'system.permission:operation,approve',
                ])
                ->name('auto-scheduling.confirm');
            Route::post('/resolve', 'resolve')
                ->middleware('system.permission:operation,edit')
                ->name('auto-scheduling.resolve');
        });

    Route::redirect('/operation/auto-dispatch', '/operation/auto-scheduling')->name('auto-dispatch');
    Route::get('/operation/trip-records', [TripRecordController::class, 'index'])->middleware('system.permission:operation,view')->name('trip-records');
    Route::get('/operation/trip-records/export', [TripRecordController::class, 'export'])->middleware('system.permission:operation,view')->name('trip-records.export');

    Route::controller(DailyDriverReportController::class)->prefix('operation/daily-driver-reports')->middleware('system.permission:operation,view')->group(function () {
        Route::get('/', 'index')->name('daily-driver-reports');
        Route::post('/', 'store')->middleware('system.permission:operation,edit')->name('daily-driver-reports.store');
        Route::get('/schedule-lookup', 'scheduleLookup')->middleware('throttle:60,1')->name('daily-driver-reports.schedule-lookup');
        Route::get('/assigned-drivers', 'assignedDrivers')->middleware('throttle:60,1')->name('daily-driver-reports.assigned-drivers');
        // Keep legacy URLs registered for permission auditing, but controller rejects mutation.
        Route::put('/{dailyDriverReport}', 'update')->middleware('system.permission:operation,edit')->name('daily-driver-reports.update');
        Route::delete('/{dailyDriverReport}', 'destroy')->middleware('system.permission:operation,edit')->name('daily-driver-reports.destroy');
    });

    Route::post('/operation/incidents/{incident}/maintenance-referral', [MaintenanceReferralController::class, 'store'])
        ->middleware('system.permission:operation,edit')
        ->name('incidents.maintenance-referral.store');

    Route::controller(IncidentController::class)->prefix('operation/incidents')->middleware('system.permission:operation,view')->group(function () {
        Route::get('/', 'index')->name('incidents');
        Route::get('/create', 'create')->name('incidents.create');
        Route::post('/', 'store')->middleware('system.permission:operation,edit')->name('incidents.store');
        Route::patch('/{incident}/details', 'updateDetails')->middleware('system.permission:operation,edit')->name('incidents.details.update');
        Route::delete('/{incident}', 'destroy')->middleware('system.permission:operation,edit')->name('incidents.destroy');
        Route::get('/{incident}', 'show')->name('incidents.show');
        Route::put('/{incident}', 'update')->middleware('system.permission:operation,edit')->name('incidents.update');
        Route::post('/{incident}/dispatch', 'dispatchReplacement')->middleware(['system.permission:operation,edit', 'system.permission:operation,approve'])->name('incidents.dispatch');
        Route::post('/{incident}/response', 'addResponse')->middleware('system.permission:operation,edit')->name('incidents.response');
    });
});


/*
 * Batch attendance: one UI endpoint for driver and mechanic rosters. The
 * controller validates the requested type and enforces that department's
 * view/edit permission before fetching or changing any records.
 */
Route::middleware('role:operation:head,operation:staff,maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin')
    ->controller(BatchAttendanceController::class)
    ->prefix('operation/attendance/batch')
    ->group(function () {
        Route::get('/{type}', 'roster')->whereIn('type', ['driver', 'mechanic'])->name('operation.attendance.batch.roster');
        Route::post('/{type}', 'store')->whereIn('type', ['driver', 'mechanic'])->name('operation.attendance.batch.store');
    });

/*
 * Mechanic master records and qualifications are Maintenance-owned. Keep
 * their original named routes for the Maintenance navigation and modals.
 */
Route::middleware('role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin')
    ->controller(BatchAttendanceController::class)
    ->prefix('maintenance/attendance/batch')
    ->group(function () {
        Route::get('/mechanic', 'roster')->defaults('type', 'mechanic')->name('maintenance.attendance.batch.roster');
        Route::post('/mechanic', 'store')->defaults('type', 'mechanic')->name('maintenance.attendance.batch.store');
    });

Route::middleware('role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin')
    ->controller(PersonnelController::class)
    ->prefix('maintenance/personnel/mechanics')
    ->group(function () {
        Route::get('/', 'mechanics')->middleware('system.permission:maintenance,view')->name('maintenance.personnel.mechanics');
        Route::post('/', 'storeMechanic')->middleware('system.permission:maintenance,edit')->name('maintenance.personnel.mechanics.store');
        Route::put('/{mechanic}', 'updateMechanic')->middleware('system.permission:maintenance,edit')->name('maintenance.personnel.mechanics.update');
        Route::patch('/{mechanic}/deactivate', 'deactivateMechanic')->middleware('system.permission:maintenance,edit')->name('maintenance.personnel.mechanics.deactivate');
        Route::patch('/{mechanic}/activate', 'activateMechanic')->middleware('system.permission:maintenance,edit')->name('maintenance.personnel.mechanics.activate');
        Route::post('/specializations', 'storeMechanicSpecialization')->middleware('system.permission:maintenance,edit')->name('maintenance.personnel.mechanics.specializations.store');
        Route::delete('/specializations/{specialization}', 'deleteMechanicSpecialization')->middleware('system.permission:maintenance,edit')->name('maintenance.personnel.mechanics.specializations.destroy');
    });

Route::middleware(['role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin'])
    ->controller(MechanicAttendanceController::class)
    ->prefix('mechanic-attendance')
    ->group(function () {
        Route::get('/', 'index')->name('mechanic-attendance');
        Route::post('/', 'store')->name('mechanic-attendance.store');
        Route::put('/{mechanicAttendance}', 'update')->name('mechanic-attendance.update');
        Route::delete('/{mechanicAttendance}', 'destroy')->name('mechanic-attendance.destroy');
        Route::post('/import', 'import')->name('mechanic-attendance.import');
    });

Route::redirect('/available-mechanics', '/mechanic-attendance')
    ->middleware(['role:maintenance:head,maintenance:staff,maintenance:admin,maintenance:maintenance head,maintenance:maintenance staff,maintenance:maintenance admin,admin:head,admin:admin,admin:system admin', 'system.permission:maintenance,view'])
    ->name('available-mechanics');
