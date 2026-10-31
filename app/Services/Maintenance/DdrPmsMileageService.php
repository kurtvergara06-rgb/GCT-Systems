<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\BusPmsMonitoring;
use App\Models\Operation\DailyDriverReport;
use Illuminate\Support\Carbon;

/**
 * Read-only DDR mileage estimate; never treat trip distances as verified
 * odometer measurements or mix GPS and DDR in the same calculation.
 */
class DdrPmsMileageService
{
    /**
     * @return array{distance_km: float, estimated_odometer_km: float, trip_count: int}|null
     */
    public function estimate(BusPmsMonitoring $baseline): ?array
    {
        // The baseline date is an entire service day; without a completion
        // timestamp we cannot prove whether same-day trips were before PMS.
        $reports = DailyDriverReport::query()
            ->where('bus_id', $baseline->bus_id)
            ->whereDate('report_date', '>', $baseline->last_completed_pms_date->toDateString())
            ->whereDate('report_date', '<=', Carbon::now(config('app.business_timezone', 'Asia/Manila'))->toDateString())
            ->with('additionalTrips:id,daily_driver_report_id,km')
            ->get(['id', 'km']);

        if ($reports->isEmpty()) {
            return null;
        }

        $distance = 0.0;
        $trips = 0;

        foreach ($reports as $report) {
            // A missing KM value means the DDR day is incomplete for PMS
            // purposes. Do not show an understated odometer estimate.
            if ($report->km === null) {
                return null;
            }

            $distance += (float) $report->km;
            $trips++;

            foreach ($report->additionalTrips as $entry) {
                if ($entry->km === null) {
                    return null;
                }

                $distance += (float) $entry->km;
                $trips++;
            }
        }

        return [
            'distance_km' => round($distance, 2),
            'estimated_odometer_km' => round((float) $baseline->last_completed_pms_odometer_km + $distance, 2),
            'trip_count' => $trips,
        ];
    }
}
