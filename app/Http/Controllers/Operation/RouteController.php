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
    /*
    |--------------------------------------------------------------------------
    | Route List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        $query = ShuttleRoute::query()
            ->with('stops');

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
                    'min:3',
                    'max:160',
                ],
            ],
            [
                'q.required' =>
                    'Enter a location to search.',

                'q.min' =>
                    'Enter at least three characters.',

                'q.max' =>
                    'The location search may not exceed 160 characters.',
            ]
        );

        $apiKey = trim(
            (string) config(
                'services.geoapify.key'
            )
        );

        if ($apiKey === '') {
            return response()->json(
                [
                    'message' =>
                        'Geoapify is not configured. Add GEOAPIFY_API_KEY to the environment settings.',

                    'results' => [],
                ],
                503
            );
        }

        $search = trim(
            (string) $validated['q']
        );

        $cacheKey =
            'route-geocode:'
            . sha1(
                mb_strtolower($search)
            );

        try {
            $results = Cache::remember(
                $cacheKey,
                now()->addDays(7),
                function () use (
                    $search,
                    $apiKey
                ): array {
                    $response = Http::acceptJson()
                        ->timeout(10)
                        ->retry(2, 250)
                        ->get(
                            'https://api.geoapify.com/v1/geocode/autocomplete',
                            [
                                'text' => $search,
                                'format' => 'json',
                                'filter' =>
                                    'countrycode:ph',
                                'limit' => 5,
                                'lang' => 'en',
                                'apiKey' => $apiKey,
                            ]
                        )
                        ->throw();

                    return collect(
                        $response->json(
                            'results',
                            []
                        )
                    )
                        ->map(
                            function (
                                array $place
                            ): array {
                                return [
                                    'id' => (string) (
                                        $place['place_id']
                                        ?? sha1(
                                            json_encode(
                                                $place
                                            )
                                        )
                                    ),

                                    'name' => (string) (
                                        $place['name']
                                        ?? $place['address_line1']
                                        ?? $place['formatted']
                                        ?? 'Unknown location'
                                    ),

                                    'address' => (string) (
                                        $place['formatted']
                                        ?? $place['address_line2']
                                        ?? ''
                                    ),

                                    'latitude' =>
                                        isset($place['lat'])
                                            ? (float) $place['lat']
                                            : null,

                                    'longitude' =>
                                        isset($place['lon'])
                                            ? (float) $place['lon']
                                            : null,

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
                        'The location service could not be reached.',

                    'results' => [],
                ],
                503
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'Location search failed.',

                    'results' => [],
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

        $coordinates = collect(
            $validated['points']
        )
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

        $cacheKey =
            'route-osrm:'
            . sha1($coordinates);

        try {
            $result = Cache::remember(
                $cacheKey,
                now()->addHours(12),
                function () use (
                    $baseUrl,
                    $coordinates
                ): array {
                    $response = Http::acceptJson()
                        ->timeout(15)
                        ->retry(2, 300)
                        ->get(
                            "{$baseUrl}/route/v1/driving/{$coordinates}",
                            [
                                'overview' => 'full',
                                'geometries' =>
                                    'geojson',
                                'steps' => 'false',
                                'alternatives' =>
                                    'false',
                            ]
                        )
                        ->throw();

                    if (
                        $response->json('code')
                            !== 'Ok'
                        || ! $response->json(
                            'routes.0'
                        )
                    ) {
                        throw ValidationException::withMessages(
                            [
                                'points' =>
                                    'No drivable route was found for the selected locations.',
                            ]
                        );
                    }

                    $route = $response->json(
                        'routes.0'
                    );

                    return [
                        'distance_km' => round(
                            (
                                (float) $route[
                                    'distance'
                                ]
                            ) / 1000,
                            2
                        ),

                        'duration_minutes' => max(
                            1,
                            (int) round(
                                (
                                    (float) $route[
                                        'duration'
                                    ]
                                ) / 60
                            )
                        ),

                        'geometry' =>
                            $route['geometry'],

                        'source' => 'OSRM',
                    ];
                }
            );

            return response()->json($result);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            return response()->json(
                [
                    'message' =>
                        'The routing service could not be reached.',
                ],
                503
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'Road route calculation failed.',
                ],
                502
            );
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
        $validated = $this->validateRoute(
            $request
        );

        DB::transaction(
            function () use ($validated): void {
                /*
                 * Lock existing route rows so concurrent
                 * requests cannot generate the same code.
                 */
                $routeCodes = ShuttleRoute::query()
                    ->lockForUpdate()
                    ->pluck('route_code');

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
        $validated = $this->validateRoute(
            $request,
            $shuttleRoute
        );

        DB::transaction(
            function () use (
                $validated,
                $shuttleRoute
            ): void {
                $shuttleRoute->update(
                    $this->routePayload(
                        $validated
                    )
                );

                /*
                 * Rebuild the stops to preserve the
                 * submitted stop ordering.
                 */
                $shuttleRoute
                    ->stops()
                    ->delete();

                $this->saveStops(
                    $shuttleRoute,
                    $validated
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
        /*
         * A route referenced by Trip Schedule must
         * remain available for historical records.
         */
        if (
            $shuttleRoute
                ->tripSchedules()
                ->exists()
        ) {
            session()->flash(
                'error',
                'This route cannot be deleted because it is already used by one or more trip schedules. Set the route to Inactive instead.'
            );

            return new RedirectResponse(
                '/operation/routes'
            );
        }

        DB::transaction(
            function () use (
                $shuttleRoute
            ): void {
                /*
                 * Route stops are configured with
                 * cascade deletion, but deleting them
                 * explicitly also keeps this behavior
                 * clear at the application level.
                 */
                $shuttleRoute
                    ->stops()
                    ->delete();

                $shuttleRoute->delete();
            }
        );

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
        return $request->validate(
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

                'estimated_time_minutes' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100000',
                ],

                'calculated_distance_km' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:999999.99',
                ],

                'calculated_time_minutes' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100000',
                ],

                'distance_source' => [
                    'nullable',
                    'string',
                    'max:40',
                ],

                'distance_is_manual' => [
                    'nullable',
                    'boolean',
                ],

                'time_is_manual' => [
                    'nullable',
                    'boolean',
                ],

                'route_geometry' => [
                    'nullable',
                    'json',
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
                    'A route with this name already exists.',

                'origin.required' =>
                    'The route origin is required.',

                'origin.different' =>
                    'The origin and destination must be different.',

                'origin_latitude.required' =>
                    'Select a valid origin from the location suggestions or map.',

                'origin_longitude.required' =>
                    'Select a valid origin from the location suggestions or map.',

                'destination.required' =>
                    'The route destination is required.',

                'destination.different' =>
                    'The destination and origin must be different.',

                'destination_latitude.required' =>
                    'Select a valid destination from the location suggestions or map.',

                'destination_longitude.required' =>
                    'Select a valid destination from the location suggestions or map.',

                'distance_km.min' =>
                    'The route distance cannot be negative.',

                'estimated_time_minutes.min' =>
                    'The estimated travel time must be at least one minute.',

                'status.required' =>
                    'Select the route status.',

                'status.in' =>
                    'The selected route status is invalid.',

                'stops.max' =>
                    'A route may contain a maximum of 18 intermediate stops.',

                'route_geometry.json' =>
                    'The calculated route geometry is invalid.',
            ]
        );
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
        $geometry = null;

        if (
            ! empty(
                $validated['route_geometry']
            )
        ) {
            $decodedGeometry = json_decode(
                $validated['route_geometry'],
                true
            );

            if (
                is_array($decodedGeometry)
            ) {
                $geometry = $decodedGeometry;
            }
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
                    'distance_km'
                ] ?? null,

            'estimated_time_minutes' =>
                $validated[
                    'estimated_time_minutes'
                ] ?? null,

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
                (bool) (
                    $validated[
                        'distance_is_manual'
                    ] ?? false
                ),

            'time_is_manual' =>
                (bool) (
                    $validated[
                        'time_is_manual'
                    ] ?? false
                ),

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