<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\BatchFileProcessingController;

use App\Http\Controllers\Auth\LoginController;

use App\Http\Controllers\Maintenance\FuelReportController;
use App\Http\Controllers\Maintenance\JobOrderController;
use App\Http\Controllers\Maintenance\MechanicListController;
use App\Http\Controllers\Maintenance\PmsSchedulingController;
use App\Http\Controllers\Maintenance\PurchaseRequestController;

use App\Http\Controllers\Operation\BusController;
use App\Http\Controllers\Operation\DriverAttendanceController;
use App\Http\Controllers\Operation\MechanicAttendanceController;
use App\Http\Controllers\Operation\RouteController;
use App\Http\Controllers\Operation\TripAssignmentController;
use App\Http\Controllers\Operation\TripScheduleController;
use App\Http\Controllers\Operation\AutoSchedulingController; 

use App\Http\Controllers\Purchase\InventoryRestockController;
use App\Http\Controllers\Purchase\MaintenanceRequestController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\ScheduledPurchaseController;

use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use App\Http\Controllers\TopbarController;

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::view(
    '/',
    'Login.login'
)->name('login');


Route::post(
    '/login',
    [LoginController::class, 'login']
)->name('login.submit');


Route::post(
    '/logout',
    [LoginController::class, 'logout']
)
    ->middleware('auth')
    ->name('logout');


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get(
        '/topbar/summary',
        [TopbarController::class, 'summary']
    )->name('topbar.summary');

    Route::post(
        '/topbar/notifications/read-all',
        [TopbarController::class, 'markAllNotificationsRead']
    )->name('topbar.notifications.read-all');

    /*
    |--------------------------------------------------------------------------
    | MAINTENANCE DEPARTMENT
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/maintenance-dashboard',
        'Maintenance.maintenance-dashboard'
    )->name('maintenance-dashboard');


    /*
    |--------------------------------------------------------------------------
    | Mechanic List
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/mechanic-list',
        [MechanicListController::class, 'index']
    )->name('mechanic-list');


    /*
    |--------------------------------------------------------------------------
    | PMS Scheduling
    |--------------------------------------------------------------------------
    */

    Route::controller(PmsSchedulingController::class)
        ->prefix('pms-scheduling')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name('PMS-Scheduling');


            Route::post(
                '/',
                'store'
            )->name('pms-schedules.store');


            Route::put(
                '/{pmsSchedule}',
                'update'
            )->name('pms-schedules.update');


            Route::delete(
                '/{pmsSchedule}',
                'destroy'
            )->name('pms-schedules.destroy');


            Route::get(
                '/{pmsSchedule}/create-job-order',
                'createJobOrder'
            )->name(
                'pms-schedules.create-job-order'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Fuel Reports
    |--------------------------------------------------------------------------
    */

    Route::controller(FuelReportController::class)
        ->prefix('fuel-reports')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name('fuel-reports');


            Route::get(
                '/gps-distance',
                'gpsDistance'
            )->name(
                'fuel-reports.gps-distance'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'fuel-reports.store'
            );


            Route::put(
                '/{fuelReport}',
                'update'
            )->name(
                'fuel-reports.update'
            );


            Route::delete(
                '/{fuelReport}',
                'destroy'
            )->name(
                'fuel-reports.destroy'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Job Orders
    |--------------------------------------------------------------------------
    */

    Route::controller(JobOrderController::class)
        ->prefix('job-orders')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name('job-orders');


            Route::get(
                '/available-mechanics',
                'availableMechanics'
            )->name(
                'job-orders.available-mechanics'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'job-orders.store'
            );


            Route::put(
                '/{jobOrder}',
                'update'
            )->name(
                'job-orders.update'
            );


            Route::post(
                '/{jobOrder}/finish',
                'finish'
            )->name(
                'job-orders.finish'
            );


            Route::post(
                '/{jobOrder}/create-pr',
                'createPurchaseRequest'
            )->name(
                'job-orders.create-pr'
            );


            Route::delete(
                '/{jobOrder}',
                'destroy'
            )->name(
                'job-orders.destroy'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Maintenance Purchase Requests
    |--------------------------------------------------------------------------
    */

    Route::controller(PurchaseRequestController::class)
        ->prefix('purchase-requests')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | LIST
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/',
                'index'
            )->name(
                'purchase-requests'
            );


            /*
            |--------------------------------------------------------------------------
            | CREATE
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/',
                'store'
            )->name(
                'purchase-requests.store'
            );


            /*
            |--------------------------------------------------------------------------
            | NORMAL EDIT
            |
            | Only Submitted PRs use this.
            |--------------------------------------------------------------------------
            */

            Route::put(
                '/{purchaseRequest}',
                'update'
            )->name(
                'purchase-requests.update'
            );


            /*
            |--------------------------------------------------------------------------
            | REVISE + RESUBMIT
            |
            | Only Rejected PRs use this.
            | Same PR record and same PR number.
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/{purchaseRequest}/resubmit',
                'resubmit'
            )->name(
                'purchase-requests.resubmit'
            );


            /*
            |--------------------------------------------------------------------------
            | DELETE
            |--------------------------------------------------------------------------
            */

            Route::delete(
                '/{purchaseRequest}',
                'destroy'
            )->name(
                'purchase-requests.destroy'
            );


            /*
            |--------------------------------------------------------------------------
            | APPROVE
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/{purchaseRequest}/approve',
                'approve'
            )->name(
                'purchase-requests.approve'
            );


            /*
            |--------------------------------------------------------------------------
            | REJECT
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/{purchaseRequest}/reject',
                'reject'
            )->name(
                'purchase-requests.reject'
            );


            /*
            |--------------------------------------------------------------------------
            | FOR PURCHASE
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/{purchaseRequest}/for-purchase',
                'markForPurchase'
            )->name(
                'purchase-requests.for-purchase'
            );


            /*
            |--------------------------------------------------------------------------
            | DELIVERED
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/{purchaseRequest}/delivered',
                'markDelivered'
            )->name(
                'purchase-requests.delivered'
            );


            /*
            |--------------------------------------------------------------------------
            | ISSUED
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/{purchaseRequest}/issue',
                'issue'
            )->name(
                'purchase-requests.issue'
            );

        });


    /*
|--------------------------------------------------------------------------
| WAREHOUSE DEPARTMENT
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Warehouse Dashboard
    |--------------------------------------------------------------------------
    */

    Route::view(
    '/warehouse/dashboard',
    'Warehouse.dashboard-warehouse'
    )->name('warehouse.dashboard');


    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    Route::controller(InventoryController::class)
        ->prefix('inventory')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'inventory'
            );

            Route::post(
                '/',
                'store'
            )->name(
                'inventory.store'
            );

            Route::put(
                '/{inventoryItem}',
                'update'
            )->name(
                'inventory.update'
            );

            Route::delete(
                '/{inventoryItem}',
                'destroy'
            )->name(
                'inventory.destroy'
            );

            Route::post(
                '/import',
                'import'
            )->name(
                'inventory.import'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Warehouse Part Requests
    |--------------------------------------------------------------------------
    */

    Route::controller(
        WarehousePartRequestController::class
    )
        ->prefix('part-requests')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'part-requests'
            );

            Route::post(
                '/{purchaseRequest}/issue',
                'issue'
            )->name(
                'part-requests.issue'
            );

            Route::post(
                '/{purchaseRequest}/send-to-purchase',
                'sendToPurchase'
            )->name(
                'part-requests.send-to-purchase'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Stock Movements
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/warehouse/stock-movements',
        'Warehouse.stock-movements'
    )->name(
        'stock-movements'
    );


    /*
    |--------------------------------------------------------------------------
    | Incoming Deliveries
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/warehouse/incoming-deliveries',
        'Warehouse.incoming-deliveries'
    )->name(
        'incoming-deliveries'
    );

});


    /*
    |--------------------------------------------------------------------------
    | PURCHASE DEPARTMENT
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Purchase Dashboard
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/purchase/dashboard',
        'Purchase.dashboard-purchase'
    )->name(
        'dashboard-purchase'
    );


    /*
    |--------------------------------------------------------------------------
    | Requested Purchase - Maintenance Requests
    |--------------------------------------------------------------------------
    */

    Route::controller(
        MaintenanceRequestController::class
    )
        ->prefix('maintenance-requests')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'maintenance-requests'
            );


            Route::post(
                '/{maintenanceRequest}/create-po',
                'createPo'
            )->name(
                'maintenance-requests.create-po'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Requested Purchase - Inventory Restock
    |--------------------------------------------------------------------------
    */

    Route::controller(
        InventoryRestockController::class
    )
        ->prefix('inventory-restock')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'inventory-restock'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Purchase Orders
    |--------------------------------------------------------------------------
    */

    Route::controller(
        PurchaseOrderController::class
    )
        ->prefix('purchase-orders')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'purchase-orders'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'purchase-orders.store'
            );


            Route::put(
                '/{purchaseOrder}',
                'update'
            )->name(
                'purchase-orders.update'
            );


            Route::patch(
                '/{purchaseOrder}/status',
                'updateStatus'
            )->name(
                'purchase-orders.update-status'
            );


            Route::delete(
                '/{purchaseOrder}',
                'destroy'
            )->name(
                'purchase-orders.destroy'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Scheduled Purchase
    |--------------------------------------------------------------------------
    */

    Route::controller(
        ScheduledPurchaseController::class
    )
        ->prefix('scheduled-purchase')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'scheduled-purchase'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'scheduled-purchase.store'
            );


            Route::put(
                '/{scheduledPurchase}',
                'update'
            )->name(
                'scheduled-purchase.update'
            );


            Route::patch(
                '/{scheduledPurchase}/toggle-status',
                'toggleStatus'
            )->name(
                'scheduled-purchase.toggle-status'
            );


            Route::patch(
                '/{scheduledPurchase}/complete',
                'complete'
            )->name(
                'scheduled-purchase.complete'
            );


            Route::post(
                '/{scheduledPurchase}/create-po',
                'createPo'
            )->name(
                'scheduled-purchase.create-po'
            );


            Route::delete(
                '/{scheduledPurchase}',
                'destroy'
            )->name(
                'scheduled-purchase.destroy'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | OPERATION DEPARTMENT
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Operation Dashboard
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/operation/dashboard',
        'Operation.dashboard-operation'
    )->name(
        'dashboard-operation'
    );


    /*
    |--------------------------------------------------------------------------
    | Bus Master List
    |--------------------------------------------------------------------------
    */

    Route::controller(BusController::class)
        ->prefix('bus-master-list')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'bus-master-list'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'bus-master-list.store'
            );


            Route::post(
                '/import',
                'import'
            )->name(
                'bus-master-list.import'
            );


            Route::put(
                '/{bus}',
                'update'
            )->name(
                'bus-master-list.update'
            );


            Route::delete(
                '/{bus}',
                'destroy'
            )->name(
                'bus-master-list.destroy'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Driver Attendance
    |--------------------------------------------------------------------------
    */

    Route::controller(
        DriverAttendanceController::class
    )
        ->prefix('driver-attendance')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'driver-attendance'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'driver-attendance.store'
            );


            Route::post(
                '/import',
                'import'
            )->name(
                'driver-attendance.import'
            );


            Route::put(
                '/{driverAttendance}',
                'update'
            )->name(
                'driver-attendance.update'
            );


            Route::delete(
                '/{driverAttendance}',
                'destroy'
            )->name(
                'driver-attendance.destroy'
            );

        });


    Route::redirect(
        '/attendance',
        '/driver-attendance'
    )->name(
        'attendance'
    );


    /*
    |--------------------------------------------------------------------------
    | Mechanic Attendance
    |--------------------------------------------------------------------------
    */

    Route::controller(
        MechanicAttendanceController::class
    )
        ->prefix('mechanic-attendance')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'mechanic-attendance'
            );


            Route::post(
                '/',
                'store'
            )->name(
                'mechanic-attendance.store'
            );


            Route::put(
                '/{mechanicAttendance}',
                'update'
            )->name(
                'mechanic-attendance.update'
            );


            Route::delete(
                '/{mechanicAttendance}',
                'destroy'
            )->name(
                'mechanic-attendance.destroy'
            );


            Route::post(
                '/import',
                'import'
            )->name(
                'mechanic-attendance.import'
            );

        });


    Route::redirect(
        '/available-mechanics',
        '/mechanic-attendance'
    )->name(
        'available-mechanics'
    );


