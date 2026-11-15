<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\Bus;
use App\Models\Maintenance\PmsSchedule;

class PmsScheduleSynchronizer
{
    private const DEFAULT_TASKS = [
        'Change Oil' => 5000,
        'Oil Filter' => 5000,
        'Brake Check' => 10000,
        'Air Filter' => 10000,
    ];

    public function ensureDefaultsFor(Bus $bus): void
    {
        $lastPmsKm = (float) ($bus->last_pms_km ?? 0);

        foreach (self::DEFAULT_TASKS as $maintenanceType => $interval) {
            PmsSchedule::firstOrCreate(
                [
                    'bus_no' => $bus->bus_no,
                    'maintenance_type' => $maintenanceType,
                ],
                [
                    'last_pms_km' => $lastPmsKm,
                    'pms_interval_km' => $interval,
                    'next_pms_km' => $lastPmsKm + $interval,
                    'recommended_date' => null,
                ]
            );
        }
    }

    public function renameBus(string $oldBusNo, Bus $bus): void
    {
        if (strcasecmp(trim($oldBusNo), trim($bus->bus_no)) !== 0) {
            PmsSchedule::query()
                ->where('bus_no', $oldBusNo)
                ->update(['bus_no' => $bus->bus_no]);
        }

        $this->ensureDefaultsFor($bus);
    }

    public function removeUnusedSchedulesFor(string $busNo): void
    {
        PmsSchedule::query()
            ->where('bus_no', $busNo)
            ->get()
            ->each(function (PmsSchedule $schedule): void {
                if (! $schedule->jobOrders()->exists()) {
                    $schedule->delete();
                }
            });
    }
}
