<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Operation\ShuttleRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RouteController extends Controller
{
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
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('route_code', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('origin', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status')
            && $request->status !== 'all'
        ) {
            $query->where(
                'status',
                ucfirst(strtolower((string) $request->status))
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Route Records
        |--------------------------------------------------------------------------
        */

        $routes = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $totalRoutes = ShuttleRoute::count();

        $activeRoutes = ShuttleRoute::where(
            'status',
            'Active'
        )->count();

        $totalStops = DB::table('route_stops')->count();

        $routeCoverage = ShuttleRoute::sum('distance_km');

        /*
        |--------------------------------------------------------------------------
        | Latest Processed GPS Trip Records
        |--------------------------------------------------------------------------
        |
        | Only records with usable coordinates are sent to the Routes page.
        | The map JavaScript accepts the format:
        | latitude,longitude -> latitude,longitude
        |
        */

        $gpsTripRecords = GpsTripRecord::query()
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
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
                    'record_no' => $record->record_no,
                    'bus_no' => $record->bus_no,
                    'grouping' => $record->grouping,
                    'trip_type' => $record->trip_type,
                    'beginning_at' => optional($record->beginning_at)
                        ->format('Y-m-d H:i:s'),
                    'initial_location' => $record->initial_location,
                    'ending_at' => optional($record->ending_at)
                        ->format('Y-m-d H:i:s'),
                    'final_location' => $record->final_location,
                    'duration_minutes' => $record->duration_minutes,
                    'total_minutes' => $record->total_minutes,
                    'in_motion_minutes' => $record->in_motion_minutes,
                    'idling_minutes' => $record->idling_minutes,
                    'mileage_km' => $record->mileage_km !== null
                        ? (float) $record->mileage_km
                        : null,
                    'engine_hours' => $record->engine_hours !== null
                        ? (float) $record->engine_hours
                        : null,
                    'location' => $record->location,
                    'coordinates' => $record->coordinates,
                    'description' => $record->description,
                ];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | Unique GPS Route Suggestions
        |--------------------------------------------------------------------------
        |
        | The GPS records are already sorted newest-first. Using unique() here
        | keeps the latest processed GPS record for every route combination.
        |
        */

        $gpsRouteSuggestions = $gpsTripRecords
            ->filter(function (array $record): bool {
                return !empty($record['grouping'])
                    && !empty($record['initial_location'])
                    && !empty($record['final_location']);
            })
            ->unique(function (array $record): string {
                return strtolower(
                    trim((string) $record['grouping'])
                    . '|'
                    . trim((string) $record['initial_location'])
                    . '|'
                    . trim((string) $record['final_location'])
                );
            })
            ->map(function (array $record): array {
                return [
                    'grouping' => $record['grouping'],
                    'origin' => $record['initial_location'],
                    'destination' => $record['final_location'],
                    'distance_km' => $record['mileage_km'],
                    'estimated_time_minutes' =>
                        $record['total_minutes']
                        ?? $record['duration_minutes'],
                    'latest_gps_trip_id' => $record['id'],
                    'coordinates' => $record['coordinates'],
                    'bus_no' => $record['bus_no'],
                    'beginning_at' => $record['beginning_at'],
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Next Route ID
        |--------------------------------------------------------------------------
        */

        $latestRoute = ShuttleRoute::orderByDesc('id')->first();

        $nextNumber = $latestRoute
            ? $latestRoute->id + 1
            : 1;

        $nextRouteCode = 'R-' . str_pad(
            (string) $nextNumber,
            2,
            '0',
            STR_PAD_LEFT
        );

        return view(
            'Operation.Routes.routes-stops',
            compact(
                'routes',
                'totalRoutes',
                'activeRoutes',
                'totalStops',
                'routeCoverage',
                'nextRouteCode',
                'gpsTripRecords',
                'gpsRouteSuggestions'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'route_name' => 'required|string|max:255',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:1',
            'status' => 'required|in:Active,Inactive',
            'stops' => 'nullable|array',
            'stops.*' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $latestRoute = ShuttleRoute::lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextNumber = $latestRoute
                ? $latestRoute->id + 1
                : 1;

            $routeCode = 'R-' . str_pad(
                (string) $nextNumber,
                2,
                '0',
                STR_PAD_LEFT
            );

            $route = ShuttleRoute::create([
                'route_code' => $routeCode,
                'route_name' => $validated['route_name'],
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'distance_km' => $validated['distance_km'] ?? null,
                'estimated_time_minutes' =>
                    $validated['estimated_time_minutes'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->saveStops(
                $route,
                $validated['stops'] ?? []
            );
        });

        return redirect()
            ->route('operation.routes')
            ->with('success', 'Route created successfully.');
    }

    public function update(
        Request $request,
        ShuttleRoute $shuttleRoute
    ): RedirectResponse {
        $validated = $request->validate([
            'route_name' => 'required|string|max:255',
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:1',
            'status' => 'required|in:Active,Inactive',
            'stops' => 'nullable|array',
            'stops.*' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use (
            $validated,
            $shuttleRoute
        ) {
            $shuttleRoute->update([
                'route_name' => $validated['route_name'],
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'distance_km' => $validated['distance_km'] ?? null,
                'estimated_time_minutes' =>
                    $validated['estimated_time_minutes'] ?? null,
                'status' => $validated['status'],
            ]);

            $shuttleRoute->stops()->delete();

            $this->saveStops(
                $shuttleRoute,
                $validated['stops'] ?? []
            );
        });

        return redirect()
            ->route('operation.routes')
            ->with('success', 'Route updated successfully.');
    }

    public function destroy(
        ShuttleRoute $shuttleRoute
    ): RedirectResponse {
        $shuttleRoute->delete();

        return redirect()
            ->route('operation.routes')
            ->with('success', 'Route deleted successfully.');
    }

    private function saveStops(
        ShuttleRoute $route,
        array $stops
    ): void {
        $order = 1;

        foreach ($stops as $stop) {
            $stop = trim((string) $stop);

            if ($stop === '') {
                continue;
            }

            $route->stops()->create([
                'stop_name' => $stop,
                'stop_order' => $order,
            ]);

            $order++;
        }
    }
}