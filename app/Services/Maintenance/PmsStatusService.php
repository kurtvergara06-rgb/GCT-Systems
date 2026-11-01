<?php

namespace App\Services\Maintenance;

use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\PmsSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PmsStatusService
{
    public const WARNING_RANGE_KM = 500;

    private const DEFAULT_AVERAGE_DAILY_KM = 250;

    /**
     * Return the latest processed GPS mileage snapshot for every bus.
     *
     * @return Collection<string, array{
     *     bus_no:string,
     *     current_km:float,
     *     km_traveled:float,
     *     gps_report_date:mixed
     * }>
     */
    public function latestProcessedGpsByBus(): Collection
    {
        // PMS needs only the latest and previous processed readings for each
        // bus. Rank in SQL so old GPS imports are not all hydrated in PHP.
        $rankedRecords = GpsTripRecord::query()
            ->select([
                'gps_trip_records.id',
                'gps_trip_records.bus_no',
                'gps_trip_records.mileage_km',
                'gps_trip_records.beginning_at',
                'gps_trip_records.created_at',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY UPPER(TRIM(gps_trip_records.bus_no)) '
                .'ORDER BY gps_trip_records.beginning_at DESC, gps_trip_records.id DESC) AS row_num'
            )
            ->whereNotNull('gps_trip_records.bus_no')
            ->whereNotNull('gps_trip_records.mileage_km')
            ->whereHas(
                'batchUpload',
                fn ($query) => $query->where('status', 'Processed')
            )
            ->toBase();

        $records = DB::query()
            ->fromSub($rankedRecords, 'ranked_gps')
            ->where('row_num', '<=', 2)
            ->orderByRaw('UPPER(TRIM(bus_no))')
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->get();

        return $records
            ->groupBy(fn ($record) => strtoupper(trim((string) $record->bus_no)))
            ->map(function (Collection $busRecords): array {
                $latestRecord = $busRecords->first();
                $previousRecord = $busRecords->skip(1)->first();

                $currentKm = (float) $latestRecord->mileage_km;
                $previousKm = $previousRecord
                    ? (float) $previousRecord->mileage_km
                    : null;

                return [
                    'bus_no' => (string) $latestRecord->bus_no,
                    'current_km' => $currentKm,
                    'km_traveled' => $previousKm !== null
                        ? max(0, $currentKm - $previousKm)
                        : 0,
                    'gps_report_date' => $latestRecord->beginning_at
                        ?? $latestRecord->created_at,
                ];
            });
    }

    public function latestProcessedGpsForBus(string $busNo): ?GpsTripRecord
    {
        return GpsTripRecord::query()
            ->whereRaw(
                'UPPER(TRIM(bus_no)) = ?',
                [strtoupper(trim($busNo))]
            )
            ->whereNotNull('mileage_km')
            ->whereHas(
                'batchUpload',
                fn ($query) => $query->where('status', 'Processed')
            )
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Build the canonical PMS status snapshot used by both the dashboard
     * and PMS Scheduling page.
     *
     * @param  array<string, mixed>|null  $gps
     * @return array{
     *     current_km:?float,
     *     km_traveled:float,
     *     gps_report_date:mixed,
     *     next_pms_km:float,
     *     remaining_km:?float,
     *     recommended_date:?CarbonInterface,
     *     status:string
     * }
     */
    public function assess(PmsSchedule $schedule, ?array $gps = null): array
    {
        $currentKm = array_key_exists('current_km', $gps ?? [])
            ? (float) $gps['current_km']
            : null;

        $gpsReportDate = $gps['gps_report_date'] ?? null;
        $nextPmsKm = (float) $schedule->next_pms_km;

        $recommendedDate = $schedule->recommended_date
            ? Carbon::parse($schedule->recommended_date)
            : $this->recommendedDate(
                $currentKm,
                $nextPmsKm,
                $gpsReportDate
            );

        return [
            'current_km' => $currentKm,
            'km_traveled' => (float) ($gps['km_traveled'] ?? 0),
            'gps_report_date' => $gpsReportDate,
            'next_pms_km' => $nextPmsKm,
            'remaining_km' => $currentKm !== null
                ? $nextPmsKm - $currentKm
                : null,
            'recommended_date' => $recommendedDate,
            'status' => $this->determineStatus(
                $currentKm,
                $nextPmsKm,
                $recommendedDate
            ),
        ];
    }

    public function determineStatus(
        ?float $currentKm,
        float $nextPmsKm,
        CarbonInterface|string|null $recommendedDate = null
    ): string {
        $date = $recommendedDate
            ? Carbon::parse($recommendedDate)
            : null;

        $isMileageOverdue = $currentKm !== null
            && $currentKm >= $nextPmsKm;

        $isDateOverdue = $date !== null
            && $date->isPast();

        if ($isMileageOverdue || $isDateOverdue) {
            return 'Overdue';
        }

        if (
            $currentKm !== null
            && $currentKm >= ($nextPmsKm - self::WARNING_RANGE_KM)
        ) {
            return 'Due Soon';
        }

        return 'Upcoming';
    }

    public function overallStatus(Collection $tasks): string
    {
        if (
            $tasks->contains(
                fn ($task) => ($task->status ?? null) === 'Overdue'
            )
        ) {
            return 'Overdue';
        }

        if (
            $tasks->contains(
                fn ($task) => ($task->status ?? null) === 'Due Soon'
            )
        ) {
            return 'Due Soon';
        }

        return 'Upcoming';
    }

    public function recommendedDate(
        ?float $currentKm,
        float $nextPmsKm,
        mixed $gpsReportDate
    ): ?CarbonInterface {
        if ($currentKm === null || ! $gpsReportDate) {
            return null;
        }

        $remainingKm = $nextPmsKm - $currentKm;

        if ($remainingKm <= 0) {
            return Carbon::parse($gpsReportDate)->startOfDay();
        }

        $daysUntilPms = (int) ceil(
            $remainingKm / self::DEFAULT_AVERAGE_DAILY_KM
        );

        return Carbon::parse($gpsReportDate)
            ->startOfDay()
            ->addDays(max(1, $daysUntilPms));
    }
}
