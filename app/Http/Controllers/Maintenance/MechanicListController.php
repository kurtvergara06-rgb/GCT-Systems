<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MechanicListController extends Controller
{
    public function index(Request $request)
    {
        $query = MechanicAttendance::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $matchingMechanics = JobOrder::query()
                ->whereNotNull('assigned_mechanic')
                ->where(function ($jobQuery) use ($search) {
                    $jobQuery->where('assigned_mechanic', 'like', "%{$search}%")
                        ->orWhere('job_order_no', 'like', "%{$search}%")
                        ->orWhere('bus_no', 'like', "%{$search}%")
                        ->orWhere('maintenance_type', 'like', "%{$search}%")
                        ->orWhere('problem_issue', 'like', "%{$search}%");
                })
                ->pluck('assigned_mechanic')
                ->filter()
                ->unique()
                ->values();

            $query->where(function ($q) use ($search, $matchingMechanics) {
                $q->where('mechanic_name', 'like', "%{$search}%")
                    ->orWhere('mechanic_id', 'like', "%{$search}%")
                    ->orWhere('assigned_job', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");

                if ($matchingMechanics->isNotEmpty()) {
                    $q->orWhereIn('mechanic_name', $matchingMechanics);
                }
            });
        }

        if ($request->filled('date_filter')) {
            if ($request->date_filter === 'Today') {
                $query->whereDate('attendance_date', today());
            }

            if ($request->date_filter === 'This Week') {
                $query->whereBetween('attendance_date', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);
            }
        }

        if (
            $request->filled('availability') &&
            $request->availability !== 'All Types'
        ) {
            if ($request->availability === 'Available') {
                $query->whereIn('status', ['Present', 'Late']);
            }

            if ($request->availability === 'Not Available') {
                $query->whereIn('status', [
                    'On Duty',
                    'Absent',
                    'On Leave',
                ]);
            }
        }

        $mechanics = $query
            ->latest('attendance_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $activeJobs = JobOrder::query()
            ->whereNotNull('assigned_mechanic')
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->latest('start_date')
            ->latest('id')
            ->get()
            ->groupBy(fn (JobOrder $jobOrder) => Str::lower(trim($jobOrder->assigned_mechanic)))
            ->map(fn ($jobs) => $jobs->first());

        $mechanics->getCollection()->transform(function (MechanicAttendance $mechanic) use ($activeJobs) {
            $activeJob = $activeJobs->get(Str::lower(trim($mechanic->mechanic_name)));

            $mechanic->setAttribute('active_job', $activeJob);
            $mechanic->setAttribute(
                'effective_status',
                $activeJob && in_array($mechanic->status, ['Present', 'Late', 'On Duty'], true)
                    ? 'On Duty'
                    : $mechanic->status
            );

            return $mechanic;
        });

        $totalMechanics = MechanicAttendance::count();

        $activeAssignedMechanics = $activeJobs->keys();

        $availableMechanics = MechanicAttendance::query()
            ->whereIn('status', ['Present', 'Late'])
            ->get()
            ->reject(fn (MechanicAttendance $mechanic) => $activeAssignedMechanics->contains(
                Str::lower(trim($mechanic->mechanic_name))
            ))
            ->count();

        $onDutyMechanics = $activeAssignedMechanics->count();

        $notAvailableMechanics = MechanicAttendance::query()
            ->whereIn('status', ['Absent', 'On Leave'])
            ->count() + $onDutyMechanics;

        return view('Maintenance.mechanic-list', compact(
            'mechanics',
            'totalMechanics',
            'availableMechanics',
            'notAvailableMechanics',
            'onDutyMechanics'
        ));
    }
}
