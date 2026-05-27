<?php

use Illuminate\Support\Facades\Route;

// ROUTE FOR LOGIN //
Route::get('/', function () {
    return view('Login_Register.login');
})->name('login');

Route::get('/register', function () {
    return view('Login_Register.register');
})->name('register');

       //  Maintenance routes //

Route::get('/dashboard', function () {
    return view('Maintenance.dashboard');
})->name('dashboard');

Route::get('/job-orders', function () {
    return view('Maintenance.job-order');
})->name('job-orders');

Route::get('/mechanic-list', function () {
    return view('Maintenance.mechanic-list');
})->name('mechanic-list');

Route::get('/PMS-Scheduling', function () {
    return view('Maintenance.PMS-Scheduling');
})->name('PMS-Scheduling');

Route::get('/purchase-requests', function () {
    return view('Maintenance.purchase-request');
})->name('purchase-requests');

Route::get('/fuel-reports', function () {
    return view('Maintenance.fuel-reports');
})->name('fuel-reports');

Route::get('/settings', function () {
    return view('Maintenance.settings');
})->name('settings');


// Warehouse Routes //

Route::get('/Inventory', function () {
    return view('Warehouse.Inventory');
})->name('Inventory');

Route::get('/part-requests', function () {
    return view('Warehouse.Part-Requests');
})->name('part-requests');

