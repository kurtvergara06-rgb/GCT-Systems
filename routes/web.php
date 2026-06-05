<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\WarehousePartRequestController;
use App\Http\Controllers\InventoryController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'Login_Register.login')
    ->name('login');

Route::view('/register', 'Login_Register.register')
    ->name('register');


/*
|--------------------------------------------------------------------------
| Maintenance Module
|--------------------------------------------------------------------------
*/

Route::view('/maintenance-dashboard', 'Maintenance.maintenance-dashboard')
    ->name('maintenance-dashboard');

Route::controller(JobOrderController::class)->group(function () {
    Route::get('/job-orders', 'index')
        ->name('job-orders');

    Route::post('/job-orders', 'store')
        ->name('job-orders.store');

    Route::put('/job-orders/{jobOrder}', 'update')
        ->name('job-orders.update');

    Route::post('/job-orders/{jobOrder}/finish', 'finish')
        ->name('job-orders.finish');

    Route::delete('/job-orders/{jobOrder}', 'destroy')
        ->name('job-orders.destroy');
});

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
| Maintenance - Purchase Requests
|--------------------------------------------------------------------------
*/

Route::controller(PurchaseRequestController::class)->group(function () {
    Route::get('/purchase-requests', 'index')
        ->name('purchase-requests');

    Route::post('/purchase-requests', 'store')
        ->name('purchase-requests.store');

    Route::put('/purchase-requests/{purchaseRequest}', 'update')
        ->name('purchase-requests.update');

    Route::delete('/purchase-requests/{purchaseRequest}', 'destroy')
        ->name('purchase-requests.destroy');

    Route::post('/purchase-requests/{purchaseRequest}/approve', 'approve')
        ->name('purchase-requests.approve');

    Route::post('/purchase-requests/{purchaseRequest}/reject', 'reject')
        ->name('purchase-requests.reject');

    Route::post('/purchase-requests/{purchaseRequest}/for-purchase', 'markForPurchase')
        ->name('purchase-requests.for-purchase');

    Route::post('/purchase-requests/{purchaseRequest}/pending-purchase', 'markPendingPurchase')
        ->name('purchase-requests.pending-purchase');

    Route::post('/purchase-requests/{purchaseRequest}/delivering', 'markDelivering')
        ->name('purchase-requests.delivering');

    Route::post('/purchase-requests/{purchaseRequest}/delivered', 'markDelivered')
        ->name('purchase-requests.delivered');

    Route::post('/purchase-requests/{purchaseRequest}/issue', 'issue')
        ->name('purchase-requests.issue');
});


/*
|--------------------------------------------------------------------------
| Warehouse Module
|--------------------------------------------------------------------------
*/

Route::controller(InventoryController::class)->group(function () {
    Route::get('/inventory', 'index')
        ->name('inventory');

    Route::post('/inventory', 'store')
        ->name('inventory.store');

    Route::put('/inventory/{inventoryItem}', 'update')
        ->name('inventory.update');

    Route::delete('/inventory/{inventoryItem}', 'destroy')
        ->name('inventory.destroy');

    Route::post('/inventory/import', 'import')
        ->name('inventory.import');
});

Route::get('/part-requests', [WarehousePartRequestController::class, 'index'])
    ->name('part-requests');


/*
|--------------------------------------------------------------------------
| Purchase Module
|--------------------------------------------------------------------------
*/

Route::view('/purchase-orders', 'Purchase.purchase-orders')
    ->name('purchase-orders');

Route::view('/requested-purchase', 'Purchase.requested-purchase')
    ->name('requested-purchase');

Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')
    ->name('scheduled-purchase');


/*
|--------------------------------------------------------------------------
| Operation Module
|--------------------------------------------------------------------------
*/

Route::view('/dashboard-operation', 'Operation.dashboard-operation')
    ->name('dashboard-operation');

Route::redirect('/attendance', '/driver-attendance')
    ->name('attendance');

Route::view('/driver-attendance', 'Operation.driver-attendance')
    ->name('driver-attendance');

Route::view('/mechanic-attendance', 'Operation.mechanic-attendance')
    ->name('mechanic-attendance');

Route::view('/available-mechanics', 'Operation.available-mechanics')
    ->name('available-mechanics');