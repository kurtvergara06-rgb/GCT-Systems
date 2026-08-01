<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Operation\ShuttleRoute;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RouteController extends Controller
{
    public function index(Request $request): View
    {
        $query = ShuttleRoute::query()->with('stops');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('route_code', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('origin', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', ucfirst(strtolower((string) $request->status)));
        }

        $routes = $query->latest()->paginate(8)->withQueryString();
        $totalRoutes = ShuttleRoute::count();
        $activeRoutes = ShuttleRoute::where('status', 'Active')->count();
        $totalStops = DB::table('route_stops')->count();
        $routeCoverage = ShuttleRoute::sum('distance_km');

        $gpsTripRecords = GpsTripRecord::query()
            ->whereHas('batchUpload', fn ($q) => $q->where('status', 'Processed'))
            ->whereNotNull('coordinates')
            ->where('coordinates', '<>', '')
            ->orderByDesc('beginning_at')
            ->limit(250)
            ->get([
                'id', 'record_no', 'bus_no', 'grouping', 'trip_type',
                'beginning_at', 'initial_location', 'ending_at', 'final_location',
                'duration_minutes', 'total_minutes', 'in_motion_minutes',
                'idling_minutes', 'mileage_km', 'engine_hours', 'location',
                'coordinates', 'description',
            ])
            ->map(function (GpsTripRecord $record): array {
                return [
                    'id' => $record->id,
                    'record_no' => $record->record_no,
                    'bus_no' => $record->bus_no,
                    'grouping' => $record->grouping,
                    'trip_type' => $record->trip_type,
                    'beginning_at' => optional($record->beginning_at)?->format('Y-m-d H:i:s'),
                    'initial_location' => $record->initial_location,
                    'ending_at' => optional($record->ending_at)?->format('Y-m-d H:i:s'),
                    'final_location' => $record->final_location,
                    'duration_minutes' => $record->duration_minutes,
                    'total_minutes' => $record->total_minutes,
                    'in_motion_minutes' => $record->in_motion_minutes,
                    'idling_minutes' => $record->idling_minutes,
                    'mileage_km' => $record->mileage_km !== null ? (float) $record->mileage_km : null,
                    'engine_hours' => $record->engine_hours !== null ? (float) $record->engine_hours : null,
                    'location' => $record->location,
                    'coordinates' => $record->coordinates,
                    'description' => $record->description,
                ];
            })
            ->values();

        $gpsLocationSuggestions = $this->buildGpsLocationSuggestions($gpsTripRecords->all());

        $latestRoute = ShuttleRoute::orderByDesc('id')->first();
        $nextNumber = $latestRoute ? $latestRoute->id + 1 : 1;
        $nextRouteCode = 'R-' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);

        return view('Operation.Routes.routes-stops', compact(
            'routes', 'totalRoutes', 'activeRoutes', 'totalStops',
            'routeCoverage', 'nextRouteCode', 'gpsTripRecords',
            'gpsLocationSuggestions'
        ));
    }

    public function searchLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:3|max:160',
        ]);

        $apiKey = (string) config('services.geoapify.key');

        if ($apiKey === '') {
            return response()->json([
                'message' => 'Geoapify is not configured. Add GEOAPIFY_API_KEY to .env.',
                'results' => [],
            ], 503);
        }

        $query = trim($validated['q']);
        $cacheKey = 'route-geocode:' . sha1(mb_strtolower($query));

        try {
            $results = Cache::remember($cacheKey, now()->addDays(7), function () use ($query, $apiKey) {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->retry(2, 250)
                    ->get('https://api.geoapify.com/v1/geocode/autocomplete', [
                        'text' => $query,
                        'format' => 'json',
                        'filter' => 'countrycode:ph',
                        'limit' => 5,
                        'lang' => 'en',
                        'apiKey' => $apiKey,
                    ])
                    ->throw();

                return collect($response->json('results', []))
                    ->map(function (array $place): array {
                        return [
                            'id' => (string) ($place['place_id'] ?? sha1(json_encode($place))),
                            'name' => (string) ($place['name'] ?? $place['address_line1'] ?? $place['formatted'] ?? 'Unknown location'),
                            'address' => (string) ($place['formatted'] ?? $place['address_line2'] ?? ''),
                            'latitude' => isset($place['lat']) ? (float) $place['lat'] : null,
                            'longitude' => isset($place['lon']) ? (float) $place['lon'] : null,
                            'source' => 'OpenStreetMap',
                        ];
                    })
                    ->filter(fn (array $place) => $place['latitude'] !== null && $place['longitude'] !== null)
                    ->values()
                    ->all();
            });

            return response()->json(['results' => $results]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'The location service could not be reached.',
                'results' => [],
            ], 503);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Location search failed.',
                'results' => [],
            ], 502);
        }
    }

    public function calculateRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'points' => 'required|array|min:2|max:20',
            'points.*.latitude' => 'required|numeric|between:-90,90',
            'points.*.longitude' => 'required|numeric|between:-180,180',
        ]);

        $coordinates = collect($validated['points'])
            ->map(fn (array $point) => sprintf('%.7F,%.7F', $point['longitude'], $point['latitude']))
            ->implode(';');

        $baseUrl = rtrim((string) config('services.osrm.base_url', 'https://router.project-osrm.org'), '/');
        $cacheKey = 'route-osrm:' . sha1($coordinates);

        try {
            $result = Cache::remember($cacheKey, now()->addHours(12), function () use ($baseUrl, $coordinates) {
                $response = Http::acceptJson()
                    ->timeout(15)
                    ->retry(2, 300)
                    ->get("{$baseUrl}/route/v1/driving/{$coordinates}", [
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                        'alternatives' => 'false',
                    ])
                    ->throw();

                if ($response->json('code') !== 'Ok' || !$response->json('routes.0')) {
                    throw ValidationException::withMessages([
                        'points' => 'No drivable route was found for the selected locations.',
                    ]);
                }

                $route = $response->json('routes.0');

                return [
                    'distance_km' => round(((float) $route['distance']) / 1000, 2),
                    'duration_minutes' => max(1, (int) round(((float) $route['duration']) / 60)),
                    'geometry' => $route['geometry'],
                    'source' => 'OSRM',
                ];
            });

            return response()->json($result);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            return response()->json(['message' => 'The routing service could not be reached.'], 503);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Road route calculation failed.'], 502);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRoute($request);

        DB::transaction(function () use ($validated) {
            $latestRoute = ShuttleRoute::lockForUpdate()->orderByDesc('id')->first();
            $nextNumber = $latestRoute ? $latestRoute->id + 1 : 1;
            $routeCode = 'R-' . str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);

            $route = ShuttleRoute::create($this->routePayload($validated, $routeCode));
            $this->saveStops($route, $validated);
        });

        session()->flash('success', 'Route created successfully.');

        return new RedirectResponse('/operation/routes');
    }

    public function update(Request $request, ShuttleRoute $shuttleRoute): RedirectResponse
    {
        $validated = $this->validateRoute($request);

        DB::transaction(function () use ($validated, $shuttleRoute) {
            $shuttleRoute->update($this->routePayload($validated));
            $shuttleRoute->stops()->delete();
            $this->saveStops($shuttleRoute, $validated);
        });

        session()->flash('success', 'Route updated successfully.');

        return new RedirectResponse('/operation/routes');
    }

    public function destroy(ShuttleRoute $shuttleRoute): RedirectResponse
    {
        $shuttleRoute->delete();
        session()->flash('success', 'Route deleted successfully.');

        return new RedirectResponse('/operation/routes');
    }

    private function validateRoute(Request $request): array
    {
        return $request->validate([
            'route_name' => 'required|string|max:255',
            'origin' => 'required|string|max:255|different:destination',
            'origin_address' => 'nullable|string|max:1000',
            'origin_latitude' => 'required|numeric|between:-90,90',
            'origin_longitude' => 'required|numeric|between:-180,180',
            'origin_source' => 'nullable|string|max:40',
            'destination' => 'required|string|max:255|different:origin',
            'destination_address' => 'nullable|string|max:1000',
            'destination_latitude' => 'required|numeric|between:-90,90',
            'destination_longitude' => 'required|numeric|between:-180,180',
            'destination_source' => 'nullable|string|max:40',
            'distance_km' => 'nullable|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:1',
            'calculated_distance_km' => 'nullable|numeric|min:0',
            'calculated_time_minutes' => 'nullable|integer|min:1',
            'distance_source' => 'nullable|string|max:40',
            'distance_is_manual' => 'nullable|boolean',
            'time_is_manual' => 'nullable|boolean',
            'route_geometry' => 'nullable|json',
            'status' => 'required|in:Active,Inactive',
            'stops' => 'nullable|array',
            'stops.*' => 'nullable|string|max:255',
            'stop_addresses' => 'nullable|array',
            'stop_addresses.*' => 'nullable|string|max:1000',
            'stop_latitudes' => 'nullable|array',
            'stop_latitudes.*' => 'nullable|numeric|between:-90,90',
            'stop_longitudes' => 'nullable|array',
            'stop_longitudes.*' => 'nullable|numeric|between:-180,180',
            'stop_sources' => 'nullable|array',
            'stop_sources.*' => 'nullable|string|max:40',
        ]);
    }

    private function routePayload(array $validated, ?string $routeCode = null): array
    {
        $payload = [
            'route_name' => $validated['route_name'],
            'origin' => $validated['origin'],
            'origin_address' => $validated['origin_address'] ?? null,
            'origin_latitude' => $validated['origin_latitude'],
            'origin_longitude' => $validated['origin_longitude'],
            'origin_source' => $validated['origin_source'] ?? null,
            'destination' => $validated['destination'],
            'destination_address' => $validated['destination_address'] ?? null,
            'destination_latitude' => $validated['destination_latitude'],
            'destination_longitude' => $validated['destination_longitude'],
            'destination_source' => $validated['destination_source'] ?? null,
            'distance_km' => $validated['distance_km'] ?? null,
            'estimated_time_minutes' => $validated['estimated_time_minutes'] ?? null,
            'calculated_distance_km' => $validated['calculated_distance_km'] ?? null,
            'calculated_time_minutes' => $validated['calculated_time_minutes'] ?? null,
            'distance_source' => $validated['distance_source'] ?? null,
            'distance_is_manual' => (bool) ($validated['distance_is_manual'] ?? false),
            'time_is_manual' => (bool) ($validated['time_is_manual'] ?? false),
            'route_geometry' => $validated['route_geometry'] ?? null,
            'route_calculated_at' => !empty($validated['route_geometry']) ? now() : null,
            'status' => $validated['status'],
        ];

        if ($routeCode !== null) {
            $payload['route_code'] = $routeCode;
        }

        return $payload;
    }

    private function saveStops(ShuttleRoute $route, array $validated): void
    {
        $stops = $validated['stops'] ?? [];
        $addresses = $validated['stop_addresses'] ?? [];
        $latitudes = $validated['stop_latitudes'] ?? [];
        $longitudes = $validated['stop_longitudes'] ?? [];
        $sources = $validated['stop_sources'] ?? [];
        $order = 1;

        foreach ($stops as $index => $stop) {
            $stop = trim((string) $stop);
            if ($stop === '') continue;

            $route->stops()->create([
                'stop_name' => $stop,
                'stop_order' => $order++,
                'address' => $addresses[$index] ?? null,
                'latitude' => $latitudes[$index] ?? null,
                'longitude' => $longitudes[$index] ?? null,
                'location_source' => $sources[$index] ?? null,
            ]);
        }
    }

    private function buildGpsLocationSuggestions(array $records): array
    {
        $locations = [];

        foreach ($records as $record) {
            if (!preg_match('/(-?\\d+(?:\\.\\d+)?)\\s*,\\s*(-?\\d+(?:\\.\\d+)?)\\s*(?:->|→|to)\\s*(-?\\d+(?:\\.\\d+)?)\\s*,\\s*(-?\\d+(?:\\.\\d+)?)/i', (string) ($record['coordinates'] ?? ''), $match)) {
                continue;
            }

            $pairs = [
                [$record['initial_location'] ?? null, (float) $match[1], (float) $match[2]],
                [$record['final_location'] ?? null, (float) $match[3], (float) $match[4]],
            ];

            foreach ($pairs as [$name, $latitude, $longitude]) {
                $name = trim((string) $name);
                if ($name === '') continue;

                $key = mb_strtolower($name);
                $locations[$key] ??= [
                    'id' => 'gps-' . sha1($key),
                    'name' => $name,
                    'address' => $name,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'source' => 'GPS Batch',
                    'grouping' => $record['grouping'] ?? null,
                    'bus_no' => $record['bus_no'] ?? null,
                ];
            }
        }

        return array_values($locations);
    }
}