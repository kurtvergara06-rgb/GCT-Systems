<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\FuelReport;
use App\Services\FuelPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelPredictionController extends Controller
{
    public function index(Request $request, FuelPredictionService $fuelService): JsonResponse
    {
        $validated = $request->validate([
            'fuel_report_ids' => ['required', 'array', 'min:1', 'max:20'],
            'fuel_report_ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['fuel_report_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $reports = FuelReport::query()
            ->with('gpsTripRecord')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $payloads = [];
        $contexts = [];

        foreach ($ids as $id) {
            /** @var FuelReport|null $report */
            $report = $reports->get($id);
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

            $payloads[(string) $id] = [
                'route' => $route,
                'trip_started_at' => $gps->beginning_at->toIso8601String(),
                'bus_no' => $report->bus_no ?: $gps->bus_no,
                'distance_km' => $distance > 0 ? $distance : null,
                'trip_duration_minutes' => $duration > 0 ? $duration : null,
                'in_motion_minutes' => $gps->in_motion_minutes !== null ? (float) $gps->in_motion_minutes : null,
                'idling_minutes' => $gps->idling_minutes !== null ? (float) $gps->idling_minutes : null,
                'engine_on_hours' => $gps->engine_hours !== null ? (float) $gps->engine_hours : null,
            ];

            $contexts[(string) $id] = [
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

        foreach ($ids as $id) {
            $key = (string) $id;
            $report = $reports->get($id);
            $prediction = $responses[$key] ?? null;
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

            $predictions[$key] = [
                'fuel_report_id' => $id,
                'report_found' => $report !== null,
                'gps_linked' => $report?->gpsTripRecord !== null,
                'available' => is_array($prediction),
                'prediction' => $prediction,
                'actual_fuel_liters' => $actual,
                'variance_liters' => $variance !== null ? round($variance, 2) : null,
                'variance_percent' => $variancePercent !== null ? round($variancePercent, 1) : null,
                'context' => $contexts[$key] ?? null,
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
            'metrics' => $status['metrics'] ?? null,
            'reason' => $status['reason'] ?? null,
            'predictions' => $predictions,
        ]);
    }
}
