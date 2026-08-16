<?php

use App\Http\Controllers\Maintenance\FuelReportController;
use App\Http\Controllers\Maintenance\JobOrderController;
use App\Http\Controllers\Maintenance\MechanicListController;
use App\Http\Controllers\Maintenance\PmsSchedulingController;
use App\Http\Controllers\Maintenance\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

Route::view(
    '/maintenance-dashboard',
    'Maintenance.maintenance-dashboard'
)->name('maintenance-dashboard');

Route::get(
    '/mechanic-list',
    [MechanicListController::class, 'index']
)->name('mechanic-list');

Route::controller(PmsSchedulingController::class)
    ->prefix('pms-scheduling')
    ->group(function () {
        Route::get('/', 'index')->name('PMS-Scheduling');
        Route::post('/', 'store')->name('pms-schedules.store');
        Route::put('/{pmsSchedule}', 'update')->name('pms-schedules.update');
        Route::delete('/{pmsSchedule}', 'destroy')->name('pms-schedules.destroy');
        Route::get('/{pmsSchedule}/create-job-order', 'createJobOrder')
            ->name('pms-schedules.create-job-order');
    });

Route::controller(FuelReportController::class)
    ->prefix('fuel-reports')
    ->group(function () {
        Route::get('/', 'index')->name('fuel-reports');
        Route::get('/gps-distance', 'gpsDistance')->name('fuel-reports.gps-distance');
        Route::post('/', 'store')->name('fuel-reports.store');
        Route::put('/{fuelReport}', 'update')->name('fuel-reports.update');
        Route::delete('/{fuelReport}', 'destroy')->name('fuel-reports.destroy');
    });

Route::controller(JobOrderController::class)
    ->prefix('job-orders')
    ->group(function () {
        Route::get('/', 'index')->name('job-orders');
        Route::get('/available-mechanics', 'availableMechanics')
            ->name('job-orders.available-mechanics');
        Route::post('/', 'store')->name('job-orders.store');
        Route::put('/{jobOrder}', 'update')->name('job-orders.update');
        Route::post('/{jobOrder}/finish', 'finish')->name('job-orders.finish');
        Route::post('/{jobOrder}/create-pr', 'createPurchaseRequest')
            ->name('job-orders.create-pr');
        Route::delete('/{jobOrder}', 'destroy')->name('job-orders.destroy');
    });

Route::controller(PurchaseRequestController::class)
    ->prefix('purchase-requests')
    ->group(function () {
        Route::get('/', 'index')->name('purchase-requests');
        Route::post('/', 'store')->name('purchase-requests.store');
        Route::put('/{purchaseRequest}', 'update')->name('purchase-requests.update');
        Route::post('/{purchaseRequest}/resubmit', 'resubmit')->name('purchase-requests.resubmit');
        Route::delete('/{purchaseRequest}', 'destroy')->name('purchase-requests.destroy');
        Route::post('/{purchaseRequest}/approve', 'approve')->name('purchase-requests.approve');
        Route::post('/{purchaseRequest}/reject', 'reject')->name('purchase-requests.reject');
        Route::post('/{purchaseRequest}/for-purchase', 'markForPurchase')
            ->name('purchase-requests.for-purchase');
        Route::post('/{purchaseRequest}/delivered', 'markDelivered')
            ->name('purchase-requests.delivered');
        Route::post('/{purchaseRequest}/issue', 'issue')->name('purchase-requests.issue');
    });
