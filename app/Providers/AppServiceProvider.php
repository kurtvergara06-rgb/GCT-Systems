<?php

namespace App\Providers;

use App\Models\Maintenance\JobOrder;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        JobOrder::updating(function (JobOrder $jobOrder): void {
            $isBeingCompleted =
                $jobOrder->isDirty('status')
                && $jobOrder->status === 'Completed';

            $hasRequestedParts =
                filled($jobOrder->part_needed);

            $partsWereRejected =
                $jobOrder->part_status === 'Rejected';

            if (
                $isBeingCompleted
                && $hasRequestedParts
                && $partsWereRejected
            ) {
                throw ValidationException::withMessages([
                    'part_status' =>
                        'This Job Order cannot be finished because its Purchase Request was rejected. Revise and resubmit the request, then wait until the required parts are issued.',
                ]);
            }
        });
    }
}
