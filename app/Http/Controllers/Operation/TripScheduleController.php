<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $query = TripSchedule::query()
            ->with('shuttleRoute');

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

        if ($request->filled('trip_date')) {
            $query->whereDate(
                'trip_date',
                $request->input('trip_date')
            );
        }

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
            ]);

        $reusableScheduleDates = TripSchedule::query()
            ->where('status', '!=', 'Cancelled')
            ->whereHas('shuttleRoute', fn ($routeQuery) => $routeQuery->where('status', 'Active'))
            ->selectRaw('DATE(trip_date) as schedule_date, COUNT(*) as trip_count')
            ->groupByRaw('DATE(trip_date)')
            ->orderByDesc('schedule_date')
            ->get();

        $today = now()->toDateString();

        $todayTrips = TripSchedule::query()
            ->whereDate('trip_date', $today);

        $totalTripsToday = (clone $todayTrips)->count();

        $assignedTrips = (clone $todayTrips)
            ->where('assignment_status', 'Assigned')
            ->count();

        $pendingAssignments = (clone $todayTrips)
            ->where('assignment_status', 'Unassigned')
            ->count();

        $activeRoutesUsed = (clone $todayTrips)
            ->distinct()
            ->count('shuttle_route_id');

        return view(
            'Operation.Scheduling_And_Dispatch.trip-schedule',
            compact(
                'trips',
                'activeRoutes',
                'reusableScheduleDates',
                'totalTripsToday',
                'assignedTrips',
                'pendingAssignments',
                'activeRoutesUsed'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('schedule_action') === 'generate_daily') {
            return $this->duplicateSchedule($request, false);
        }

        if ($request->input('schedule_action') === 'copy_previous_day') {
            return $this->duplicateSchedule($request, true);
        }

        $validated = $this->validateTrip($request);

        DB::transaction(function () use ($validated) {
            $latestTrip = TripSchedule::query()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextNumber = $latestTrip
                ? $latestTrip->id + 1
                : 1;

            $tripCode = 'T-' . str_pad(
                (string) $nextNumber,
                3,
                '0',
                STR_PAD_LEFT
            );

            $route = ShuttleRoute::query()
                ->whereKey($validated['shuttle_route_id'])
                ->where('status', 'Active')
                ->firstOrFail();

            $departure = Carbon::createFromFormat(
                'H:i',
                $validated['departure_time']
            );

            $estimatedArrival = $this->resolveArrivalTime(
                $departure,
                $validated['estimated_arrival_time'] ?? null,
                $route->estimated_time_minutes
            );

            TripSchedule::create([
                'trip_code' => $tripCode,
                'trip_date' => $validated['trip_date'],
                'shuttle_route_id' => $route->id,
                'departure_time' => $departure->format('H:i:s'),
                'estimated_arrival_time' => $estimatedArrival->format('H:i:s'),
                'shift' => $this->detectShift($departure),
                'assignment_status' => 'Unassigned',
                'status' => 'Scheduled',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        session()->flash(
            'success',
            'Trip schedule created successfully.'
        );

        return new RedirectResponse('/operation/trip-schedule');
    }

    public function update(
        Request $request,
        TripSchedule $tripSchedule
    ): RedirectResponse {
        if (
            in_array(
                $tripSchedule->status,
                ['Dispatched', 'Completed'],
                true
            )
        ) {
            session()->flash(
                'error',
                'Dispatched or completed trips can no longer be edited.'
            );

            return new RedirectResponse('/operation/trip-schedule');
        }

        $validated = $this->validateTrip(
            $request,
            $tripSchedule
        );

        $route = ShuttleRoute::query()
            ->whereKey($validated['shuttle_route_id'])
            ->where('status', 'Active')
            ->firstOrFail();

        $departure = Carbon::createFromFormat(
            'H:i',
            $validated['departure_time']
        );

        $estimatedArrival = $this->resolveArrivalTime(
            $departure,
            $validated['estimated_arrival_time'] ?? null,
            $route->estimated_time_minutes
        );

        $tripSchedule->update([
            'trip_date' => $validated['trip_date'],
            'shuttle_route_id' => $route->id,
            'departure_time' => $departure->format('H:i:s'),
            'estimated_arrival_time' => $estimatedArrival->format('H:i:s'),
            'shift' => $this->detectShift($departure),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        session()->flash(
            'success',
            'Trip schedule updated successfully.'
        );

        return new RedirectResponse('/operation/trip-schedule');
    }

    public function destroy(
        TripSchedule $tripSchedule
    ): RedirectResponse {
        if (
            ! in_array(
                $tripSchedule->status,
                ['Scheduled', 'Cancelled'],
                true
            )
        ) {
            session()->flash(
                'error',
                'Only scheduled or cancelled trips may be deleted.'
            );

            return new RedirectResponse('/operation/trip-schedule');
        }

        if ($tripSchedule->assignment_status === 'Assigned') {
            session()->flash(
                'error',
                'Remove the driver and bus assignment before deleting this trip.'
            );

            return new RedirectResponse('/operation/trip-schedule');
        }

        $tripSchedule->delete();

        session()->flash(
            'success',
            'Trip schedule deleted successfully.'
        );

        return new RedirectResponse('/operation/trip-schedule');
    }

    private function duplicateSchedule(
        Request $request,
        bool $usePreviousDay
    ): RedirectResponse {
        $rules = [
            'target_date' => ['required', 'date'],
        ];

        if (! $usePreviousDay) {
            $rules['source_date'] = ['required', 'date'];
        }

        $validated = $request->validate($rules);
        $targetDate = Carbon::parse($validated['target_date'])->toDateString();
        $sourceDate = $usePreviousDay
            ? Carbon::parse($targetDate)->subDay()->toDateString()
            : Carbon::parse($validated['source_date'])->toDateString();

        if ($sourceDate === $targetDate) {
            return redirect()
                ->to(route('trip-schedule', ['schedule_tool' => 'generate'], false))
                ->withInput()
                ->with('error', 'Source and target dates must be different.');
        }

        $sourceTrips = TripSchedule::query()
            ->with('shuttleRoute')
            ->whereDate('trip_date', $sourceDate)
            ->where('status', '!=', 'Cancelled')
            ->whereHas('shuttleRoute', fn ($query) => $query->where('status', 'Active'))
            ->orderBy('departure_time')
            ->get();

        if ($sourceTrips->isEmpty()) {
            $message = $usePreviousDay
                ? 'No reusable trips were found on the previous day.'
                : 'No reusable trips were found on the selected source date.';

            return redirect()
                ->to(route(
                    'trip-schedule',
                    ['schedule_tool' => $usePreviousDay ? 'copy' : 'generate'],
                    false
                ))
                ->withInput()
                ->with('error', $message);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $sourceTrips,
            $targetDate,
            &$created,
            &$skipped
        ): void {
            $latestTrip = TripSchedule::query()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextNumber = $latestTrip
                ? $latestTrip->id + 1
                : 1;

            foreach ($sourceTrips as $sourceTrip) {
                $duplicateExists = TripSchedule::query()
                    ->whereDate('trip_date', $targetDate)
                    ->where('shuttle_route_id', $sourceTrip->shuttle_route_id)
                    ->where('departure_time', $sourceTrip->departure_time)
                    ->exists();

                if ($duplicateExists) {
                    $skipped++;
                    continue;
                }

                $tripCode = 'T-' . str_pad(
                    (string) $nextNumber,
                    3,
                    '0',
                    STR_PAD_LEFT
                );

                TripSchedule::create([
                    'trip_code' => $tripCode,
                    'trip_date' => $targetDate,
                    'shuttle_route_id' => $sourceTrip->shuttle_route_id,
                    'departure_time' => $sourceTrip->departure_time,
                    'estimated_arrival_time' => $sourceTrip->estimated_arrival_time,
                    'shift' => $sourceTrip->shift,
                    'assignment_status' => 'Unassigned',
                    'status' => 'Scheduled',
                    'notes' => $sourceTrip->notes,
                    'created_by' => auth()->id(),
                ]);

                $created++;
                $nextNumber++;
            }
        });

        if ($created === 0) {
            return redirect()
                ->to(route('trip-schedule', ['trip_date' => $targetDate], false))
                ->with(
                    'error',
                    "No new trips were created. {$skipped} matching trip(s) already exist on the target date."
                );
        }

        $message = "{$created} trip(s) created for "
            . Carbon::parse($targetDate)->format('M d, Y')
            . '.';

        if ($skipped > 0) {
            $message .= " {$skipped} duplicate trip(s) were skipped.";
        }

        return redirect()
            ->to(route('trip-schedule', ['trip_date' => $targetDate], false))
            ->with('success', $message);
    }

    private function validateTrip(
        Request $request,
        ?TripSchedule $tripSchedule = null
    ): array {
        return $request->validate([
            'trip_date' => [
                'required',
                'date',
            ],
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
            'estimated_arrival_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'status' => [
                'required',
                Rule::in([
                    'Scheduled',
                    'Cancelled',
                ]),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
    }

    private function resolveArrivalTime(
        Carbon $departure,
        ?string $manualArrival,
        mixed $routeDuration
    ): Carbon {
        if ($manualArrival) {
            $arrival = Carbon::createFromFormat(
                'H:i',
                $manualArrival
            );

            if ($arrival->lessThanOrEqualTo($departure)) {
                $arrival->addDay();
            }

            return $arrival;
        }

        $durationMinutes = max(
            1,
            (int) ($routeDuration ?: 60)
        );

        return $departure
            ->copy()
            ->addMinutes($durationMinutes);
    }

    private function detectShift(Carbon $departure): string
    {
        $minutes = (
            ((int) $departure->format('H')) * 60
        ) + (int) $departure->format('i');

        if ($minutes >= 240 && $minutes < 720) {
            return 'Morning';
        }

        if ($minutes >= 720 && $minutes < 1080) {
            return 'Afternoon';
        }

        return 'Night';
    }
}