/*
|--------------------------------------------------------------------------
| Routes & Stops
|--------------------------------------------------------------------------
*/

Route::get(
    '/operation/routes',
    [RouteController::class, 'index']
)->name('operation.routes');

Route::post(
    '/operation/routes',
    [RouteController::class, 'store']
)->name('operation.routes.store');

Route::put(
    '/operation/routes/{shuttleRoute}',
    [RouteController::class, 'update']
)->name('operation.routes.update');

Route::delete(
    '/operation/routes/{shuttleRoute}',
    [RouteController::class, 'destroy']
)->name('operation.routes.destroy');

/*
    |--------------------------------------------------------------------------
    | Geoapify
    |--------------------------------------------------------------------------
    */

Route::get('/operation/routes/location-search', [\App\Http\Controllers\Operation\RouteController::class, 'searchLocations'])
    ->middleware('throttle:60,1')
    ->name('operation.routes.location-search');

Route::post('/operation/routes/calculate', [\App\Http\Controllers\Operation\RouteController::class, 'calculateRoute'])
    ->middleware('throttle:60,1')
    ->name('operation.routes.calculate');


    /*
    |--------------------------------------------------------------------------
    | Trip Schedule
    |--------------------------------------------------------------------------
    */

   Route::controller(TripScheduleController::class)
    ->prefix('operation/trip-schedule')
    ->group(function () {

        Route::get(
            '/',
            'index'
        )->name('trip-schedule');

        Route::post(
            '/',
            'store'
        )->name('trip-schedule.store');

        Route::put(
            '/{tripSchedule}',
            'update'
        )->name('trip-schedule.update');

        Route::delete(
            '/{tripSchedule}',
            'destroy'
        )->name('trip-schedule.destroy');

    });


    /*
    |--------------------------------------------------------------------------
    | Driver & Bus Assignment
    |--------------------------------------------------------------------------
    */

    Route::controller(TripAssignmentController::class)
        ->prefix('operation/driver-bus-assignment')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name('driver-bus-assignment');

            Route::post(
                '/',
                'store'
            )->name('driver-bus-assignment.store');

            Route::put(
                '/{tripAssignment}',
                'update'
            )->name('driver-bus-assignment.update');

            Route::delete(
                '/{tripAssignment}',
                'destroy'
            )->name('driver-bus-assignment.destroy');

        });


   /*
|--------------------------------------------------------------------------
| Auto Scheduling
|--------------------------------------------------------------------------
*/

    Route::controller(
        AutoSchedulingController::class
    )
        ->prefix('operation/auto-scheduling')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'auto-scheduling'
            );

            Route::post(
                '/generate',
                'generate'
            )->name(
                'auto-scheduling.generate'
            );

            Route::post(
                '/confirm',
                'confirm'
            )->name(
                'auto-scheduling.confirm'
            );

        });


    Route::redirect(
        '/operation/auto-dispatch',
        '/operation/auto-scheduling'
    )->name(
        'auto-dispatch'
    );


    /*
    |--------------------------------------------------------------------------
    | Trip Records
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/operation/trip-records',
        'Operation.Trip_Records.trip-records'
    )->name(
        'trip-records'
    );


    /*use
    |--------------------------------------------------------------------------
    | ADMIN DEPARTMENT
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/admin/dashboard',
        'Admin.admin-dashboard'
    )->name(
        'admin.dashboard'
    );



/*
|--------------------------------------------------------------------------
| ADMIN - USER MANAGEMENT
|--------------------------------------------------------------------------
*/

