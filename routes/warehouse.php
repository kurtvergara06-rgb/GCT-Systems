<?php

use App\Http\Controllers\Warehouse\IncomingDeliveryController;
use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\InventoryMovementController;
use App\Http\Controllers\Warehouse\StockMovementController;
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'role:warehouse:head,warehouse:staff,warehouse:warehouse head,warehouse:warehouse staff,admin:head,admin:admin,admin:system admin',
])->group(function () {
    Route::middleware('system.permission:warehouse,view')->group(function () {
        Route::get('/warehouse/dashboard', [WarehouseDashboardController::class, 'index'])
            ->name('warehouse.dashboard');

        Route::controller(InventoryController::class)
            ->prefix('inventory')
            ->group(function () {
                Route::get('/', 'index')->name('inventory');
                Route::post('/', 'store')->middleware('system.permission:warehouse,edit')->name('inventory.store');
                Route::put('/{inventoryItem}', 'update')->middleware('system.permission:warehouse,edit')->name('inventory.update');
                Route::delete('/{inventoryItem}', 'destroy')->middleware('system.permission:warehouse,edit')->name('inventory.destroy');
                Route::post('/import', 'import')->middleware('system.permission:warehouse,edit')->name('inventory.import');
                Route::post('/issue', 'issue')->middleware('system.permission:warehouse,edit')->name('inventory.issue');
            });

        Route::get('/inventory/{inventoryItem}/movements', [InventoryMovementController::class, 'show'])
            ->name('inventory.movements');

        Route::controller(WarehousePartRequestController::class)
            ->prefix('part-requests')
            ->group(function () {
                Route::get('/', 'index')->name('part-requests');
                Route::post('/{purchaseRequest}/approve-for-issue', 'approveForIssue')
                    ->middleware('system.permission:warehouse,approve')
                    ->name('part-requests.approve-for-issue');
                Route::post('/{purchaseRequest}/hold', 'hold')
                    ->middleware('system.permission:warehouse,approve')
                    ->name('part-requests.hold');
                Route::post('/{purchaseRequest}/prepare', 'prepare')
                    ->middleware('system.permission:warehouse,edit')
                    ->name('part-requests.prepare');
                Route::post('/{purchaseRequest}/issue', 'issue')
                    ->middleware('system.permission:warehouse,edit')
                    ->name('part-requests.issue');
                Route::post('/{purchaseRequest}/send-to-purchase', 'sendToPurchase')
                    ->middleware('system.permission:warehouse,approve')
                    ->name('part-requests.send-to-purchase');
            });

        Route::get('/warehouse/stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements');

        Route::controller(IncomingDeliveryController::class)
            ->prefix('warehouse/incoming-deliveries')
            ->group(function () {
                Route::get('/', 'index')->name('incoming-deliveries');
                Route::post('/{purchaseOrder}/receive', 'receive')
                    ->middleware('system.permission:warehouse,edit')
                    ->name('incoming-deliveries.receive');
            });
    });
});
