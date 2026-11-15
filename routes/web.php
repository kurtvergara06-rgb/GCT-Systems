<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\BatchFileProcessingController;
use App\Http\Controllers\Maintenance\JobOrderController;
use App\Http\Controllers\Maintenance\PurchaseRequestController;
use App\Http\Controllers\Operation\MechanicAttendanceController;
use App\Http\Controllers\Purchase\InventoryRestockController;
use App\Http\Controllers\Purchase\MaintenanceRequestController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use App\Http\Controllers\Maintenance\MechanicListController;
use App\Http\Controllers\Maintenance\PmsSchedulingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Pages
|--------------------------------------------------------------------------
*/

Route::view('/', 'Login.login')->name('login');

Route::post('/login', [LoginController::class, 'login'])
    ->name('login.submit');

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Maintenance Department
    |--------------------------------------------------------------------------
    */

    Route::view('/maintenance-dashboard', 'Maintenance.maintenance-dashboard')
        ->name('maintenance-dashboard');

    Route::get('/mechanic-list', [MechanicListController::class, 'index'])
    ->name('mechanic-list');

    Route::controller(PmsSchedulingController::class)
    ->prefix('pms-scheduling')
    ->group(function () {
        Route::get('/', 'index')->name('PMS-Scheduling');

        Route::post('/', 'store')
            ->name('pms-schedules.store');

        Route::put('/{pmsSchedule}', 'update')
            ->name('pms-schedules.update');

        Route::delete('/{pmsSchedule}', 'destroy')
            ->name('pms-schedules.destroy');

        Route::get('/{pmsSchedule}/create-job-order', 'createJobOrder')
            ->name('pms-schedules.create-job-order');
    });

    Route::view('/fuel-reports', 'Maintenance.fuel-reports')
        ->name('fuel-reports');

    Route::view('/settings', 'Maintenance.settings')
        ->name('settings');

    /*
    |--------------------------------------------------------------------------
    | Job Orders
    |--------------------------------------------------------------------------
    */

    Route::controller(JobOrderController::class)
        ->prefix('job-orders')
        ->group(function () {
            Route::get('/', 'index')->name('job-orders');

            Route::get('/available-mechanics', 'availableMechanics')
                ->name('job-orders.available-mechanics');

            Route::post('/', 'store')->name('job-orders.store');

            Route::put('/{jobOrder}', 'update')
                ->name('job-orders.update');

            Route::post('/{jobOrder}/finish', 'finish')
                ->name('job-orders.finish');

            Route::post('/{jobOrder}/create-pr', 'createPurchaseRequest')
                ->name('job-orders.create-pr');

            Route::delete('/{jobOrder}', 'destroy')
                ->name('job-orders.destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Purchase Requests
    |--------------------------------------------------------------------------
    */

    Route::controller(PurchaseRequestController::class)
        ->prefix('purchase-requests')
        ->group(function () {
            Route::get('/', 'index')->name('purchase-requests');
            Route::post('/', 'store')->name('purchase-requests.store');
            Route::put('/{purchaseRequest}', 'update')->name('purchase-requests.update');
            Route::delete('/{purchaseRequest}', 'destroy')->name('purchase-requests.destroy');

            Route::post('/{purchaseRequest}/approve', 'approve')
                ->name('purchase-requests.approve');

            Route::post('/{purchaseRequest}/reject', 'reject')
                ->name('purchase-requests.reject');

            Route::post('/{purchaseRequest}/for-purchase', 'markForPurchase')
                ->name('purchase-requests.for-purchase');

            Route::post('/{purchaseRequest}/delivered', 'markDelivered')
                ->name('purchase-requests.delivered');

            Route::post('/{purchaseRequest}/issue', 'issue')
                ->name('purchase-requests.issue');
        });

    /*
    |--------------------------------------------------------------------------
    | Warehouse Inventory
    |--------------------------------------------------------------------------
    */

    Route::controller(InventoryController::class)
        ->prefix('inventory')
        ->group(function () {
            Route::get('/', 'index')->name('inventory');
            Route::post('/', 'store')->name('inventory.store');
            Route::put('/{inventoryItem}', 'update')->name('inventory.update');
            Route::delete('/{inventoryItem}', 'destroy')->name('inventory.destroy');
            Route::post('/import', 'import')->name('inventory.import');
        });

    /*
    |--------------------------------------------------------------------------
    | Warehouse Part Requests
    |--------------------------------------------------------------------------
    */

    Route::controller(WarehousePartRequestController::class)
        ->prefix('part-requests')
        ->group(function () {
            Route::get('/', 'index')->name('part-requests');

            Route::post('/{purchaseRequest}/issue', 'issue')
                ->name('part-requests.issue');

            Route::post('/{purchaseRequest}/send-to-purchase', 'sendToPurchase')
                ->name('part-requests.send-to-purchase');
        });

    /*
    |--------------------------------------------------------------------------
    | Purchase Orders
    |--------------------------------------------------------------------------
    */

    Route::controller(PurchaseOrderController::class)
        ->prefix('purchase-orders')
        ->group(function () {
            Route::get('/', 'index')->name('purchase-orders');
            Route::post('/', 'store')->name('purchase-orders.store');
            Route::put('/{purchaseOrder}', 'update')->name('purchase-orders.update');

            Route::patch('/{purchaseOrder}/status', 'updateStatus')
                ->name('purchase-orders.update-status');

            Route::delete('/{purchaseOrder}', 'destroy')
                ->name('purchase-orders.destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Purchase Maintenance Requests
    |--------------------------------------------------------------------------
    */

    Route::controller(MaintenanceRequestController::class)
        ->prefix('maintenance-requests')
        ->group(function () {
            Route::get('/', 'index')->name('maintenance-requests');

            Route::post('/{maintenanceRequest}/create-po', 'createPo')
                ->name('maintenance-requests.create-po');
        });

    /*
    |--------------------------------------------------------------------------
    | Purchase Inventory Restock
    |--------------------------------------------------------------------------
    */

    Route::controller(InventoryRestockController::class)
        ->prefix('inventory-restock')
        ->group(function () {
            Route::get('/', 'index')->name('inventory-restock');
        });

    Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')
        ->name('scheduled-purchase');

    /*
    |--------------------------------------------------------------------------
    | Operation Department
    |--------------------------------------------------------------------------
    */

    Route::view('/dashboard-operation', 'Operation.dashboard-operation')
        ->name('dashboard-operation');

    Route::redirect('/attendance', '/driver-attendance')
        ->name('attendance');

    Route::view('/driver-attendance', 'Operation.driver-attendance')
        ->name('driver-attendance');

    Route::redirect('/available-mechanics', '/mechanic-attendance')
        ->name('available-mechanics');

    Route::controller(MechanicAttendanceController::class)
        ->prefix('mechanic-attendance')
        ->group(function () {
            Route::get('/', 'index')->name('mechanic-attendance');
            Route::post('/', 'store')->name('mechanic-attendance.store');

            Route::put('/{mechanicAttendance}', 'update')
                ->name('mechanic-attendance.update');

            Route::delete('/{mechanicAttendance}', 'destroy')
                ->name('mechanic-attendance.destroy');

            Route::post('/import', 'import')
                ->name('mechanic-attendance.import');
        });

    /*
    |--------------------------------------------------------------------------
    | Admin Department
    |--------------------------------------------------------------------------
    */

    Route::view('/admin/dashboard', 'Admin.admin-dashboard')
        ->name('admin.dashboard');

    Route::view('/admin/permissions', 'Admin.permissions')
        ->name('admin.permissions');

    Route::controller(AdminUserController::class)
        ->prefix('admin/users')
        ->group(function () {
            Route::get('/', 'index')->name('admin.users');
            Route::post('/', 'store')->name('admin.users.store');
            Route::put('/{user}', 'update')->name('admin.users.update');

            Route::patch('/{user}/status', 'updateStatus')
                ->name('admin.users.update-status');

            Route::patch('/{user}/reset-password', 'resetPassword')
                ->name('admin.users.reset-password');

            Route::delete('/{user}', 'destroy')
                ->name('admin.users.destroy');
        });

    Route::controller(BatchFileProcessingController::class)
        ->prefix('batch-file-processing')
        ->group(function () {
            Route::get('/', 'index')->name('batch-file-processing');
            Route::post('/upload', 'upload')->name('batch-file-processing.upload');
            Route::get('/export', 'export')->name('batch-file-processing.export');

            Route::delete('/{batchUpload}', 'destroy')
                ->name('batch-file-processing.destroy');

            Route::patch('/{batchUpload}/confirm', 'confirm')
                ->name('batch-file-processing.confirm');

            Route::put('/records/{gpsTripRecord}', 'updateRecord')
                ->name('batch-file-processing.records.update');

            Route::put('/{batchUpload}/records/bulk-update', 'bulkUpdateRecords')
                ->name('batch-file-processing.records.bulk-update');
        });
});