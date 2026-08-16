<?php

use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use Illuminate\Support\Facades\Route;

Route::view(
    '/warehouse/dashboard',
    'Warehouse.dashboard-warehouse'
)->name('warehouse.dashboard');

Route::controller(InventoryController::class)
    ->prefix('inventory')
    ->group(function () {
        Route::get('/', 'index')->name('inventory');
        Route::post('/', 'store')->name('inventory.store');
        Route::put('/{inventoryItem}', 'update')->name('inventory.update');
        Route::delete('/{inventoryItem}', 'destroy')->name('inventory.destroy');
        Route::post('/import', 'import')->name('inventory.import');
    });

Route::controller(WarehousePartRequestController::class)
    ->prefix('part-requests')
    ->group(function () {
        Route::get('/', 'index')->name('part-requests');
        Route::post('/{purchaseRequest}/issue', 'issue')->name('part-requests.issue');
        Route::post('/{purchaseRequest}/send-to-purchase', 'sendToPurchase')
            ->name('part-requests.send-to-purchase');
    });

Route::view(
    '/warehouse/stock-movements',
    'Warehouse.stock-movements'
)->name('stock-movements');

Route::view(
    '/warehouse/incoming-deliveries',
    'Warehouse.incoming-deliveries'
)->name('incoming-deliveries');