Route::get(
    '/admin/users',
    [AdminUserController::class, 'index']
)->name('admin.users');

Route::post(
    '/admin/users',
    [AdminUserController::class, 'store']
)->name('admin.users.store');

Route::put(
    '/admin/users/{user}',
    [AdminUserController::class, 'update']
)->name('admin.users.update');

Route::delete(
    '/admin/users/{user}',
    [AdminUserController::class, 'destroy']
)->name('admin.users.destroy');

Route::post(
    '/admin/users/{user}/reset-password',
    [AdminUserController::class, 'resetPassword']
)->name('admin.users.reset-password');

Route::patch(
    '/admin/users/{user}/status',
    [AdminUserController::class, 'updateStatus']
)->name('admin.users.update-status');

Route::view(
    '/admin/roles-permissions',
    'Admin.User_Management.permissions'
)->name('admin.roles-permissions');

Route::view(
    '/admin/account-requests',
    'Admin.User_Management.account-requests'
)->name('admin.account-requests');
/*
|--------------------------------------------------------------------------
| ADMIN - SYSTEM MONITORING
|--------------------------------------------------------------------------
*/

Route::view(
    '/admin/activity-logs',
    'Admin.System_Monitoring.activity-logs'
)->name('admin.activity-logs');


Route::view(
    '/admin/notifications',
    'Admin.System_Monitoring.notifications'
)->name('admin.notifications');


    /*
    |--------------------------------------------------------------------------
    | Batch File Processing
    |--------------------------------------------------------------------------
    */

    Route::controller(
        BatchFileProcessingController::class
    )
        ->prefix('batch-file-processing')
        ->group(function () {

            Route::get(
                '/',
                'index'
            )->name(
                'batch-file-processing'
            );


            Route::post(
                '/upload',
                'upload'
            )->name(
                'batch-file-processing.upload'
            );


            Route::get(
                '/export',
                'export'
            )->name(
                'batch-file-processing.export'
            );


            Route::delete(
                '/{batchUpload}',
                'destroy'
            )->name(
                'batch-file-processing.destroy'
            );


            Route::patch(
                '/{batchUpload}/confirm',
                'confirm'
            )->name(
                'batch-file-processing.confirm'
            );


            Route::put(
                '/records/{gpsTripRecord}',
                'updateRecord'
            )->name(
                'batch-file-processing.records.update'
            );


            Route::put(
                '/{batchUpload}/records/bulk-update',
                'bulkUpdateRecords'
            )->name(
                'batch-file-processing.records.bulk-update'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Admin Batch File Processing Shortcut
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/admin/batch-file-processing',
        [BatchFileProcessingController::class, 'index']
    )->name(
        'admin.batch-file-processing'
    );


    /*
    |--------------------------------------------------------------------------
    | Data Management - Import / Export
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/admin/import-export',
        'Admin.Data_Management.uploading-data'
    )->name(
        'admin.import-export'
    );


    /*
    |--------------------------------------------------------------------------
    | Data Management - History
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/admin/data-history',
        'Admin.Data_Management.data-history'
    )->name(
        'admin.data-history'
    );


    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    */

    Route::redirect(
        '/analytics',
        '/analytics/overview'
    )->name(
        'analytics'
    );


    Route::view(
        '/analytics/overview',
        'Admin.Analytics.overview'
    )->name(
        'analytics.overview'
    );


    Route::view(
        '/analytics/fleet-trip',
        'Admin.Analytics.fleet-trip'
    )->name(
        'analytics.fleet-trip'
    );


    Route::view(
        '/analytics/fuel',
        'Admin.Analytics.fuel'
    )->name(
        'analytics.fuel'
    );


    Route::view(
        '/analytics/bus-health',
        'Admin.Analytics.bus-health'
    )->name(
        'analytics.bus-health'
    );


    Route::view(
        '/analytics/inventory',
        'Admin.Analytics.inventory'
    )->name(
        'analytics.inventory'
    );


    Route::view(
        '/analytics/recommendations',
        'Admin.Analytics.recommendations'
    )->name(
        'analytics.recommendations'
    );


    /*
    |--------------------------------------------------------------------------
    | Admin Settings
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/admin/settings/general',
        'Admin.Settings.general-settings'
    )->name(
        'admin.settings.general'
    );


    Route::view(
        '/admin/settings/notifications',
        'Admin.Settings.notification-settings'
    )->name(
        'admin.settings.notifications'
    );


    Route::view(
        '/admin/settings/security',
        'Admin.Settings.security-settings'
    )->name(
        'admin.settings.security'
    );

});
