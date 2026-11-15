<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operation\TripSchedule;
use App\Services\DelayPredictionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DelayPredictionController extends Controller
{
    public function index(
        Request $request,
        DelayPredictionService $delayService
    ): JsonResponse {
        $validated = $request->validate([
            'trip_codes' => ['required', 'array', 'min:1', 'max:20'],
            'trip_codes.*' => ['required', 'string', 'max:100'],
        ]);

        $tripCodes = collect($validated['trip_codes'])
            ->map(fn ($code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        $schedules = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'assignment.bus',
                'incidents.replacement',
            ])
            ->whereIn('trip_code', $tripCodes)
            ->get()
            ->keyBy('trip_code');

        $payloads = [];
        $contexts = [];

        foreach ($tripCodes as $tripCode) {
            /** @var TripSchedule|null $schedule */
            $schedule = $schedules->get($tripCode);

            if (! $schedule || ! $schedule->trip_date || ! $schedule->departure_time) {
                continue;
            }

            $departureAt = Carbon::parse(
                $schedule->trip_date->format('Y-m-d').' '.$schedule->departure_time
            );

            $route = $schedule->shuttleRoute;
            // Model #3 training encodes routes by route_code. Prefer that exact
            // identifier so known demo/genuine routes do not become unseen (-1)
            // during inference. Keep the human-readable fallbacks for legacy
            // records that do not have a route code.
            $routeLabel = trim((string) ($route?->route_code ?? ''));

            if ($routeLabel === '') {
                $routeLabel = trim((string) ($route?->route_name ?? ''));
            }

            if ($routeLabel === '') {
                $origin = trim((string) ($route?->origin ?? ''));
                $destination = trim((string) ($route?->destination ?? ''));

                $routeLabel = $origin !== '' && $destination !== ''
                    ? "{$origin} - {$destination}"
                    : 'Unspecified Route';
            }

            $incidentContext = $this->incidentContext(
                collect($schedule->incidents),
                $departureAt
            );

            $payloads[$tripCode] = [
                'route' => $routeLabel,
                'scheduled_departure_time' => $departureAt->format('H:i'),
                'bus_no' => $schedule->assignment?->bus?->bus_no,
                'driver_id' => $schedule->assignment?->driver_id,
                'trip_date' => $departureAt->toDateString(),
                'scheduled_duration_minutes' => $route?->estimated_time_minutes !== null
                    ? (float) $route->estimated_time_minutes
                    : null,
                'route_distance_km' => $route?->distance_km !== null
                    ? (float) $route->distance_km
                    : null,
                'incident_before_departure' => $incidentContext['incident_before_departure'],
                'incident_breakdown_flag' => $incidentContext['incident_breakdown_flag'],
                'incident_traffic_flag' => $incidentContext['incident_traffic_flag'],
                'incident_replacement_flag' => $incidentContext['incident_replacement_flag'],
            ];

            $contexts[$tripCode] = [
                'route' => $routeLabel,
                'scheduled_departure_at' => $departureAt->toIso8601String(),
                'bus_no' => $schedule->assignment?->bus?->bus_no,
                'driver_id' => $schedule->assignment?->driver_id,
                'pre_trip_incident_count' => $incidentContext['incident_count'],
            ];
        }

        $status = $delayService->status();
        $modelReady = ($status['model_ready'] ?? false) === true;

        $responses = $modelReady && $payloads !== []
            ? $delayService->predictBatch($payloads)
            : array_fill_keys(array_keys($payloads), null);

        $predictions = [];

        foreach ($tripCodes as $tripCode) {
            $scheduleFound = $schedules->has($tripCode);
            $prediction = $responses[$tripCode] ?? null;

            $predictions[$tripCode] = [
                'trip_code' => $tripCode,
                'schedule_found' => $scheduleFound,
                'available' => is_array($prediction),
                'prediction' => $prediction,
                'context' => $contexts[$tripCode] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'model_ready' => $modelReady,
            'dataset_type' => $status['dataset_type'] ?? null,
            'model_source' => $status['model_source'] ?? $status['data_source'] ?? null,
            'is_production_model' => (bool) ($status['is_production_model'] ?? false),
            'model_version' => $status['model_version'] ?? $status['message'] ?? null,
            'warning' => $status['warning'] ?? null,
            'disclaimer' => $status['disclaimer'] ?? null,
            'predictions' => $predictions,
        ]);
    }

    /**
     * Convert incidents explicitly linked to a scheduled trip into the same
     * pre-departure flags used by genuine Delay Model #3 training.
     *
     * @return array{
     *     incident_count: int,
     *     incident_before_departure: bool,
     *     incident_breakdown_flag: bool,
     *     incident_traffic_flag: bool,
     *     incident_replacement_flag: bool
     * }
     */
    private function incidentContext(
        Collection $incidents,
        Carbon $scheduledDeparture
    ): array {
        $preTrip = $incidents
            ->filter(function ($incident) use ($scheduledDeparture): bool {
                return $incident->incident_reported_at !== null
                    && $incident->incident_reported_at->lt($scheduledDeparture);
            })
            ->values();

        $breakdown = false;
        $traffic = false;
        $replacement = false;

        foreach ($preTrip as $incident) {
            $type = strtolower(trim((string) $incident->incident_type));

            if (str_contains($type, 'breakdown')) {
                $breakdown = true;
            }

            if (str_contains($type, 'traffic') || str_contains($type, 'accident')) {
                $traffic = true;
            }

            if (
                $incident->replacement?->dispatched_at
                && $incident->replacement->dispatched_at->lt($scheduledDeparture)
            ) {
                $replacement = true;
            }
        }

        return [
            'incident_count' => $preTrip->count(),
            'incident_before_departure' => $preTrip->isNotEmpty(),
            'incident_breakdown_flag' => $breakdown,
            'incident_traffic_flag' => $traffic,
            'incident_replacement_flag' => $replacement,
        ];
    }
}
