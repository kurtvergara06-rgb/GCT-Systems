<?php

use App\Http\Controllers\Maintenance\JobOrderController;
use App\Http\Controllers\Maintenance\PurchaseRequestController;
use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\MaintenanceRequestController;
use App\Http\Controllers\Purchase\InventoryRestockController;
use App\Http\Controllers\Operation\MechanicAttendanceController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Authentication Pages
|--------------------------------------------------------------------------
*/

Route::view('/', 'Login_Register.login')
    ->name('login');

Route::view('/register', 'Login_Register.register')
    ->name('register');


/*
|--------------------------------------------------------------------------
| Maintenance Department
|--------------------------------------------------------------------------
*/

Route::view('/maintenance-dashboard', 'Maintenance.maintenance-dashboard')
    ->name('maintenance-dashboard');

Route::view('/mechanic-list', 'Maintenance.mechanic-list')
    ->name('mechanic-list');

Route::view('/PMS-Scheduling', 'Maintenance.PMS-Scheduling')
    ->name('PMS-Scheduling');

Route::view('/fuel-reports', 'Maintenance.fuel-reports')
    ->name('fuel-reports');

Route::view('/settings', 'Maintenance.settings')
    ->name('settings');


/*
|--------------------------------------------------------------------------
| Maintenance Department - Job Orders
|--------------------------------------------------------------------------
*/

Route::controller(JobOrderController::class)
    ->prefix('job-orders')
    ->group(function () {
        Route::get('/', 'index')->name('job-orders');
        Route::post('/', 'store')->name('job-orders.store');
        Route::put('/{jobOrder}', 'update')->name('job-orders.update');
        Route::post('/{jobOrder}/finish', 'finish')->name('job-orders.finish');
        Route::post('/{jobOrder}/create-pr', 'createPurchaseRequest')->name('job-orders.create-pr');
        Route::delete('/{jobOrder}', 'destroy')->name('job-orders.destroy');
    });


/*
|--------------------------------------------------------------------------
| Maintenance Department - Purchase Requests
|--------------------------------------------------------------------------
| This is the Maintenance-side PR page.
|--------------------------------------------------------------------------
*/

Route::controller(PurchaseRequestController::class)
    ->prefix('purchase-requests')
    ->group(function () {
        Route::get('/', 'index')->name('purchase-requests');
        Route::post('/', 'store')->name('purchase-requests.store');
        Route::put('/{purchaseRequest}', 'update')->name('purchase-requests.update');
        Route::delete('/{purchaseRequest}', 'destroy')->name('purchase-requests.destroy');

        Route::post('/{purchaseRequest}/approve', 'approve')->name('purchase-requests.approve');
        Route::post('/{purchaseRequest}/reject', 'reject')->name('purchase-requests.reject');
        Route::post('/{purchaseRequest}/for-purchase', 'markForPurchase')->name('purchase-requests.for-purchase');
        Route::post('/{purchaseRequest}/delivered', 'markDelivered')->name('purchase-requests.delivered');
        Route::post('/{purchaseRequest}/issue', 'issue')->name('purchase-requests.issue');
    });


/*
|--------------------------------------------------------------------------
| Warehouse Department - Inventory
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
| Warehouse Department - Part Requests
|--------------------------------------------------------------------------
*/

Route::controller(WarehousePartRequestController::class)
    ->prefix('part-requests')
    ->group(function () {
        Route::get('/', 'index')->name('part-requests');
        Route::post('/{purchaseRequest}/issue', 'issue')->name('part-requests.issue');
        Route::post('/{purchaseRequest}/send-to-purchase', 'sendToPurchase')->name('part-requests.send-to-purchase');
    });


/*
|--------------------------------------------------------------------------
| Purchase Department - Purchase Orders
|--------------------------------------------------------------------------
*/

Route::controller(PurchaseOrderController::class)
    ->prefix('purchase-orders')
    ->group(function () {
        Route::get('/', 'index')->name('purchase-orders');
        Route::post('/', 'store')->name('purchase-orders.store');
        Route::put('/{purchaseOrder}', 'update')->name('purchase-orders.update');
        Route::patch('/{purchaseOrder}/status', 'updateStatus')->name('purchase-orders.update-status');
        Route::delete('/{purchaseOrder}', 'destroy')->name('purchase-orders.destroy');
    });


/*
|--------------------------------------------------------------------------
| Purchase Department - Maintenance Requests
|--------------------------------------------------------------------------
| This is the child page under Requested Purchase.
| Old name was requested-purchase.
|--------------------------------------------------------------------------
*/

Route::controller(MaintenanceRequestController::class)
    ->prefix('maintenance-requests')
    ->group(function () {
        Route::get('/', 'index')->name('maintenance-requests');
        Route::post('/{maintenanceRequest}/create-po', 'createPo')->name('maintenance-requests.create-po');
    });


/*
|--------------------------------------------------------------------------
| Purchase Department - Inventory Restock
|--------------------------------------------------------------------------
| This is the child page under Requested Purchase.
|--------------------------------------------------------------------------
*/

Route::controller(InventoryRestockController::class)
    ->prefix('inventory-restock')
    ->group(function () {
        Route::get('/', 'index')->name('inventory-restock');
    });


/*
|--------------------------------------------------------------------------
| Purchase Department - Scheduled Purchase
|--------------------------------------------------------------------------
*/

Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')
    ->name('scheduled-purchase');


/*
|--------------------------------------------------------------------------
| Operations Department
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


/*
|--------------------------------------------------------------------------
| Operations Department - Mechanic Attendance
|--------------------------------------------------------------------------
*/

Route::controller(MechanicAttendanceController::class)
    ->prefix('mechanic-attendance')
    ->group(function () {
        Route::get('/', 'index')->name('mechanic-attendance');
        Route::post('/', 'store')->name('mechanic-attendance.store');
        Route::put('/{mechanicAttendance}', 'update')->name('mechanic-attendance.update');
        Route::delete('/{mechanicAttendance}', 'destroy')->name('mechanic-attendance.destroy');
        Route::post('/import', 'import')->name('mechanic-attendance.import');
    });


/*
|--------------------------------------------------------------------------
| Admin Department
|--------------------------------------------------------------------------
*/

Route::view('/admin-dashboard', 'Admin.admin-dashboard')
    ->name('admin-dashboard');