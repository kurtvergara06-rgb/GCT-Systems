<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\TripAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusAvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $today = today(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $model = trim((string) $request->query('model', ''));
        $models = Bus::query()->whereNotNull('bus_model')->where('bus_model', '!=', '')->distinct()->orderBy('bus_model')->pluck('bus_model');

        // A stale Active master status must never override an unresolved JO.
        // Match on the internal bus_no used by Maintenance, not the plate number.
        $restrictedBusNumbers = JobOrder::query()
            ->where('status', '!=', 'Completed')
            ->distinct()
            ->pluck('bus_no')
            ->filter()
            ->values();

        $query = Bus::query();

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('plate_no', 'like', "%{$search}%")
                    ->orWhere('bus_model', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['Active', 'Inactive', 'Under Maintenance'], true)) {
            $query->where('status', $status);
        }

        if ($model !== '' && $models->contains($model)) {
            $query->where('bus_model', $model);
        }

        $buses = $query->orderBy('plate_no')->paginate(15)->withQueryString();

        // Upcoming assignments are informational. An assignment tomorrow does
        // not make an operational bus unavailable for the entire day today.
        $assignments = TripAssignment::query()
            ->with('tripSchedule.shuttleRoute')
            ->whereIn('bus_id', $buses->getCollection()->pluck('id'))
            ->whereHas('tripSchedule', fn ($query) => $query
                ->whereDate('trip_date', '>=', $today)
                ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed']))
            ->get()
            ->filter(fn ($assignment) => $assignment->tripSchedule !== null)
            ->sortBy(fn ($assignment) => $assignment->tripSchedule->trip_date->format('Y-m-d').' '.$assignment->tripSchedule->departure_time)
            ->groupBy('bus_id');

        $buses->getCollection()->each(function (Bus $bus) use ($assignments, $restrictedBusNumbers): void {
            $bus->has_unresolved_job_order = $restrictedBusNumbers->contains($bus->bus_no);
            $bus->next_assignment = $assignments->get($bus->id)?->first()?->tripSchedule;
            $bus->is_on_trip = $assignments->get($bus->id)?->contains(
                fn ($assignment) => in_array($assignment->tripSchedule?->status, ['Dispatched'], true)
            ) ?? false;
        });

        return view('Operation.Shuttle_Bus_Management.bus-availability', [
            'buses' => $buses,
            'models' => $models,
            'totalBuses' => Bus::count(),
            'activeBuses' => Bus::where('status', 'Active')->whereNotIn('bus_no', $restrictedBusNumbers)->count(),
            'maintenanceBuses' => Bus::where('status', 'Under Maintenance')->orWhere(fn ($query) => $query->where('status', 'Active')->whereIn('bus_no', $restrictedBusNumbers))->count(),
            'inactiveBuses' => Bus::where('status', 'Inactive')->count(),
        ]);
    }
}
