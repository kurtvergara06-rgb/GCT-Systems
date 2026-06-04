<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\WarehousePartRequestController;

/*
|--------------------------------------------------------------------------
| Login / Register Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'Login_Register.login')
    ->name('login');

Route::view('/register', 'Login_Register.register')
    ->name('register');


/*
|--------------------------------------------------------------------------
| Maintenance Module Routes
|--------------------------------------------------------------------------
*/

// Dashboard
Route::view('/maintenance-dashboard', 'Maintenance.maintenance-dashboard')
    ->name('maintenance-dashboard');

// Job Orders
Route::get('/job-orders', [JobOrderController::class, 'index'])
    ->name('job-orders');

Route::post('/job-orders', [JobOrderController::class, 'store'])
    ->name('job-orders.store');

Route::put('/job-orders/{jobOrder}', [JobOrderController::class, 'update'])
    ->name('job-orders.update');

Route::delete('/job-orders/{jobOrder}', [JobOrderController::class, 'destroy'])
    ->name('job-orders.destroy');

// Mechanic List
Route::view('/mechanic-list', 'Maintenance.mechanic-list')
    ->name('mechanic-list');

// PMS Scheduling
Route::view('/PMS-Scheduling', 'Maintenance.PMS-Scheduling')
    ->name('PMS-Scheduling');

// Purchase Requests
Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])
    ->name('purchase-requests');

Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])
    ->name('purchase-requests.store');

Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])
    ->name('purchase-requests.update');

Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])
    ->name('purchase-requests.approve');

Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])
    ->name('purchase-requests.reject');

Route::post('/purchase-requests/{purchaseRequest}/for-purchase', [PurchaseRequestController::class, 'markForPurchase'])
    ->name('purchase-requests.for-purchase');

Route::post('/purchase-requests/{purchaseRequest}/pending-purchase', [PurchaseRequestController::class, 'markPendingPurchase'])
    ->name('purchase-requests.pending-purchase');

Route::post('/purchase-requests/{purchaseRequest}/delivering', [PurchaseRequestController::class, 'markDelivering'])
    ->name('purchase-requests.delivering');

Route::post('/purchase-requests/{purchaseRequest}/delivered', [PurchaseRequestController::class, 'markDelivered'])
    ->name('purchase-requests.delivered');

Route::post('/purchase-requests/{purchaseRequest}/issue', [PurchaseRequestController::class, 'issue'])
    ->name('purchase-requests.issue');

Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])
    ->name('purchase-requests.destroy');

// Fuel Reports
Route::view('/fuel-reports', 'Maintenance.fuel-reports')
    ->name('fuel-reports');

// Settings
Route::view('/settings', 'Maintenance.settings')
    ->name('settings');


/*
|--------------------------------------------------------------------------
| Warehouse Module Routes
|--------------------------------------------------------------------------
*/

// Inventory
Route::view('/inventory', 'Warehouse.Inventory')
    ->name('inventory');

// Part Requests
Route::get('/part-requests', [WarehousePartRequestController::class, 'index'])
    ->name('part-requests');

/*
|--------------------------------------------------------------------------
| Purchase Module Routes
|--------------------------------------------------------------------------
*/

// Purchase Orders
Route::view('/purchase-orders', 'Purchase.purchase-orders')
    ->name('purchase-orders');

// Requested Purchase
Route::view('/requested-purchase', 'Purchase.requested-purchase')
    ->name('requested-purchase');

// Scheduled Purchase
Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')
    ->name('scheduled-purchase');


/*
|--------------------------------------------------------------------------
| Operation Module Routes
|--------------------------------------------------------------------------
*/

// Dashboard
Route::view('/dashboard-operation', 'Operation.dashboard-operation')
    ->name('dashboard-operation');

// Attendance main route redirects to Driver Attendance
Route::redirect('/attendance', '/driver-attendance')
    ->name('attendance');

// Driver Attendance
Route::view('/driver-attendance', 'Operation.driver-attendance')
    ->name('driver-attendance');

// Mechanic Attendance
Route::view('/mechanic-attendance', 'Operation.mechanic-attendance')
    ->name('mechanic-attendance');

// Available Mechanics
Route::view('/available-mechanics', 'Operation.available-mechanics')
    ->name('available-mechanics');