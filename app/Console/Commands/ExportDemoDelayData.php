<?php

namespace App\Console\Commands;

use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Incident;
use App\Models\Operation\TripSchedule;
use App\Services\Operation\DailyDriverReportScheduleMatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExportDemoDelayData extends Command
{
    protected const EXPORT_COLUMNS = [
        'report_date', 'trip_code', 'route_code', 'route_name', 'bus_no',
        'driver_id', 'driver_name', 'trip_ticket', 'scheduled_departure_time',
        'actual_departure_time', 'scheduled_arrival_time', 'actual_arrival_time',
        'scheduled_duration_minutes', 'actual_duration_minutes',
        'departure_delay_minutes', 'arrival_delay_minutes', 'route_distance_km',
        'incident_before_count', 'incident_breakdown_count',
        'incident_traffic_count', 'incident_replacement_count',
    ];

    protected $signature = 'delay:export-demo
        {--path= : Override output path (default: training_data/delay/demo_delay_training.csv)}';

    protected $description = 'Export frontend-visible DEMO DDR history for Delay Model #3 client demonstration';

    public function handle(DailyDriverReportScheduleMatchService $matcher): int
    {
        $path = $this->option('path')
            ?: base_path('training_data/delay/demo_delay_training.csv');

        $reports = DailyDriverReport::query()
            ->with(['bus', 'tripSchedule.shuttleRoute', 'tripSchedule.assignment', 'tripAssignment'])
            ->where(function ($query): void {
                $query->where('trip_ticket', 'like', 'TRIP-DEMO-%')
                    ->orWhereHas('tripSchedule', fn ($schedule) =>
                        $schedule->where('trip_code', 'like', 'TRIP-DEMO-%'));
            })
            ->orderBy('report_date')
            ->orderBy('departure_time')
            ->get();

        $rows = [];
        $seen = [];
        $exclusions = [
            'unmatched' => 0,
            'non_demo' => 0,
            'cancelled' => 0,
            'missing_timing' => 0,
            'implausible_duration' => 0,
            'short_schedule' => 0,
            'duplicate' => 0,
        ];

        foreach ($reports as $report) {
            $schedule = $matcher->match($report);
            if (! $schedule) {
                $exclusions['unmatched']++;
                continue;
            }

            if (! str_starts_with((string) $schedule->trip_code, 'TRIP-DEMO-')) {
                $exclusions['non_demo']++;
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

            $incidents = $this->incidentContext($report, $schedule, $scheduledDeparture);
            $route = $schedule->shuttleRoute;

            $rows[] = [
                $report->report_date->toDateString(),
                (string) $schedule->trip_code,
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
                $incidents['incident_before_count'],
                $incidents['breakdown_count'],
                $incidents['traffic_count'],
                $incidents['replacement_count'],
            ];
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error('Could not open demo export path: '.$path);
            return self::FAILURE;
        }

        fputcsv($handle, self::EXPORT_COLUMNS);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        file_put_contents(
            $path.'.meta.json',
            json_encode([
                'exported_at' => now()->toISOString(),
                'source' => 'demo',
                'dataset_type' => 'DEMO / SYNTHETIC FRONTEND DATA',
                'reports_processed' => $reports->count(),
                'matched_rows' => count($rows),
                'exclusions' => $exclusions,
            ], JSON_PRETTY_PRINT)
        );

        $this->info('DEMO delay export written: '.$path);
        $this->info('Frontend demo DDR processed: '.$reports->count().' | Matched rows: '.count($rows));
        $this->warn('DEMO / SYNTHETIC DATA ONLY - never present this export as genuine GCT operational history.');

        return count($rows) > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function incidentContext(
        DailyDriverReport $report,
        TripSchedule $schedule,
        Carbon $scheduledDeparture
    ): array {
        $incidents = Incident::query()
            ->where('trip_schedule_id', $schedule->id)
            ->where('incident_reported_at', '<', $scheduledDeparture)
            ->with('replacement')
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
