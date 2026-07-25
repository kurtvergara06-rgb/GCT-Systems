<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
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

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where(
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
            && $request->status !== 'all'
        ) {

            $query->where(
                'status',
                ucfirst(
                    strtolower($request->status)
                )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Records
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

        $totalRoutes =
            ShuttleRoute::count();

        $activeRoutes =
            ShuttleRoute::where(
                'status',
                'Active'
            )->count();

        $totalStops =
            DB::table('route_stops')->count();

        $routeCoverage =
            ShuttleRoute::sum('distance_km');


        /*
        |--------------------------------------------------------------------------
        | Next Route ID
        |--------------------------------------------------------------------------
        */

        $latestRoute =
            ShuttleRoute::orderByDesc('id')
                ->first();

        $nextNumber =
            $latestRoute
                ? $latestRoute->id + 1
                : 1;

        $nextRouteCode =
            'R-' .
            str_pad(
                $nextNumber,
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
                'nextRouteCode'
            )
        );
    }


    public function store(
        Request $request
    ): RedirectResponse {

        $validated =
            $request->validate([
                'route_name' =>
                    'required|string|max:255',

                'origin' =>
                    'required|string|max:255',

                'destination' =>
                    'required|string|max:255',

                'distance_km' =>
                    'nullable|numeric|min:0',

                'estimated_time_minutes' =>
                    'nullable|integer|min:1',

                'status' =>
                    'required|in:Active,Inactive',

                'stops' =>
                    'nullable|array',

                'stops.*' =>
                    'nullable|string|max:255',
            ]);


        DB::transaction(
            function () use ($validated) {

                $latestRoute =
                    ShuttleRoute::lockForUpdate()
                        ->orderByDesc('id')
                        ->first();

                $nextNumber =
                    $latestRoute
                        ? $latestRoute->id + 1
                        : 1;

                $routeCode =
                    'R-' .
                    str_pad(
                        $nextNumber,
                        2,
                        '0',
                        STR_PAD_LEFT
                    );


                $route =
                    ShuttleRoute::create([
                        'route_code' =>
                            $routeCode,

                        'route_name' =>
                            $validated['route_name'],

                        'origin' =>
                            $validated['origin'],

                        'destination' =>
                            $validated['destination'],

                        'distance_km' =>
                            $validated['distance_km']
                            ?? null,

                        'estimated_time_minutes' =>
                            $validated[
                                'estimated_time_minutes'
                            ] ?? null,

                        'status' =>
                            $validated['status'],
                    ]);


                $this->saveStops(
                    $route,
                    $validated['stops'] ?? []
                );
            }
        );


        return redirect()
            ->route('operation.routes')
            ->with(
                'success',
                'Route created successfully.'
            );
    }


    public function update(
        Request $request,
        ShuttleRoute $shuttleRoute
    ): RedirectResponse {

        $validated =
            $request->validate([
                'route_name' =>
                    'required|string|max:255',

                'origin' =>
                    'required|string|max:255',

                'destination' =>
                    'required|string|max:255',

                'distance_km' =>
                    'nullable|numeric|min:0',

                'estimated_time_minutes' =>
                    'nullable|integer|min:1',

                'status' =>
                    'required|in:Active,Inactive',

                'stops' =>
                    'nullable|array',

                'stops.*' =>
                    'nullable|string|max:255',
            ]);


        DB::transaction(
            function () use (
                $validated,
                $shuttleRoute
            ) {

                $shuttleRoute->update([
                    'route_name' =>
                        $validated['route_name'],

                    'origin' =>
                        $validated['origin'],

                    'destination' =>
                        $validated['destination'],

                    'distance_km' =>
                        $validated['distance_km']
                        ?? null,

                    'estimated_time_minutes' =>
                        $validated[
                            'estimated_time_minutes'
                        ] ?? null,

                    'status' =>
                        $validated['status'],
                ]);


                $shuttleRoute
                    ->stops()
                    ->delete();


                $this->saveStops(
                    $shuttleRoute,
                    $validated['stops'] ?? []
                );
            }
        );


        return redirect()
            ->route('operation.routes')
            ->with(
                'success',
                'Route updated successfully.'
            );
    }


    public function destroy(
        ShuttleRoute $shuttleRoute
    ): RedirectResponse {

        $shuttleRoute->delete();


        return redirect()
            ->route('operation.routes')
            ->with(
                'success',
                'Route deleted successfully.'
            );
    }


    private function saveStops(
        ShuttleRoute $route,
        array $stops
    ): void {

        $order = 1;

        foreach ($stops as $stop) {

            $stop = trim(
                (string) $stop
            );

            if ($stop === '') {
                continue;
            }

            $route
                ->stops()
                ->create([
                    'stop_name' =>
                        $stop,

                    'stop_order' =>
                        $order,
                ]);

            $order++;
        }
    }
}