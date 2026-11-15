<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TripScheduleController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private const MIN_ROUTE_DEPARTURE_INTERVAL_MINUTES = 15;

    public function index(Request $request): View
    {
        $query = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'assignment',
            ])
            ->withCount([
                'dailyDriverReports',
                'incidents',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('trip_code', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('assignment_status', 'like', "%{$search}%")
                    ->orWhereHas('shuttleRoute', function ($routeQuery) use ($search) {
                        $routeQuery
                            ->where('route_code', 'like', "%{$search}%")
                            ->orWhere('route_name', 'like', "%{$search}%")
                            ->orWhere('origin', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%");
                    });
            });
        }

        // Trip Schedule opens on the current operational day by default.
        // Explicit date selections continue to show historical/future trips.
        $request->validate([
            'trip_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        if ($request->filled('trip_date')) {
            $request->session()->put(
                'operation.selected_trip_date',
                $request->input('trip_date')
            );
        }

        $selectedTripDate = $request->input('trip_date')
            ?: $request->session()->get('operation.selected_trip_date')
            ?: now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $query->whereDate('trip_date', $selectedTripDate);

        if (
            $request->filled('route')
            && $request->input('route') !== 'all'
        ) {
            $query->where(
                'shuttle_route_id',
                $request->integer('route')
            );
        }

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $query->where(
                'status',
                $request->input('status')
            );
        }

        $trips = $query
            ->orderByDesc('trip_date')
            ->orderBy('departure_time')
            ->paginate(8)
            ->withQueryString();

        $activeRoutes = ShuttleRoute::query()
            ->where('status', 'Active')
            ->orderBy('route_code')
            ->get([
                'id',
                'route_code',
                'route_name',
                'origin',
                'destination',
                'estimated_time_minutes',
                'calculated_time_minutes',
            ]);

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $operationalTodayTrips = TripSchedule::query()
            ->whereDate('trip_date', $today)
            ->where('status', '!=', 'Cancelled');

        $totalTripsToday = (clone $operationalTodayTrips)->count();

        $assignedTrips = (clone $operationalTodayTrips)
            ->where(function ($query) {
                $query
                    ->where('assignment_status', 'Assigned')
                    ->orWhereHas('assignment');
            })
            ->count();

        $pendingAssignments = (clone $operationalTodayTrips)
            ->where('assignment_status', 'Unassigned')
            ->whereDoesntHave('assignment')
            ->where('status', 'Scheduled')
            ->count();

        $activeRoutesUsed = (clone $operationalTodayTrips)
            ->distinct()
            ->count('shuttle_route_id');

        return view(
            'Operation.Scheduling_And_Dispatch.trip-schedule',
            compact(
                'trips',
                'activeRoutes',
                'totalTripsToday',
                'assignedTrips',
                'pendingAssignments',
                'activeRoutesUsed',
                'selectedTripDate'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $this->validateTrip($request);

            $trip = DB::transaction(function () use ($validated): TripSchedule {
                $route = ShuttleRoute::query()
                    ->whereKey($validated['shuttle_route_id'])
                    ->where('status', 'Active')
                    ->lockForUpdate()
                    ->firstOrFail();

                $departure = $this->departureDateTime(
                    $validated['trip_date'],
                    $validated['departure_time']
                );

                $this->assertMinimumRouteDepartureInterval(
                    $validated['trip_date'],
                    $route,
                    $departure
                );

                $estimatedArrival = $this->resolveArrivalDateTime(
                    $departure,
                    $this->routeDurationMinutes($route)
                );

                $trip = TripSchedule::create([
                    'trip_code' => 'TMP-'.Str::upper(Str::random(20)),
                    'trip_date' => $validated['trip_date'],
                    'shuttle_route_id' => $route->id,
                    'departure_time' => $departure->format('H:i:s'),
                    'estimated_arrival_time' => $estimatedArrival->format('H:i:s'),
                    'estimated_arrival_date' => $estimatedArrival->toDateString(),
                    'shift' => $this->detectShift($departure),
                    'assignment_status' => 'Unassigned',
                    'status' => 'Scheduled',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $trip->update([
                    'trip_code' => $this->tripCodeFromId($trip->id),
                ]);

                return $trip;
            });
        } catch (ValidationException $exception) {
            $this->rememberTripValidation($request, 'create');

            throw $exception;
        }

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'created',
            $trip->id,
            "{$trip->trip_code} was added to Trip Schedule."
        );

        // Show the date just created, including after leaving and returning.
        $request->session()->put('operation.selected_trip_date', $validated['trip_date']);

        session()->flash(
            'success',
            'Trip schedule created successfully.'
        );

        return $this->scheduleReturnRedirect($request);
    }

    public function update(
        Request $request,
        TripSchedule $tripSchedule
    ): RedirectResponse {
        if (! $this->tripCanBeEdited($tripSchedule)) {
            session()->flash(
                'error',
                'Only unassigned Scheduled/Cancelled trips without operational history can be edited.'
            );

            return $this->scheduleReturnRedirect($request);
        }

        try {
            $validated = $this->validateTrip(
                $request,
                $tripSchedule
            );

            $updatedTrip = DB::transaction(
                function () use ($validated, $tripSchedule): TripSchedule {
                    $route = ShuttleRoute::query()
                        ->whereKey($validated['shuttle_route_id'])
                        ->where('status', 'Active')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedTrip = TripSchedule::query()
                        ->whereKey($tripSchedule->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertEditableTripState($lockedTrip);

                    $departure = $this->departureDateTime(
                        $validated['trip_date'],
                        $validated['departure_time']
                    );

                    if ($validated['status'] !== 'Cancelled') {
                        $this->assertMinimumRouteDepartureInterval(
                            $validated['trip_date'],
                            $route,
                            $departure,
                            $lockedTrip->id
                        );
                    }

                    $estimatedArrival = $this->resolveArrivalDateTime(
                        $departure,
                        $this->routeDurationMinutes($route)
                    );

                    $lockedTrip->update([
                        'trip_date' => $validated['trip_date'],
                        'shuttle_route_id' => $route->id,
                        'departure_time' => $departure->format('H:i:s'),
                        'estimated_arrival_time' => $estimatedArrival->format('H:i:s'),
                        'estimated_arrival_date' => $estimatedArrival->toDateString(),
                        'shift' => $this->detectShift($departure),
                        'status' => $validated['status'],
                        'notes' => $validated['notes'] ?? null,
                    ]);

                    return $lockedTrip;
                }
            );
        } catch (ValidationException $exception) {
            if (! array_key_exists('trip_schedule', $exception->errors())) {
                $this->rememberTripValidation(
                    $request,
                    'edit',
                    $tripSchedule->id,
                    $tripSchedule->trip_code
                );
            }

            throw $exception;
        }

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $updatedTrip->id,
            "{$updatedTrip->trip_code} was updated in Trip Schedule."
        );

        session()->flash(
            'success',
            'Trip schedule updated successfully.'
        );

        return $this->scheduleReturnRedirect($request);
    }

    public function destroy(
        Request $request,
        TripSchedule $tripSchedule
    ): RedirectResponse {
        $deleteError = DB::transaction(
            function () use ($tripSchedule): ?string {
                $lockedTrip = TripSchedule::query()
                    ->whereKey($tripSchedule->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedTrip->loadMissing('assignment');
                $lockedTrip->loadCount([
                    'dailyDriverReports',
                    'incidents',
                ]);

                if (
                    ! in_array(
                        $lockedTrip->status,
                        ['Scheduled', 'Cancelled'],
                        true
                    )
                ) {
                    return 'Only Scheduled or Cancelled trips may be deleted.';
                }

                if (
                    $lockedTrip->assignment_status !== 'Unassigned'
                    || $lockedTrip->assignment !== null
                ) {
                    return 'Remove the driver and bus assignment before deleting this trip.';
                }

                if (
                    $lockedTrip->daily_driver_reports_count > 0
                    || $lockedTrip->incidents_count > 0
                ) {
                    return 'This trip already has operational history and cannot be deleted. Cancel it instead.';
                }

                $lockedTrip->delete();

                return null;
            }
        );

        if ($deleteError !== null) {
            session()->flash('error', $deleteError);

            return $this->scheduleReturnRedirect($request);
        }

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'deleted',
            $tripSchedule->id,
            "{$tripSchedule->trip_code} was removed from Trip Schedule."
        );

        session()->flash(
            'success',
            'Trip schedule deleted successfully.'
        );

        return $this->scheduleReturnRedirect($request);
    }

    private function scheduleReturnRedirect(Request $request): RedirectResponse
    {
        $context = $request->validate([
            'return_trip_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'return_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'return_status' => ['sometimes', Rule::in(['all', 'Scheduled', 'Ready', 'Dispatched', 'Completed', 'Cancelled'])],
        ]);

        $filters = array_filter([
            'trip_date' => $context['return_trip_date'] ?? null,
            'search' => $context['return_search'] ?? null,
            'status' => $context['return_status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return new RedirectResponse('/operation/trip-schedule'
            .($filters ? '?'.http_build_query($filters) : ''));
    }

    private function validateTrip(
        Request $request,
        ?TripSchedule $tripSchedule = null
    ): array {
        $allowsHistoricalCorrection = $tripSchedule !== null
            && $tripSchedule->hasDeparted()
            && $this->tripCanBeEdited($tripSchedule);

        $tripDateRules = [
            'required',
            'date',
        ];

        if (! $allowsHistoricalCorrection) {
            $tripDateRules[] = 'after_or_equal:'
                .now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        }

        $rules = [
            'trip_date' => $tripDateRules,
            'shuttle_route_id' => [
                'required',
                'integer',
                Rule::exists('shuttle_routes', 'id')
                    ->where('status', 'Active'),
            ],
            'departure_time' => [
                'required',
                'date_format:H:i',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];

        if ($tripSchedule !== null) {
            $rules['status'] = [
                'required',
                Rule::in([
                    'Scheduled',
                    'Cancelled',
                ]),
            ];
        }

        $validated = $request->validate($rules);

        $requestedDeparture = $this->departureDateTime(
            $validated['trip_date'],
            $validated['departure_time']
        );

        if (
            $requestedDeparture->lt(now(config('app.business_timezone', 'Asia/Manila'))->startOfMinute())
            && ! $allowsHistoricalCorrection
        ) {
            throw ValidationException::withMessages([
                'departure_time' => 'Trip departure must be the current time or a future time.',
            ]);
        }

        return $validated;
    }

    private function assertMinimumRouteDepartureInterval(
        string $tripDate,
        ShuttleRoute $route,
        Carbon $departure,
        ?int $ignoreTripId = null
    ): void {
        $conflictingTrip = $this->findRouteDepartureConflict(
            $tripDate,
            $route,
            $departure,
            $ignoreTripId
        );

        if ($conflictingTrip === null) {
            return;
        }

        $existingDeparture = Carbon::parse(
            $conflictingTrip->departure_time
        );

        $differenceMinutes = abs(
            $this->minutesFromMidnight($existingDeparture)
            - $this->minutesFromMidnight($departure)
        );

        if ($differenceMinutes === 0) {
            throw ValidationException::withMessages([
                'departure_time' => sprintf(
                    'Duplicate trip: %s is already scheduled on %s at %s.',
                    $route->route_code,
                    Carbon::parse($tripDate)->format('M d, Y'),
                    $departure->format('g:i A')
                ),
            ]);
        }

        throw ValidationException::withMessages([
            'departure_time' => sprintf(
                'Departure too close: %s already has a trip at %s. Keep at least %d minutes between departures for the same route.',
                $route->route_code,
                $existingDeparture->format('g:i A'),
                self::MIN_ROUTE_DEPARTURE_INTERVAL_MINUTES
            ),
        ]);
    }

    private function findRouteDepartureConflict(
        string $tripDate,
        ShuttleRoute $route,
        Carbon $departure,
        ?int $ignoreTripId = null
    ): ?TripSchedule {
        $query = TripSchedule::query()
            ->whereDate('trip_date', $tripDate)
            ->where('shuttle_route_id', $route->id)
            ->where('status', '!=', 'Cancelled')
            ->lockForUpdate();

        if ($ignoreTripId !== null) {
            $query->where('id', '!=', $ignoreTripId);
        }

        $departureMinutes = $this->minutesFromMidnight(
            $departure
        );

        return $query
            ->get([
                'id',
                'departure_time',
            ])
            ->first(
                function (TripSchedule $trip) use ($departureMinutes): bool {
                    $existingDeparture = Carbon::parse(
                        $trip->departure_time
                    );

                    $differenceMinutes = abs(
                        $this->minutesFromMidnight($existingDeparture)
                        - $departureMinutes
                    );

                    return $differenceMinutes
                        < self::MIN_ROUTE_DEPARTURE_INTERVAL_MINUTES;
                }
            );
    }

    private function assertEditableTripState(
        TripSchedule $tripSchedule
    ): void {
        if (! $this->tripCanBeEdited($tripSchedule)) {
            throw ValidationException::withMessages([
                'trip_schedule' => 'This trip is no longer editable because it is assigned, dispatched, completed, or already has operational history.',
            ]);
        }
    }

    private function tripCanBeEdited(
        TripSchedule $tripSchedule
    ): bool {
        return $tripSchedule->canBeManagedFromSchedule();
    }

    private function rememberTripValidation(
        Request $request,
        string $mode,
        ?int $tripId = null,
        ?string $tripCode = null
    ): void {
        session()->flash('trip_validation_mode', $mode);
        session()->flash('trip_validation_id', $tripId);
        session()->flash('trip_validation_code', $tripCode);
        session()->flashInput(
            $request->except([
                '_token',
                '_method',
            ])
        );
    }

    private function departureDateTime(
        string $tripDate,
        string $departureTime
    ): Carbon {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$tripDate} {$departureTime}",
            config('app.business_timezone', 'Asia/Manila')
        );
    }

    private function resolveArrivalDateTime(
        Carbon $departure,
        int $routeDurationMinutes
    ): Carbon {
        return $departure
            ->copy()
            ->addMinutes(
                max(1, $routeDurationMinutes)
            );
    }

    private function routeDurationMinutes(
        ShuttleRoute $route
    ): int {
        return max(
            1,
            (int) (
                $route->calculated_time_minutes
                ?: $route->estimated_time_minutes
                ?: 60
            )
        );
    }

    private function minutesFromMidnight(
        Carbon $time
    ): int {
        return (
            ((int) $time->format('H')) * 60
        ) + (int) $time->format('i');
    }

    private function tripCodeFromId(
        int $tripId
    ): string {
        return 'T-'.str_pad(
            (string) $tripId,
            3,
            '0',
            STR_PAD_LEFT
        );
    }

    private function detectShift(Carbon $departure): string
    {
        return TripSchedule::shiftForDeparture($departure);
    }
}
