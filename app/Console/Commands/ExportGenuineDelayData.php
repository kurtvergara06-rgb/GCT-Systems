<?php

namespace App\Console\Commands;

use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Incident;
use App\Models\Operation\TripSchedule;
use App\Services\Operation\DailyDriverReportScheduleMatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExportGenuineDelayData extends Command
{
    /**
     * Genuine export column layout - matches GENUINE_RAW_COLUMNS in
     * python_engine/delay/training_data.py.
     */
    protected const EXPORT_COLUMNS = [
        'report_date',
        'trip_code',
        'route_code',
        'route_name',
        'bus_no',
        'driver_id',
        'driver_name',
        'trip_ticket',
        'scheduled_departure_time',
        'actual_departure_time',
        'scheduled_arrival_time',
        'actual_arrival_time',
        'scheduled_duration_minutes',
        'actual_duration_minutes',
        'departure_delay_minutes',
        'arrival_delay_minutes',
        'route_distance_km',
        'incident_before_count',
        'incident_breakdown_count',
        'incident_traffic_count',
        'incident_replacement_count',
    ];

    protected $signature = 'delay:export-genuine
        {--path= : Override the export CSV path (default: training_data/delay/genuine_delay_training.csv)}
        {--from= : Only export reports on/after this date (Y-m-d)}';

    protected $description = 'Export the genuine matched DDR history for delay-model (Model #3) training';

    public function handle(DailyDriverReportScheduleMatchService $matcher): int
    {
        $path = $this->option('path')
            ?: base_path('training_data/delay/genuine_delay_training.csv');

        $prefixes = array_map(
            fn (string $prefix): string => trim($prefix),
            (array) config('services.delay.demo_trip_prefixes', ['TRIP-'])
        );

        $from = $this->option('from') ?: null;

        $reports = DailyDriverReport::query()
            ->with([
                'bus',
                'tripSchedule.shuttleRoute',
                'tripSchedule.assignment',
                'tripAssignment',
            ])
            ->when($from, fn ($query) => $query->whereDate('report_date', '>=', $from))
            ->orderBy('report_date')
            ->orderBy('departure_time')
            ->get();

        $rows = [];
        $exclusions = [
            'unmatched' => 0,
            'demo_schedule' => 0,
            'cancelled' => 0,
            'missing_timing' => 0,
            'implausible_duration' => 0,
            'short_schedule' => 0,
            'duplicate' => 0,
        ];
        $seen = [];
        $total = 0;

        foreach ($reports as $report) {
            $total++;

            $schedule = $matcher->match($report);
            if (! $schedule) {
                $exclusions['unmatched']++;
                continue;
            }

            $tripCode = (string) $schedule->trip_code;
            $isDemo = count($prefixes) > 0
                && collect($prefixes)->contains(fn ($prefix) => $prefix !== '' && str_starts_with($tripCode, $prefix));
            if ($isDemo) {
                $exclusions['demo_schedule']++;
                continue;
            }

            if (strtolower((string) $schedule->status) === 'cancelled') {
                $exclusions['cancelled']++;
                continue;
            }

            $comparison = $matcher->comparison(
                $schedule,
                $report->departure_time,
                $report->arrival_time
            );
            if (
                ! $comparison['scheduled_departure']
                || ! $comparison['scheduled_arrival']
                || ! $comparison['actual_departure']
                || ! $comparison['actual_arrival']
            ) {
                $exclusions['missing_timing']++;
                continue;
            }

            $scheduledDeparture = Carbon::createFromFormat(
                'Y-m-d H:i',
                $report->report_date->toDateString().' '.$comparison['scheduled_departure']
            );
            $scheduledArrival = Carbon::createFromFormat(
                'Y-m-d H:i',
                $report->report_date->toDateString().' '.$comparison['scheduled_arrival']
            );
            $actualDeparture = Carbon::createFromFormat(
                'H:i',
                $comparison['actual_departure']
            )->setDateFrom($scheduledDeparture);
            $actualArrival = Carbon::createFromFormat(
                'H:i',
                $comparison['actual_arrival']
            )->setDateFrom($scheduledDeparture);

            // Overnight trips wrap past midnight: advance to the next day.
            if ($scheduledArrival->lt($scheduledDeparture)) {
                $scheduledArrival->addDay();
            }
            if ($actualArrival->lt($actualDeparture)) {
                $actualArrival->addDay();
            }

            $scheduledDuration = (int) $scheduledDeparture->diffInMinutes($scheduledArrival, false);
            $actualDuration = (int) $actualDeparture->diffInMinutes($actualArrival, false);

            if ($scheduledDuration < 10) {
                $exclusions['short_schedule']++;
                continue;
            }

            if ($actualDuration <= 0 || $actualDuration > 720) {
                $exclusions['implausible_duration']++;
                continue;
            }

            $duplicateKey = $report->report_date->toDateString().'|'.$report->trip_ticket;
            if (isset($seen[$duplicateKey])) {
                $exclusions['duplicate']++;
                continue;
            }
            $seen[$duplicateKey] = true;

            $incidentContext = $this->incidentContext($report, $schedule, $scheduledDeparture);

            $route = $schedule->shuttleRoute;

            $rows[] = [
                $report->report_date->toDateString(),
                $tripCode,
                (string) ($route->route_code ?? ''),
                (string) ($route->route_name ?? $comparison['route_label'] ?? ''),
                (string) ($report->bus?->bus_no ?? ''),
                (string) $report->driver_id,
                (string) $report->driver_name,
                (string) $report->trip_ticket,
                $comparison['scheduled_departure'],
                $comparison['actual_departure'],
                $comparison['scheduled_arrival'],
                $comparison['actual_arrival'],
                $scheduledDuration,
                $actualDuration,
                (int) $comparison['departure_delay_minutes'],
                (int) $comparison['arrival_delay_minutes'],
                (float) ($route->distance_km ?? 0.0),
                $incidentContext['incident_before_count'],
                $incidentContext['breakdown_count'],
                $incidentContext['traffic_count'],
                $incidentContext['replacement_count'],
            ];
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error('Could not open export path for writing: '.$path);

            return self::FAILURE;
        }
        fputcsv($handle, self::EXPORT_COLUMNS);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $metaPath = $path.'.meta.json';
        file_put_contents(
            $metaPath,
            json_encode(
                [
                    'exported_at' => now()->toISOString(),
                    'source' => 'genuine',
                    'reports_processed' => $total,
                    'matched_rows' => count($rows),
                    'exclusions' => $exclusions,
                ],
                JSON_PRETTY_PRINT
            )
        );

        $this->info('Genuine delay export written: '.$path);
        $this->info('Reports processed: '.$total.' | Matched rows exported: '.count($rows));

        $this->table(
            ['Exclusion', 'Rows excluded'],
            array_map(
                fn (string $key, int $count): array => [$key, $count],
                array_keys($exclusions),
                array_values($exclusions)
            )
        );

        if (count($rows) === 0) {
            $this->warn('No genuine matched rows exported - the delay model is NOT READY for genuine training (see python_engine/delay/DELAY_MODEL_READINESS.md).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Pre-trip incident context for a matched trip.
     *
     * Directly linked DDR records only use incidents tied to that exact
     * trip schedule. Legacy DDR records may additionally use unlinked
     * incidents that match the same bus and driver. An incident explicitly
     * linked to a different trip is never allowed to leak into this trip's
     * training features.
     *
     * @return array{incident_before_count: int, breakdown_count: int, traffic_count: int, replacement_count: int}
     */
    private function incidentContext(
        DailyDriverReport $report,
        TripSchedule $schedule,
        Carbon $scheduledDeparture
    ): array {
        $query = Incident::query()
            ->whereDate('incident_reported_at', $report->report_date->toDateString())
            ->where('incident_reported_at', '<', $scheduledDeparture);

        if ($report->trip_schedule_id) {
            $query->where('trip_schedule_id', $schedule->id);
        } else {
            $query->where(function ($contextQuery) use ($report, $schedule) {
                $contextQuery->where('trip_schedule_id', $schedule->id);

                $contextQuery->orWhere(function ($legacyQuery) use ($report) {
                    $legacyQuery->whereNull('trip_schedule_id');

                    if ($report->bus_id) {
                        $legacyQuery->where('bus_id', $report->bus_id);
                    }

                    if ($report->driver_id) {
                        $legacyQuery->where('driver_id', $report->driver_id);
                    }
                });
            });
        }

        $incidents = $query
            ->with(['replacement'])
            ->get();

        $breakdown = 0;
        $traffic = 0;
        $replacement = 0;

        foreach ($incidents as $incident) {
            $type = strtolower((string) $incident->incident_type);
            if (str_contains($type, 'breakdown')) {
                $breakdown++;
            }
            if (str_contains($type, 'traffic') || str_contains($type, 'accident')) {
                $traffic++;
            }
            if (
                $incident->replacement
                && $incident->replacement->dispatched_at
                && $incident->replacement->dispatched_at->lt($scheduledDeparture)
            ) {
                $replacement++;
            }
        }

        return [
            'incident_before_count' => $incidents->count(),
            'breakdown_count' => $breakdown,
            'traffic_count' => $traffic,
            'replacement_count' => $replacement,
        ];
    }
}
