<?php

use Illuminate\Support\Facades\Route;

// Login //
Route::view('/', 'Login_Register.login')->name('login');
Route::view('/register', 'Login_Register.register')->name('register');

// Maintenance //
Route::view('/dashboard', 'Maintenance.dashboard-maintenance')->name('dashboard-maintenance');
Route::view('/job-orders', 'Maintenance.job-order')->name('job-orders');
Route::view('/mechanic-list', 'Maintenance.mechanic-list')->name('mechanic-list');
Route::view('/PMS-Scheduling', 'Maintenance.PMS-Scheduling')->name('PMS-Scheduling');
Route::view('/purchase-requests', 'Maintenance.purchase-request')->name('purchase-requests');
Route::view('/fuel-reports', 'Maintenance.fuel-reports')->name('fuel-reports');
Route::view('/settings', 'Maintenance.settings')->name('settings');

// Warehouse //
Route::view('/Inventory', 'Warehouse.Inventory')->name('Inventory');
Route::view('/part-requests', 'Warehouse.Part-Requests')->name('part-requests');

// Purchase //
Route::view('/purchase-orders', 'Purchase.purchase-orders')->name('purchase-orders');
Route::view('/requested-purchase', 'Purchase.requested-purchase')->name('requested-purchase');
Route::view('/scheduled-purchase', 'Purchase.scheduled-purchase')->name('scheduled-purchase');

// Operations //
Route::view('/dashboard-operation', 'Operation.dashboard-operation')->name('dashboard-operation');
Route::view('/available-mechanics', 'Operation.available-mechanics')->name('available-mechanics');