<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\FuelReport;
use App\Services\FuelPredictionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelPredictionController extends Controller
{
    public function index(Request $request, FuelPredictionService $fuelService): JsonResponse
    {
        $validated = $request->validate([
            'bus_nos' => ['required', 'array', 'min:1', 'max:12'],
            'bus_nos.*' => ['required', 'string', 'max:50'],
            'period' => ['nullable', 'string', 'max:30'],
        ]);

        $busNos = collect($validated['bus_nos'])
            ->map(fn ($bus): string => strtoupper(trim((string) $bus)))
            ->filter()
            ->unique()
            ->values();

        [$start, $end] = $this->periodBounds((string) ($validated['period'] ?? 'this-month'));

        $reports = FuelReport::query()
            ->with('gpsTripRecord')
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('gps_trip_record_id')
            ->where(function ($query) use ($busNos): void {
                foreach ($busNos as $busNo) {
                    $query->orWhereRaw('UPPER(TRIM(bus_no)) = ?', [$busNo]);
                }
            })
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FuelReport $report): bool => $report->gpsTripRecord !== null)
            ->groupBy(fn (FuelReport $report): string => strtoupper(trim((string) $report->bus_no)))
            ->map(fn ($rows) => $rows->first());

        $payloads = [];
        $contexts = [];

        foreach ($busNos as $busNo) {
            /** @var FuelReport|null $report */
            $report = $reports->get($busNo);
            $gps = $report?->gpsTripRecord;

            if (! $report || ! $gps || ! $gps->beginning_at) {
                continue;
            }

            $route = trim((string) $gps->grouping);
            if ($route === '') {
                $from = trim((string) $gps->initial_location);
                $to = trim((string) $gps->final_location);
                $route = $from !== '' && $to !== '' ? "{$from} - {$to}" : 'Unspecified Route';
            }

            $duration = (float) ($gps->duration_minutes ?? 0);
            if ($duration <= 0) {
                $duration = (float) ($gps->total_minutes ?? 0);
            }

            $distance = (float) ($report->distance_km ?? 0);
            if ($distance <= 0) {
                $distance = (float) ($gps->mileage_km ?? 0);
            }

            $payloads[$busNo] = [
                'route' => $route,
                'trip_started_at' => $gps->beginning_at->toIso8601String(),
                'bus_no' => $report->bus_no ?: $gps->bus_no,
                'distance_km' => $distance > 0 ? $distance : null,
                'trip_duration_minutes' => $duration > 0 ? $duration : null,
                'in_motion_minutes' => $gps->in_motion_minutes !== null ? (float) $gps->in_motion_minutes : null,
                'idling_minutes' => $gps->idling_minutes !== null ? (float) $gps->idling_minutes : null,
                'engine_on_hours' => $gps->engine_hours !== null ? (float) $gps->engine_hours : null,
            ];

            $contexts[$busNo] = [
                'fuel_report_id' => $report->id,
                'report_date' => $report->report_date?->toDateString(),
                'bus_no' => $report->bus_no,
                'route' => $route,
                'distance_km' => $distance,
                'actual_fuel_liters' => (float) $report->fuel_liters,
                'gps_trip_record_id' => $report->gps_trip_record_id,
            ];
        }

        $status = $fuelService->status();
        $modelReady = ($status['model_ready'] ?? false) === true;

        $responses = $modelReady && $payloads !== []
            ? $fuelService->predictBatch($payloads)
            : array_fill_keys(array_keys($payloads), null);

        $predictions = [];

        foreach ($busNos as $busNo) {
            $report = $reports->get($busNo);
            $prediction = $responses[$busNo] ?? null;
            $actual = $report ? (float) $report->fuel_liters : null;
            $predicted = is_array($prediction) && isset($prediction['predicted_fuel_liters'])
                ? (float) $prediction['predicted_fuel_liters']
                : null;
            $variance = $actual !== null && $predicted !== null
                ? $actual - $predicted
                : null;
            $variancePercent = $predicted !== null && $predicted > 0 && $variance !== null
                ? ($variance / $predicted) * 100
                : null;

            $predictions[$busNo] = [
                'bus_no' => $busNo,
                'report_found' => $report !== null,
                'gps_linked' => $report?->gpsTripRecord !== null,
                'available' => is_array($prediction),
                'prediction' => $prediction,
                'actual_fuel_liters' => $actual,
                'variance_liters' => $variance !== null ? round($variance, 2) : null,
                'variance_percent' => $variancePercent !== null ? round($variancePercent, 1) : null,
                'context' => $contexts[$busNo] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'model_ready' => $modelReady,
            'source' => $status['source'] ?? null,
            'data_source' => $status['data_source'] ?? 'genuine',
            'dataset_type' => $status['dataset_type'] ?? 'GENUINE GCT RECORDS',
            'is_production_model' => (bool) ($status['is_production_model'] ?? $modelReady),
            'model_version' => $status['model_version'] ?? null,
            'sample_count' => (int) ($status['sample_count'] ?? 0),
            'reason' => $status['reason'] ?? null,
            'period' => $validated['period'] ?? 'this-month',
            'predictions' => $predictions,
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodBounds(string $period): array
    {
        $end = now()->endOfDay();
        $start = match ($period) {
            'last-30-days' => now()->subDays(29)->startOfDay(),
            'last-90-days' => now()->subDays(89)->startOfDay(),
            'last-12-months' => now()->subMonths(12)->startOfDay(),
            'this-week' => now()->startOfWeek()->startOfDay(),
            default => now()->startOfMonth(),
        };

        return [$start, $end];
    }
}
