<?php

namespace App\Providers;

use App\Http\Controllers\Purchase\PurchaseDashboardController;
use App\Http\Controllers\Warehouse\IncomingDeliveryController;
use App\Http\Controllers\Warehouse\StockMovementController;
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Models\Maintenance\JobOrder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('Warehouse.dashboard-warehouse', function ($view): void {
            $view->with(app(WarehouseDashboardController::class)->data());
        });

        View::composer('Warehouse.incoming-deliveries', function ($view): void {
            $view->with(app(IncomingDeliveryController::class)->data(request()));
        });

        View::composer('Warehouse.stock-movements', function ($view): void {
            $view->with(app(StockMovementController::class)->data(request()));
        });

        View::composer('Purchase.dashboard-purchase', function ($view): void {
            $view->with(app(PurchaseDashboardController::class)->data());
        });

        JobOrder::updating(function (JobOrder $jobOrder): void {
            $isBeingCompleted =
                $jobOrder->isDirty('status')
                && $jobOrder->status === 'Completed';

            $hasRequestedParts = filled($jobOrder->part_needed);
            $partsWereRejected = $jobOrder->part_status === 'Rejected';

            if ($isBeingCompleted && $hasRequestedParts && $partsWereRejected) {
                throw ValidationException::withMessages([
                    'part_status' =>
                        'This Job Order cannot be finished because its Purchase Request was rejected. Revise and resubmit the request, then wait until the required parts are issued.',
                ]);
            }
        });
    }
}
