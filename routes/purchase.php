<?php

use App\Http\Controllers\Purchase\InventoryRestockController;
use App\Http\Controllers\Purchase\MaintenanceRequestController;
use App\Http\Controllers\Purchase\PurchaseDashboardController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Purchase\ScheduledPurchaseController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:purchase:head,purchase:staff,admin:head')->group(function () {
    Route::middleware('system.permission:purchase,view')->group(function () {
        Route::get('/purchase/dashboard', [PurchaseDashboardController::class, 'index'])
            ->name('dashboard-purchase');

        Route::controller(MaintenanceRequestController::class)
            ->prefix('maintenance-requests')
            ->group(function () {
                Route::get('/', 'index')->name('maintenance-requests');
                Route::post('/{maintenanceRequest}/create-po', 'createPo')
                    ->middleware('system.permission:purchase,edit')
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
                Route::post('/', 'store')->middleware('system.permission:purchase,edit')->name('purchase-orders.store');
                Route::put('/{purchaseOrder}', 'update')->middleware('system.permission:purchase,edit')->name('purchase-orders.update');
                Route::delete('/{purchaseOrder}', 'destroy')->middleware('system.permission:purchase,edit')->name('purchase-orders.destroy');
            });

        Route::controller(ScheduledPurchaseController::class)
            ->prefix('scheduled-purchase')
            ->group(function () {
                Route::get('/', 'index')->name('scheduled-purchase');
                Route::post('/', 'store')->middleware('system.permission:purchase,edit')->name('scheduled-purchase.store');
                Route::put('/{scheduledPurchase}', 'update')->middleware('system.permission:purchase,edit')->name('scheduled-purchase.update');
                Route::patch('/{scheduledPurchase}/toggle-status', 'toggleStatus')
                    ->middleware('system.permission:purchase,edit')
                    ->name('scheduled-purchase.toggle-status');
                Route::patch('/{scheduledPurchase}/complete', 'complete')
                    ->middleware('system.permission:purchase,edit')
                    ->name('scheduled-purchase.complete');
                Route::post('/{scheduledPurchase}/create-po', 'createPo')
                    ->middleware('system.permission:purchase,edit')
                    ->name('scheduled-purchase.create-po');
                Route::delete('/{scheduledPurchase}', 'destroy')->middleware('system.permission:purchase,edit')->name('scheduled-purchase.destroy');
            });
    });
});

Route::patch('/purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])
    ->middleware([
        'role:purchase:head,purchase:staff,admin:head',
        'system.permission:purchase,edit',
    ])
    ->name('purchase-orders.update-status');
