
<?php

use App\Http\Controllers\JobOrderController;

use Illuminate\Support\Facades\Route;

// Login //
Route::view('/', 'Login_Register.login')->name('login');

Route::view('/register', 'Login_Register.register')->name('register');

// Maintenance //
Route::view('/dashboard', 'Maintenance.dashboard-maintenance')->name('dashboard-maintenance');


// jobOrders //
Route::get('/job-orders', [JobOrderController::class, 'index'])->name('job-orders');
Route::post('/job-orders', [JobOrderController::class, 'store'])->name('job-orders.store');
Route::put('/job-orders/{jobOrder}', [JobOrderController::class, 'update'])->name('job-orders.update');
Route::delete('/job-orders/{jobOrder}', [JobOrderController::class, 'destroy'])->name('job-orders.destroy');


// Mechanic List //
Route::view('/mechanic-list', 'Maintenance.mechanic-list')->name('mechanic-list');
// PMS Scheduling //
Route::view('/PMS-Scheduling', 'Maintenance.PMS-Scheduling')->name('PMS-Scheduling');
// Purchase Requests //
Route::view('/purchase-requests', 'Maintenance.purchase-request')->name('purchase-requests');
// Fuel Reports //
Route::view('/fuel-reports', 'Maintenance.fuel-reports')->name('fuel-reports');
// Settings //
Route::view('/settings', 'Maintenance.settings')->name('settings');


// Warehouse //

// Inventory //
Route::view('/Inventory', 'Warehouse.Inventory')->name('Inventory');
// Part Requests //
Route::view('/part-requests', 'Warehouse.Part-Requests')->name('part-requests');

// Purchase //

// Purchase Orders //
Route::view('/purchase-orders', 'Purchase.purchase-orders')->name('purchase-orders');
// Requested Purchase //
Route::view('/requested-purchase', 'Purchase.requested-purchase')->name('requested-purchase');
// Scheduled Purchase //
Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')->name('scheduled-purchase');

// Operations //

// Dashboard //
Route::view('/dashboard-operation', 'Operation.dashboard-operation')->name('dashboard-operation');
// Available Mechanics //
Route::view('/available-mechanics', 'Operation.available-mechanics')->name('available-mechanics');