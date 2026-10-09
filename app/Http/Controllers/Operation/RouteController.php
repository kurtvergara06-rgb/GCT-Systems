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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RouteController extends Controller
{
    private const MIN_ENDPOINT_DISTANCE_KM = 0.05;
    /*
    |--------------------------------------------------------------------------
    | Route List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        $query = ShuttleRoute::query()
            ->with('stops')
            ->withCount('tripSchedules');

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input('search')
            );

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'route_code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'route_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'origin',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'destination',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'stops',
                        function ($stopQuery) use ($search) {
                            $stopQuery->where(
                                'stop_name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $status = ucfirst(
                strtolower(
                    (string) $request->input('status')
                )
            );

            if (in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )) {
                $query->where('status', $status);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Table Data
        |--------------------------------------------------------------------------
        */

        $routes = $query
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Correct Database-Wide Summary
        |--------------------------------------------------------------------------
        */

        $routeStats = [
            'total' => ShuttleRoute::query()->count(),

            'active' => ShuttleRoute::query()
                ->where('status', 'Active')
                ->count(),

            'inactive' => ShuttleRoute::query()
                ->where('status', 'Inactive')
                ->count(),

            'stops' => DB::table('route_stops')
                ->join(
                    'shuttle_routes',
                    'shuttle_routes.id',
                    '=',
                    'route_stops.shuttle_route_id'
                )
                ->count(),

            'coverage' => (float) ShuttleRoute::query()
                ->sum('distance_km'),
        ];

        /*
        |--------------------------------------------------------------------------
        | GPS Trip Records
        |--------------------------------------------------------------------------
        */

        $gpsTripRecords = GpsTripRecord::query()
            ->whereHas(
                'batchUpload',
                fn ($query) => $query->where(
                    'status',
                    'Processed'
                )
            )
            ->whereNotNull('coordinates')
            ->where('coordinates', '<>', '')
            ->orderByDesc('beginning_at')
            ->limit(250)
            ->get([
                'id',
                'record_no',
                'bus_no',
                'grouping',
                'trip_type',
                'beginning_at',
                'initial_location',
                'ending_at',
                'final_location',
                'duration_minutes',
                'total_minutes',
                'in_motion_minutes',
                'idling_minutes',
                'mileage_km',
                'engine_hours',
                'location',
                'coordinates',
                'description',
            ])
            ->map(function (GpsTripRecord $record): array {
                return [
                    'id' => $record->id,

                    'record_no' =>
                        $record->record_no,

                    'bus_no' =>
                        $record->bus_no,

                    'grouping' =>
                        $record->grouping,

                    'trip_type' =>
                        $record->trip_type,

                    'beginning_at' =>
                        optional(
                            $record->beginning_at
                        )?->format('Y-m-d H:i:s'),

                    'initial_location' =>
                        $record->initial_location,

                    'ending_at' =>
                        optional(
                            $record->ending_at
                        )?->format('Y-m-d H:i:s'),

                    'final_location' =>
                        $record->final_location,

                    'duration_minutes' =>
                        $record->duration_minutes,

                    'total_minutes' =>
                        $record->total_minutes,

                    'in_motion_minutes' =>
                        $record->in_motion_minutes,

                    'idling_minutes' =>
                        $record->idling_minutes,

                    'mileage_km' =>
                        $record->mileage_km !== null
                            ? (float) $record->mileage_km
                            : null,

                    'engine_hours' =>
                        $record->engine_hours !== null
                            ? (float) $record->engine_hours
                            : null,

                    'location' =>
                        $record->location,

                    'coordinates' =>
                        $record->coordinates,

                    'description' =>
                        $record->description,
                ];
            })
            ->values();

        $gpsLocationSuggestions =
            $this->buildGpsLocationSuggestions(
                $gpsTripRecords->all()
            );

        /*
        |--------------------------------------------------------------------------
        | Next Route Code Preview
        |--------------------------------------------------------------------------
        */

        $nextRouteCode =
            $this->generateNextRouteCode();

        return view(
            'Operation.Routes.routes-stops',
            compact(
                'routes',
                'routeStats',
                'nextRouteCode',
                'gpsTripRecords',
                'gpsLocationSuggestions'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Location Autocomplete
    |--------------------------------------------------------------------------
    */

    public function searchLocations(
        Request $request
    ): JsonResponse {
        $validated = $request->validate(
            [
                'q' => [
                    'required',
                    'string',
                    'min:2',
                    'max:160',
                ],
            ],
            [
                'q.required' =>
                    'Enter a location to search.',

                'q.min' =>
                    'Enter at least two characters.',

                'q.max' =>
                    'The location search may not exceed 160 characters.',
            ]
        );

        $search = trim(
            (string) $validated['q']
        );

        $remoteSearch =
            str_contains(
                mb_strtolower($search),
                'philippines'
            )
                ? $search
                : $search . ' Philippines';

        $cacheKey =
            'route-photon-geocode:v1:'
            . sha1(
                mb_strtolower($remoteSearch)
            );

        try {
            $results = Cache::remember(
                $cacheKey,
                now()->addDays(7),
                function () use ($remoteSearch): array {
                    $response = Http::acceptJson()
                        ->withHeaders([
                            'User-Agent' =>
                                'GCT-Systems/1.0 (route location search)',
                            'Accept-Language' =>
                                'en',
                        ])
                        ->timeout(10)
                        ->get(
                            rtrim(
                                (string) config(
                                    'services.photon.base_url',
                                    'https://photon.komoot.io'
                                ),
                                '/'
                            ) . '/api/',
                            [
                                'q' => $remoteSearch,
                                'limit' => 10,
                                'lang' => 'en',
                            ]
                        )
                        ->throw();

                    return collect(
                        $response->json(
                            'features',
                            []
                        )
                    )
                        ->filter(
                            function (
                                array $feature
                            ): bool {
                                $countryCode =
                                    mb_strtolower(
                                        trim(
                                            (string) data_get(
                                                $feature,
                                                'properties.countrycode',
                                                ''
                                            )
                                        )
                                    );

                                return $countryCode === ''
                                    || $countryCode === 'ph';
                            }
                        )
                        ->map(
                            function (
                                array $feature
                            ): array {
                                $properties =
                                    $feature['properties']
                                    ?? [];

                                $coordinates =
                                    data_get(
                                        $feature,
                                        'geometry.coordinates',
                                        []
                                    );

                                $longitude =
                                    isset($coordinates[0])
                                        ? (float) $coordinates[0]
                                        : null;

                                $latitude =
                                    isset($coordinates[1])
                                        ? (float) $coordinates[1]
                                        : null;

                                $name = trim(
                                    (string) (
                                        $properties['name']
                                        ?? $properties['street']
                                        ?? $properties['city']
                                        ?? $properties['district']
                                        ?? $properties['county']
                                        ?? 'Unknown location'
                                    )
                                );

                                $address = collect([
                                    $properties['street']
                                        ?? null,
                                    $properties['district']
                                        ?? null,
                                    $properties['city']
                                        ?? $properties['town']
                                        ?? $properties['village']
                                        ?? null,
                                    $properties['state']
                                        ?? null,
                                    $properties['country']
                                        ?? null,
                                ])
                                    ->map(
                                        fn ($value) =>
                                            trim(
                                                (string) $value
                                            )
                                    )
                                    ->filter()
                                    ->unique()
                                    ->implode(', ');

                                return [
                                    'id' => (string) (
                                        $properties['osm_id']
                                        ?? sha1(
                                            json_encode(
                                                $feature
                                            )
                                        )
                                    ),

                                    'name' =>
                                        $name !== ''
                                            ? $name
                                            : 'Unknown location',

                                    'address' =>
                                        $address !== ''
                                            ? $address
                                            : $name,

                                    'latitude' =>
                                        $latitude,

                                    'longitude' =>
                                        $longitude,

                                    'source' =>
                                        'OpenStreetMap',
                                ];
                            }
                        )
                        ->filter(
                            fn (array $place) =>
                                $place['latitude']
                                    !== null
                                && $place['longitude']
                                    !== null
                        )
                        ->values()
                        ->all();
                }
            );

            return response()->json([
                'results' => $results,
            ]);
        } catch (ConnectionException $exception) {
            return response()->json(
                [
                    'message' =>
                        'OpenStreetMap location search could not be reached.',

                    'results' => [],
                ],
                503
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'OpenStreetMap location search failed.',

                    'results' => [],
                ],
                502
            );
        }
    }

    public function reverseLocation(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        $cacheKey = 'route-photon-reverse:v1:'
            . sha1(
                number_format($latitude, 6, '.', '')
                . ','
                . number_format($longitude, 6, '.', '')
            );

        try {
            $place = Cache::remember(
                $cacheKey,
                now()->addDays(30),
                function () use (
                    $latitude,
                    $longitude
                ): ?array {
                    $response = Http::acceptJson()
                        ->withHeaders([
                            'User-Agent' =>
                                'GCT-Systems/1.0 (route reverse geocoding)',
                            'Accept-Language' =>
                                'en',
                        ])
                        ->timeout(10)
                        ->get(
                            rtrim(
                                (string) config(
                                    'services.photon.base_url',
                                    'https://photon.komoot.io'
                                ),
                                '/'
                            ) . '/reverse',
                            [
                                'lat' => $latitude,
                                'lon' => $longitude,
                                'lang' => 'en',
                            ]
                        )
                        ->throw();

                    $feature = collect(
                        $response->json(
                            'features',
                            []
                        )
                    )->first();

                    if (! is_array($feature)) {
                        return null;
                    }

                    $properties =
                        $feature['properties']
                        ?? [];

                    $coordinates = data_get(
                        $feature,
                        'geometry.coordinates',
                        []
                    );

                    $resolvedLongitude =
                        isset($coordinates[0])
                            ? (float) $coordinates[0]
                            : $longitude;

                    $resolvedLatitude =
                        isset($coordinates[1])
                            ? (float) $coordinates[1]
                            : $latitude;

                    $name = trim(
                        (string) (
                            $properties['name']
                            ?? $properties['street']
                            ?? $properties['district']
                            ?? $properties['city']
                            ?? $properties['town']
                            ?? $properties['village']
                            ?? $properties['municipality']
                            ?? ''
                        )
                    );

                    $address = collect([
                        $properties['name']
                            ?? null,
                        $properties['street']
                            ?? null,
                        $properties['district']
                            ?? null,
                        $properties['city']
                            ?? $properties['town']
                            ?? $properties['village']
                            ?? $properties['municipality']
                            ?? null,
                        $properties['state']
                            ?? null,
                        $properties['country']
                            ?? null,
                    ])
                        ->map(
                            fn ($value) =>
                                trim(
                                    (string) $value
                                )
                        )
                        ->filter()
                        ->unique()
                        ->implode(', ');

                    if ($name === '') {
                        $name = $address !== ''
                            ? explode(',', $address)[0]
                            : sprintf(
                                '%.5f, %.5f',
                                $latitude,
                                $longitude
                            );
                    }

                    return [
                        'id' => (string) (
                            $properties['osm_id']
                            ?? sha1(
                                json_encode(
                                    $feature
                                )
                            )
                        ),

                        'name' => $name,

                        'address' =>
                            $address !== ''
                                ? $address
                                : $name,

                        'latitude' =>
                            $resolvedLatitude,

                        'longitude' =>
                            $resolvedLongitude,

                        'source' =>
                            'Manual Pin',
                    ];
                }
            );

            return response()->json([
                'place' => $place,
            ]);
        } catch (ConnectionException $exception) {
            return response()->json(
                [
                    'message' =>
                        'The location lookup service could not be reached.',
                    'place' => null,
                ],
                503
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'Unable to identify the pinned location.',
                    'place' => null,
                ],
                502
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Road Route Calculation
    |--------------------------------------------------------------------------
    */

    public function calculateRoute(
        Request $request
    ): JsonResponse {
        $validated = $request->validate(
            [
                'points' => [
                    'required',
                    'array',
                    'min:2',
                    'max:20',
                ],

                'points.*.latitude' => [
                    'required',
                    'numeric',
                    'between:-90,90',
                ],

                'points.*.longitude' => [
                    'required',
                    'numeric',
                    'between:-180,180',
                ],
            ],
            [
                'points.required' =>
                    'Select an origin and destination.',

                'points.min' =>
                    'At least an origin and destination are required.',

                'points.max' =>
                    'A maximum of 20 route points is allowed.',
            ]
        );

        $this->assertRoutePointSpacing(
            $validated['points']
        );

        try {
            return response()->json(
                $this->calculateVerifiedRoute(
                    $validated['points']
                )
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            return response()->json(
                [
                    'message' =>
                        'The routing service could not be reached. Try again after the connection is restored.',
                ],
                503
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'Road route calculation failed. Please retry the confirmed route points.',
                ],
                502
            );
        }
    }

    private function calculateVerifiedRoute(
        array $points
    ): array {
        $coordinates = collect($points)
            ->map(
                fn (array $point) =>
                    sprintf(
                        '%.7F,%.7F',
                        $point['longitude'],
                        $point['latitude']
                    )
            )
            ->implode(';');

        $baseUrl = rtrim(
            (string) config(
                'services.osrm.base_url',
                'https://router.project-osrm.org'
            ),
            '/'
        );

        $operationalSpeedKph = max(
            5.0,
            (float) config(
                'services.osrm.operational_speed_kph',
                25
            )
        );

        $stopDwellMinutes = max(
            0,
            (int) config(
                'services.osrm.stop_dwell_minutes',
                1
            )
        );

        $intermediateStopCount = max(
            0,
            count($points) - 2
        );

        $cacheHash = sha1(
            $coordinates
            . '|'
            . number_format(
                $operationalSpeedKph,
                2,
                '.',
                ''
            )
            . '|'
            . $stopDwellMinutes
        );

        $freshKey =
            'route-osrm:v4:' . $cacheHash;

        $staleKey =
            'route-osrm-stale:v2:' . $cacheHash;

        $cached = Cache::get($freshKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->retry(2, 300)
                ->get(
                    "{$baseUrl}/route/v1/driving/{$coordinates}",
                    [
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                        'alternatives' => 'false',
                    ]
                )
                ->throw();

            if (
                $response->json('code') !== 'Ok'
                || ! $response->json('routes.0')
            ) {
                throw ValidationException::withMessages([
                    'points' =>
                        'No drivable route was found for the selected locations.',
                ]);
            }

            $route = $response->json('routes.0');

            $distanceKm = round(
                ((float) $route['distance']) / 1000,
                2
            );

            $osrmDurationMinutes = max(
                1,
                (int) round(
                    ((float) $route['duration']) / 60
                )
            );

            $operationalDurationMinutes = max(
                1,
                (int) ceil(
                    ($distanceKm / $operationalSpeedKph) * 60
                )
            );

            $dwellMinutes =
                $intermediateStopCount
                * $stopDwellMinutes;

            $result = [
                'distance_km' => $distanceKm,

                'duration_minutes' =>
                    max(
                        $osrmDurationMinutes,
                        $operationalDurationMinutes
                    ) + $dwellMinutes,

                'routing_duration_minutes' =>
                    $osrmDurationMinutes,

                'dwell_minutes' =>
                    $dwellMinutes,

                'geometry' =>
                    $route['geometry'],

                'source' =>
                    'OSRM Operational ETA',
            ];

            Cache::put(
                $freshKey,
                $result,
                now()->addHours(
                    max(
                        1,
                        (int) config(
                            'services.osrm.fresh_cache_hours',
                            12
                        )
                    )
                )
            );

            Cache::put(
                $staleKey,
                $result,
                now()->addDays(
                    max(
                        1,
                        (int) config(
                            'services.osrm.stale_cache_days',
                            30
                        )
                    )
                )
            );

            return $result;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $stale = Cache::get($staleKey);

            if (is_array($stale)) {
                $stale['source'] =
                    'OSRM Cached Operational ETA';

                return $stale;
            }

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store Route
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): RedirectResponse {
        $this->assertCanManageRoutes();

        $validated = $this->validateRoute(
            $request
        );

        $validated = array_merge(
            $validated,
            $this->verifiedRoutePayload(
                $validated
            )
        );

        DB::transaction(
            function () use ($validated): void {
                $routeCodes = ShuttleRoute::query()
                    ->lockForUpdate()
                    ->pluck('route_code');

                $this->assertUniqueRouteName(
                    $validated['route_name']
                );

                $routeCode =
                    $this->calculateNextRouteCode(
                        $routeCodes->all()
                    );

                $route = ShuttleRoute::create(
                    $this->routePayload(
                        $validated,
                        $routeCode
                    )
                );

                $this->saveStops(
                    $route,
                    $validated
                );
            }
        );

        session()->flash(
            'success',
            'Route created successfully.'
        );

        return new RedirectResponse(
            '/operation/routes'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Route
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        ShuttleRoute $shuttleRoute
    ): RedirectResponse {
        $this->assertCanManageRoutes();

        $routeWasAlreadyUsed =
            $shuttleRoute
                ->tripSchedules()
                ->exists();

        if ($routeWasAlreadyUsed) {
            $validatedStatus =
                $request->validate([
                    'status' => [
                        'required',
                        Rule::in([
                            'Active',
                            'Inactive',
                        ]),
                    ],
                ]);

            DB::transaction(
                function () use (
                    $validatedStatus,
                    $shuttleRoute
                ): void {
                    $lockedRoute = ShuttleRoute::query()
                        ->whereKey($shuttleRoute->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        $validatedStatus['status']
                            === 'Inactive'
                    ) {
                        $this->assertCanDeactivateRoute(
                            $lockedRoute
                        );
                    }

                    $lockedRoute->update([
                        'status' =>
                            $validatedStatus['status'],
                    ]);
                }
            );

            session()->flash(
                'success',
                'Route status updated. Historical route details remain protected.'
            );

            return new RedirectResponse(
                '/operation/routes'
            );
        }

        $validated = $this->validateRoute(
            $request,
            $shuttleRoute
        );

        $verifiedPayload =
            $this->verifiedRoutePayload(
                $validated
            );

        DB::transaction(
            function () use (
                $validated,
                $verifiedPayload,
                $shuttleRoute
            ): void {
                $lockedRoute = ShuttleRoute::query()
                    ->with('stops')
                    ->whereKey($shuttleRoute->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedRoute
                        ->tripSchedules()
                        ->exists()
                ) {
                    if (
                        $validated['status']
                            === 'Inactive'
                    ) {
                        $this->assertCanDeactivateRoute(
                            $lockedRoute
                        );
                    }

                    $lockedRoute->update([
                        'status' =>
                            $validated['status'],
                    ]);

                    return;
                }

                $this->assertUniqueRouteName(
                    $validated['route_name'],
                    $lockedRoute->id
                );

                $effective = array_merge(
                    $validated,
                    $verifiedPayload
                );

                $lockedRoute->update(
                    $this->routePayload(
                        $effective
                    )
                );

                $lockedRoute
                    ->stops()
                    ->delete();

                $this->saveStops(
                    $lockedRoute,
                    $effective
                );
            }
        );

        session()->flash(
            'success',
            'Route updated successfully.'
        );

        return new RedirectResponse(
            '/operation/routes'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Route
    |--------------------------------------------------------------------------
    */

    public function destroy(
        ShuttleRoute $shuttleRoute
    ): RedirectResponse {
        $this->assertCanManageRoutes();

        $deleted = DB::transaction(
            function () use (
                $shuttleRoute
            ): bool {
                $lockedRoute = ShuttleRoute::query()
                    ->whereKey($shuttleRoute->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedRoute
                        ->tripSchedules()
                        ->exists()
                ) {
                    return false;
                }

                $lockedRoute
                    ->stops()
                    ->delete();

                $lockedRoute->delete();

                return true;
            }
        );

        if (! $deleted) {
            session()->flash(
                'error',
                'This route cannot be deleted because it is already used by one or more trip schedules. Set the route to Inactive instead.'
            );

            return new RedirectResponse(
                '/operation/routes'
            );
        }

        session()->flash(
            'success',
            'Route deleted successfully.'
        );

        return new RedirectResponse(
            '/operation/routes'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Route Validation
    |--------------------------------------------------------------------------
    */

    private function validateRoute(
        Request $request,
        ?ShuttleRoute $shuttleRoute = null
    ): array {
        $request->merge([
            'route_name' =>
                $this->normalizeRouteText(
                    $request->input('route_name')
                ),

            'origin' =>
                $this->normalizeRouteText(
                    $request->input('origin')
                ),

            'destination' =>
                $this->normalizeRouteText(
                    $request->input('destination')
                ),

            'stops' => collect(
                $request->input('stops', [])
            )
                ->map(
                    fn ($stop) =>
                        $this->normalizeRouteText(
                            $stop
                        )
                )
                ->all(),
        ]);

        $validated = $request->validate(
            [
                'route_name' => [
                    'required',
                    'string',
                    'max:255',

                    Rule::unique(
                        'shuttle_routes',
                        'route_name'
                    )->ignore(
                        $shuttleRoute?->id
                    ),
                ],

                'origin' => [
                    'required',
                    'string',
                    'max:255',
                    'different:destination',
                ],

                'origin_address' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'origin_latitude' => [
                    'required',
                    'numeric',
                    'between:-90,90',
                ],

                'origin_longitude' => [
                    'required',
                    'numeric',
                    'between:-180,180',
                ],

                'origin_source' => [
                    'nullable',
                    'string',
                    'max:40',
                ],

                'destination' => [
                    'required',
                    'string',
                    'max:255',
                    'different:origin',
                ],

                'destination_address' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'destination_latitude' => [
                    'required',
                    'numeric',
                    'between:-90,90',
                ],

                'destination_longitude' => [
                    'required',
                    'numeric',
                    'between:-180,180',
                ],

                'destination_source' => [
                    'nullable',
                    'string',
                    'max:40',
                ],

                'distance_km' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:999999.99',
                ],

                'status' => [
                    'required',
                    Rule::in([
                        'Active',
                        'Inactive',
                    ]),
                ],

                'stops' => [
                    'nullable',
                    'array',
                    'max:18',
                ],

                'stops.*' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'stop_addresses' => [
                    'nullable',
                    'array',
                    'max:18',
                ],

                'stop_addresses.*' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'stop_latitudes' => [
                    'nullable',
                    'array',
                    'max:18',
                ],

                'stop_latitudes.*' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                ],

                'stop_longitudes' => [
                    'nullable',
                    'array',
                    'max:18',
                ],

                'stop_longitudes.*' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                ],

                'stop_sources' => [
                    'nullable',
                    'array',
                    'max:18',
                ],

                'stop_sources.*' => [
                    'nullable',
                    'string',
                    'max:40',
                ],
            ],
            [
                'route_name.required' =>
                    'The route name is required.',

                'route_name.unique' =>
                    'A route with this name already exists. Use a distinct name for route variants.',

                'origin.required' =>
                    'The route origin is required.',

                'origin.different' =>
                    'The origin and destination labels must be different.',

                'origin_latitude.required' =>
                    'Select a valid origin from the location suggestions or map.',

                'origin_longitude.required' =>
                    'Select a valid origin from the location suggestions or map.',

                'destination.required' =>
                    'The route destination is required.',

                'destination.different' =>
                    'The destination and origin labels must be different.',

                'destination_latitude.required' =>
                    'Select a valid destination from the location suggestions or map.',

                'destination_longitude.required' =>
                    'Select a valid destination from the location suggestions or map.',

                'status.required' =>
                    'Select the route status.',

                'status.in' =>
                    'The selected route status is invalid.',

                'stops.max' =>
                    'A route may contain a maximum of 18 intermediate stops.',
            ]
        );

        $this->assertStopCoordinatesComplete(
            $validated
        );

        $points =
            $this->routePointsFromValidated(
                $validated
            );

        $this->assertRoutePointSpacing(
            $points
        );

        return $validated;
    }

    /*
    |--------------------------------------------------------------------------
    | Route Integrity Helpers
    |--------------------------------------------------------------------------
    */

    private function assertCanManageRoutes(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $department =
            strtolower(
                trim(
                    (string) $user->department
                )
            );

        $role =
            strtolower(
                trim(
                    (string) $user->role
                )
            );

        $isOperationHead =
            $department === 'operation'
            && $role === 'head';

        if (
            $isOperationHead
            || $user->hasSystemPermission(
                'operation',
                'edit'
            )
        ) {
            return;
        }

        abort(
            403,
            'You are not authorized to create, edit, or delete route master records.'
        );
    }

    private function verifiedRoutePayload(
        array $validated
    ): array {
        try {
            $verified =
                $this->calculateVerifiedRoute(
                    $this->routePointsFromValidated(
                        $validated
                    )
                );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'origin' =>
                    'The route could not be verified by the routing service. Confirm the map locations and try again.',
            ]);
        }

        return [
            'distance_km' =>
                $verified['distance_km'],

            'calculated_distance_km' =>
                $verified['distance_km'],

            'calculated_time_minutes' =>
                $verified['duration_minutes'],

            'distance_source' =>
                $verified['source'],

            'distance_is_manual' =>
                false,

            'time_is_manual' =>
                false,

            'route_geometry' =>
                $verified['geometry'],
        ];
    }

    private function assertUniqueRouteName(
        string $routeName,
        ?int $ignoreRouteId = null
    ): void {
        $normalizedName =
            mb_strtolower(
                $this->normalizeRouteText(
                    $routeName
                )
            );

        $query = ShuttleRoute::query()
            ->whereRaw(
                'LOWER(TRIM(route_name)) = ?',
                [$normalizedName]
            )
            ->lockForUpdate();

        if ($ignoreRouteId !== null) {
            $query->where(
                'id',
                '!=',
                $ignoreRouteId
            );
        }

        if (
            $query
                ->first(['id'])
                !== null
        ) {
            throw ValidationException::withMessages([
                'route_name' =>
                    'A route with this name already exists. Use a distinct name for route variants.',
            ]);
        }
    }

    private function assertCanDeactivateRoute(
        ShuttleRoute $route
    ): void {
        $upcomingTrips = $route
            ->tripSchedules()
            ->whereDate(
                'trip_date',
                '>=',
                now()->toDateString()
            )
            ->whereNotIn(
                'status',
                [
                    'Completed',
                    'Cancelled',
                ]
            )
            ->lockForUpdate()
            ->get(['id']);

        if ($upcomingTrips->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'This route has %d current or upcoming trip(s). Cancel or move those trips before setting the route to Inactive.',
                $upcomingTrips->count()
            ),
        ]);
    }

    private function assertStopCoordinatesComplete(
        array $validated
    ): void {
        foreach (
            $validated['stops'] ?? []
            as $index => $stop
        ) {
            $name =
                $this->normalizeRouteText(
                    $stop
                );

            if ($name === '') {
                continue;
            }

            $latitude =
                $validated[
                    'stop_latitudes'
                ][$index] ?? null;

            $longitude =
                $validated[
                    'stop_longitudes'
                ][$index] ?? null;

            if (
                ! is_numeric($latitude)
                || ! is_numeric($longitude)
            ) {
                throw ValidationException::withMessages([
                    "stops.{$index}" =>
                        sprintf(
                            'Confirm %s from the location suggestions or pin it on the map.',
                            $name
                        ),
                ]);
            }
        }
    }

    private function routePointsFromValidated(
        array $validated
    ): array {
        $points = [
            [
                'latitude' =>
                    (float) $validated[
                        'origin_latitude'
                    ],

                'longitude' =>
                    (float) $validated[
                        'origin_longitude'
                    ],
            ],
        ];

        foreach (
            $validated['stops'] ?? []
            as $index => $stop
        ) {
            if (
                $this->normalizeRouteText(
                    $stop
                ) === ''
            ) {
                continue;
            }

            $points[] = [
                'latitude' =>
                    (float) $validated[
                        'stop_latitudes'
                    ][$index],

                'longitude' =>
                    (float) $validated[
                        'stop_longitudes'
                    ][$index],
            ];
        }

        $points[] = [
            'latitude' =>
                (float) $validated[
                    'destination_latitude'
                ],

            'longitude' =>
                (float) $validated[
                    'destination_longitude'
                ],
        ];

        return $points;
    }

    private function assertRoutePointSpacing(
        array $points
    ): void {
        if (count($points) < 2) {
            return;
        }

        $origin = $points[0];
        $destination =
            $points[count($points) - 1];

        if (
            $this->distanceBetweenPointsKm(
                $origin,
                $destination
            )
            < self::MIN_ENDPOINT_DISTANCE_KM
        ) {
            throw ValidationException::withMessages([
                'destination' =>
                    'Origin and destination resolve to the same physical location. Choose points at least 50 meters apart.',
            ]);
        }

        for (
            $index = 1;
            $index < count($points);
            $index++
        ) {
            if (
                $this->distanceBetweenPointsKm(
                    $points[$index - 1],
                    $points[$index]
                ) < 0.01
            ) {
                throw ValidationException::withMessages([
                    'stops' =>
                        'Two consecutive route points are effectively the same location. Move or remove the duplicate stop.',
                ]);
            }
        }
    }

    private function distanceBetweenPointsKm(
        array $first,
        array $second
    ): float {
        $earthRadiusKm = 6371.0088;

        $lat1 = deg2rad(
            (float) $first['latitude']
        );

        $lat2 = deg2rad(
            (float) $second['latitude']
        );

        $deltaLat = deg2rad(
            (float) $second['latitude']
            - (float) $first['latitude']
        );

        $deltaLon = deg2rad(
            (float) $second['longitude']
            - (float) $first['longitude']
        );

        $a =
            sin($deltaLat / 2) ** 2
            + cos($lat1)
            * cos($lat2)
            * sin($deltaLon / 2) ** 2;

        $a = min(
            1.0,
            max(
                0.0,
                $a
            )
        );

        return $earthRadiusKm
            * 2
            * atan2(
                sqrt($a),
                sqrt(1 - $a)
            );
    }

    private function normalizeRouteText(
        mixed $value
    ): string {
        return preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $value)
        ) ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | Route Database Payload
    |--------------------------------------------------------------------------
    */

    private function routePayload(
        array $validated,
        ?string $routeCode = null
    ): array {
        $geometry =
            $validated['route_geometry']
            ?? null;

        if (is_string($geometry)) {
            $decodedGeometry = json_decode(
                $geometry,
                true
            );

            $geometry =
                is_array($decodedGeometry)
                    ? $decodedGeometry
                    : null;
        }

        if (! is_array($geometry)) {
            $geometry = null;
        }

        $payload = [
            'route_name' => trim(
                $validated['route_name']
            ),

            'origin' => trim(
                $validated['origin']
            ),

            'origin_address' =>
                $this->nullableTrim(
                    $validated[
                        'origin_address'
                    ] ?? null
                ),

            'origin_latitude' =>
                $validated[
                    'origin_latitude'
                ],

            'origin_longitude' =>
                $validated[
                    'origin_longitude'
                ],

            'origin_source' =>
                $this->nullableTrim(
                    $validated[
                        'origin_source'
                    ] ?? null
                ),

            'destination' => trim(
                $validated['destination']
            ),

            'destination_address' =>
                $this->nullableTrim(
                    $validated[
                        'destination_address'
                    ] ?? null
                ),

            'destination_latitude' =>
                $validated[
                    'destination_latitude'
                ],

            'destination_longitude' =>
                $validated[
                    'destination_longitude'
                ],

            'destination_source' =>
                $this->nullableTrim(
                    $validated[
                        'destination_source'
                    ] ?? null
                ),

            'distance_km' =>
                $validated[
                    'calculated_distance_km'
                ],

            'estimated_time_minutes' =>
                $validated[
                    'calculated_time_minutes'
                ],

            'calculated_distance_km' =>
                $validated[
                    'calculated_distance_km'
                ] ?? null,

            'calculated_time_minutes' =>
                $validated[
                    'calculated_time_minutes'
                ] ?? null,

            'distance_source' =>
                $this->nullableTrim(
                    $validated[
                        'distance_source'
                    ] ?? null
                ),

            'distance_is_manual' =>
                false,

            'time_is_manual' =>
                false,

            'route_geometry' =>
                $geometry,

            'route_calculated_at' =>
                $geometry !== null
                    ? now()
                    : null,

            'status' =>
                $validated['status'],
        ];

        if ($routeCode !== null) {
            $payload['route_code'] =
                $routeCode;
        }

        return $payload;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Route Stops
    |--------------------------------------------------------------------------
    */

    private function saveStops(
        ShuttleRoute $route,
        array $validated
    ): void {
        $stops =
            $validated['stops']
            ?? [];

        $addresses =
            $validated['stop_addresses']
            ?? [];

        $latitudes =
            $validated['stop_latitudes']
            ?? [];

        $longitudes =
            $validated['stop_longitudes']
            ?? [];

        $sources =
            $validated['stop_sources']
            ?? [];

        $stopOrder = 1;

        foreach (
            $stops as $index => $stop
        ) {
            $stopName = trim(
                (string) $stop
            );

            if ($stopName === '') {
                continue;
            }

            $route->stops()->create([
                'stop_name' =>
                    $stopName,

                'stop_order' =>
                    $stopOrder++,

                'address' =>
                    $this->nullableTrim(
                        $addresses[
                            $index
                        ] ?? null
                    ),

                'latitude' =>
                    $latitudes[
                        $index
                    ] ?? null,

                'longitude' =>
                    $longitudes[
                        $index
                    ] ?? null,

                'location_source' =>
                    $this->nullableTrim(
                        $sources[
                            $index
                        ] ?? null
                    ),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Next Route Code
    |--------------------------------------------------------------------------
    */

    private function generateNextRouteCode(): string
    {
        return $this->calculateNextRouteCode(
            ShuttleRoute::query()
                ->pluck('route_code')
                ->all()
        );
    }

    private function calculateNextRouteCode(
        array $routeCodes
    ): string {
        $highestNumber = 0;

        foreach ($routeCodes as $routeCode) {
            if (
                preg_match(
                    '/^R-(\d+)$/i',
                    trim((string) $routeCode),
                    $matches
                )
            ) {
                $highestNumber = max(
                    $highestNumber,
                    (int) $matches[1]
                );
            }
        }

        $nextNumber =
            $highestNumber + 1;

        do {
            $candidate =
                'R-'
                . str_pad(
                    (string) $nextNumber,
                    2,
                    '0',
                    STR_PAD_LEFT
                );

            $exists = in_array(
                strtoupper($candidate),
                array_map(
                    fn ($code) =>
                        strtoupper(
                            trim(
                                (string) $code
                            )
                        ),
                    $routeCodes
                ),
                true
            );

            $nextNumber++;
        } while ($exists);

        return $candidate;
    }

    /*
    |--------------------------------------------------------------------------
    | GPS Location Suggestions
    |--------------------------------------------------------------------------
    */

    private function buildGpsLocationSuggestions(
        array $records
    ): array {
        $locations = [];

        foreach ($records as $record) {
            $coordinates = (string) (
                $record['coordinates']
                ?? ''
            );

            $matched = preg_match(
                '/(-?\d+(?:\.\d+)?)\s*,\s*'
                . '(-?\d+(?:\.\d+)?)\s*'
                . '(?:->|→|to)\s*'
                . '(-?\d+(?:\.\d+)?)\s*,\s*'
                . '(-?\d+(?:\.\d+)?)/i',
                $coordinates,
                $matches
            );

            if (! $matched) {
                continue;
            }

            $pairs = [
                [
                    $record[
                        'initial_location'
                    ] ?? null,

                    (float) $matches[1],
                    (float) $matches[2],
                ],

                [
                    $record[
                        'final_location'
                    ] ?? null,

                    (float) $matches[3],
                    (float) $matches[4],
                ],
            ];

            foreach (
                $pairs as [
                    $name,
                    $latitude,
                    $longitude,
                ]
            ) {
                $locationName = trim(
                    (string) $name
                );

                if ($locationName === '') {
                    continue;
                }

                $key = mb_strtolower(
                    $locationName
                );

                $locations[$key] ??= [
                    'id' =>
                        'gps-'
                        . sha1($key),

                    'name' =>
                        $locationName,

                    'address' =>
                        $locationName,

                    'latitude' =>
                        $latitude,

                    'longitude' =>
                        $longitude,

                    'source' =>
                        'GPS Batch',

                    'grouping' =>
                        $record[
                            'grouping'
                        ] ?? null,

                    'bus_no' =>
                        $record[
                            'bus_no'
                        ] ?? null,
                ];
            }
        }

        return array_values(
            $locations
        );
    }

    /*
    |--------------------------------------------------------------------------
    | String Helper
    |--------------------------------------------------------------------------
    */

    private function nullableTrim(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }
}