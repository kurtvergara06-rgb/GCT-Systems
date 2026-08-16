<?php

use App\Http\Controllers\Purchase\InventoryRestockController;
use App\Http\Controllers\Purchase\MaintenanceRequestController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\ScheduledPurchaseController;
use Illuminate\Support\Facades\Route;

Route::view(
    '/purchase/dashboard',
    'Purchase.dashboard-purchase'
)->name('dashboard-purchase');

Route::controller(MaintenanceRequestController::class)
    ->prefix('maintenance-requests')
    ->group(function () {
        Route::get('/', 'index')->name('maintenance-requests');
        Route::post('/{maintenanceRequest}/create-po', 'createPo')
            ->name('maintenance-requests.create-po');
    });

Route::controller(InventoryRestockController::class)
    ->prefix('inventory-restock')
    ->group(function () {
        Route::get('/', 'index')->name('inventory-restock');
    });

Route::controller(PurchaseOrderController::class)
    ->prefix('purchase-orders')
    ->group(function () {
        Route::get('/', 'index')->name('purchase-orders');
        Route::post('/', 'store')->name('purchase-orders.store');
        Route::put('/{purchaseOrder}', 'update')->name('purchase-orders.update');
        Route::patch('/{purchaseOrder}/status', 'updateStatus')
            ->name('purchase-orders.update-status');
        Route::delete('/{purchaseOrder}', 'destroy')->name('purchase-orders.destroy');
    });

Route::controller(ScheduledPurchaseController::class)
    ->prefix('scheduled-purchase')
    ->group(function () {
        Route::get('/', 'index')->name('scheduled-purchase');
        Route::post('/', 'store')->name('scheduled-purchase.store');
        Route::put('/{scheduledPurchase}', 'update')->name('scheduled-purchase.update');
        Route::patch('/{scheduledPurchase}/toggle-status', 'toggleStatus')
            ->name('scheduled-purchase.toggle-status');
        Route::patch('/{scheduledPurchase}/complete', 'complete')
            ->name('scheduled-purchase.complete');
        Route::post('/{scheduledPurchase}/create-po', 'createPo')
            ->name('scheduled-purchase.create-po');
        Route::delete('/{scheduledPurchase}', 'destroy')->name('scheduled-purchase.destroy');
    });
