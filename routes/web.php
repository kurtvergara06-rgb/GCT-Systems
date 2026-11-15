<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\MechanicAttendanceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RequestedPurchaseController;
use App\Http\Controllers\WarehousePartRequestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'Login_Register.login')->name('login');
Route::view('/register', 'Login_Register.register')->name('register');

Route::view('/maintenance-dashboard', 'Maintenance.maintenance-dashboard')
    ->name('maintenance-dashboard');

Route::controller(JobOrderController::class)
    ->prefix('job-orders')
    ->name('job-orders')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->name('.store');
        Route::put('/{jobOrder}', 'update')->name('.update');
        Route::post('/{jobOrder}/finish', 'finish')->name('.finish');
        Route::delete('/{jobOrder}', 'destroy')->name('.destroy');
    });

Route::view('/mechanic-list', 'Maintenance.mechanic-list')
    ->name('mechanic-list');

Route::view('/PMS-Scheduling', 'Maintenance.PMS-Scheduling')
    ->name('PMS-Scheduling');

Route::view('/fuel-reports', 'Maintenance.fuel-reports')
    ->name('fuel-reports');

Route::view('/settings', 'Maintenance.settings')
    ->name('settings');

Route::controller(PurchaseRequestController::class)
    ->prefix('purchase-requests')
    ->name('purchase-requests')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->name('.store');
        Route::put('/{purchaseRequest}', 'update')->name('.update');
        Route::delete('/{purchaseRequest}', 'destroy')->name('.destroy');
        Route::post('/{purchaseRequest}/approve', 'approve')->name('.approve');
        Route::post('/{purchaseRequest}/reject', 'reject')->name('.reject');
        Route::post('/{purchaseRequest}/for-purchase', 'markForPurchase')->name('.for-purchase');
        Route::post('/{purchaseRequest}/delivered', 'markDelivered')->name('.delivered');
        Route::post('/{purchaseRequest}/issue', 'issue')->name('.issue');
    });

Route::controller(InventoryController::class)
    ->prefix('inventory')
    ->name('inventory')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->name('.store');
        Route::put('/{inventoryItem}', 'update')->name('.update');
        Route::delete('/{inventoryItem}', 'destroy')->name('.destroy');
        Route::post('/import', 'import')->name('.import');
    });

Route::controller(WarehousePartRequestController::class)
    ->prefix('part-requests')
    ->name('part-requests')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/{purchaseRequest}/issue', 'issue')->name('.issue');
        Route::post('/{purchaseRequest}/send-to-purchase', 'sendToPurchase')->name('.send-to-purchase');
    });

Route::controller(PurchaseOrderController::class)
    ->prefix('purchase-orders')
    ->name('purchase-orders')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->name('.store');
        Route::put('/{purchaseOrder}', 'update')->name('.update');
        Route::delete('/{purchaseOrder}', 'destroy')->name('.destroy');
    });

Route::controller(RequestedPurchaseController::class)
    ->prefix('requested-purchase')
    ->name('requested-purchase')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/{purchaseRequest}/create-po', 'createPo')->name('.create-po');
    });

Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')
    ->name('scheduled-purchase');

Route::view('/dashboard-operation', 'Operation.dashboard-operation')
    ->name('dashboard-operation');

Route::redirect('/attendance', '/driver-attendance')
    ->name('attendance');

Route::view('/driver-attendance', 'Operation.driver-attendance')
    ->name('driver-attendance');

Route::controller(MechanicAttendanceController::class)
    ->prefix('mechanic-attendance')
    ->name('mechanic-attendance')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->name('.store');
        Route::put('/{mechanicAttendance}', 'update')->name('.update');
        Route::delete('/{mechanicAttendance}', 'destroy')->name('.destroy');
        Route::post('/import', 'import')->name('.import');
    });

Route::redirect('/available-mechanics', '/mechanic-attendance')
    ->name('available-mechanics');

Route::patch('/purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])
    ->name('purchase-orders.update-status');