<?php

use App\Http\Controllers\Warehouse\IncomingDeliveryController;
use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\ItemRequestController;
use App\Http\Controllers\Warehouse\InventoryMovementController;
use App\Http\Controllers\Warehouse\StockMovementController;
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Http\Controllers\Warehouse\WarehousePartRequestController;
use App\Http\Controllers\Warehouse\RestockingPurchaseRequestController;
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

        // Warehouse may read the approved JO authorization supporting a maintenance PR.
        // Reuse the existing controller's private-disk and approved-audit validation.
        Route::get('/warehouse/job-orders/{jobOrder}/approval-signature', [\App\Http\Controllers\Maintenance\JobOrderController::class, 'approvalSignature'])
            ->name('warehouse.job-orders.approval-signature');

        Route::get('/warehouse/item-requests', [ItemRequestController::class, 'index'])->name('warehouse.item-requests');
        Route::get('/warehouse/item-requests/{itemRequest}/verify', [ItemRequestController::class, 'verify'])->name('warehouse.item-requests.verify');
        Route::post('/warehouse/item-requests/{itemRequest}/issue', [ItemRequestController::class, 'issue'])->middleware('system.permission:warehouse,edit')->name('warehouse.item-requests.issue');
        Route::get('/warehouse/item-requests/{itemRequest}/signed-slip', [ItemRequestController::class, 'signedSlip'])->name('warehouse.item-requests.signed-slip');

        Route::controller(WarehousePartRequestController::class)
            ->prefix('part-requests')
            ->group(function () {
                Route::get('/', 'index')->name('part-requests');
                Route::post('/restocking', [RestockingPurchaseRequestController::class, 'store'])
                    ->middleware('system.permission:warehouse,edit')->name('part-requests.restock.store');
                Route::post('/job-orders/{jobOrder}/issue', 'issueJobOrder')
                    ->middleware('system.permission:warehouse,edit')
                    ->name('part-requests.job-order.issue');
                Route::post('/{purchaseRequest}/issue', 'issue')
                    ->middleware('system.permission:warehouse,edit')
                    ->name('part-requests.issue');
                Route::post('/{purchaseRequest}/send-to-purchase', 'sendToPurchase')
                    ->middleware('system.permission:warehouse,edit')
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
