<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MechanicListController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->filled('attendance_date')
            ? now()->parse($request->attendance_date)->toDateString()
            : today()->toDateString();

        $activeJobs = JobOrder::query()
            ->whereNotNull('assigned_mechanic')
            ->where('assigned_mechanic', '!=', '')
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->latest('start_date')
            ->latest('id')
            ->get()
            ->groupBy(fn (JobOrder $jobOrder) => Str::lower(trim($jobOrder->assigned_mechanic)))
            ->map(fn ($jobs) => $jobs->first());

        $attendanceByMechanic = MechanicAttendance::query()
            ->whereDate('attendance_date', $selectedDate)
            ->latest('id')
            ->get()
            ->unique('mechanic_id')
            ->keyBy('mechanic_id');

        $query = Mechanic::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $matchingMechanics = $activeJobs
                ->filter(function (JobOrder $jobOrder) use ($search) {
                    return Str::contains(Str::lower($jobOrder->assigned_mechanic), Str::lower($search))
                        || Str::contains(Str::lower($jobOrder->job_order_no), Str::lower($search))
                        || Str::contains(Str::lower($jobOrder->bus_no), Str::lower($search))
                        || Str::contains(Str::lower($jobOrder->maintenance_type), Str::lower($search))
                        || Str::contains(Str::lower($jobOrder->problem_issue), Str::lower($search));
                })
                ->keys();

            $query->where(function ($q) use ($search, $matchingMechanics) {
                $q->where('mechanic_name', 'like', "%{$search}%")
                    ->orWhere('mechanic_id', 'like', "%{$search}%")
                    ->orWhere('shift', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%");

                if ($matchingMechanics->isNotEmpty()) {
                    $q->orWhereIn(
                        'mechanic_name',
                        $matchingMechanics->map(fn ($name) => trim($name))->all()
                    );
                }
            });
        }

        $mechanics = $query
            ->orderBy('mechanic_name')
            ->paginate(10)
            ->withQueryString();

        $mechanics->getCollection()->transform(function (Mechanic $mechanic) use ($attendanceByMechanic, $activeJobs, $selectedDate) {
            $attendance = $attendanceByMechanic->get($mechanic->mechanic_id);
            $activeJob = $activeJobs->get(Str::lower(trim($mechanic->mechanic_name)));
            $baseStatus = $attendance?->status ?? 'No Attendance';

            $mechanic->setAttribute('attendance_date', $attendance?->attendance_date ?? $selectedDate);
            $mechanic->setAttribute('time_in', $attendance?->time_in);
            $mechanic->setAttribute('time_out', $attendance?->time_out);
            $mechanic->setAttribute('assigned_job', $attendance?->assigned_job);
            $mechanic->setAttribute('status', $baseStatus);
            $mechanic->setAttribute('active_job', $activeJob);
            $mechanic->setAttribute(
                'effective_status',
                $activeJob && in_array($baseStatus, ['Present', 'Late', 'On Duty'], true)
                    ? 'On Duty'
                    : $baseStatus
            );

            return $mechanic;
        });

        if ($request->filled('availability') && $request->availability !== 'All Types') {
            $filtered = $mechanics->getCollection()->filter(function (Mechanic $mechanic) use ($request) {
                return $request->availability === 'Available'
                    ? in_array($mechanic->effective_status, ['Present', 'Late'], true)
                    : in_array($mechanic->effective_status, ['On Duty', 'Absent', 'On Leave', 'No Attendance'], true);
            })->values();

            $mechanics->setCollection($filtered);
        }

        $allMechanics = Mechanic::query()->where('employment_status', '!=', 'Inactive')->get();
        $activeAssignedNames = $activeJobs->keys();

        $totalMechanics = $allMechanics->count();
        $availableMechanics = $allMechanics->filter(function (Mechanic $mechanic) use ($attendanceByMechanic, $activeAssignedNames) {
            $attendance = $attendanceByMechanic->get($mechanic->mechanic_id);
            $isAssigned = $activeAssignedNames->contains(Str::lower(trim($mechanic->mechanic_name)));

            return $attendance
                && in_array($attendance->status, ['Present', 'Late'], true)
                && ! $isAssigned;
        })->count();

        $onDutyMechanics = $allMechanics->filter(
            fn (Mechanic $mechanic) => $activeAssignedNames->contains(Str::lower(trim($mechanic->mechanic_name)))
        )->count();

        $notAvailableMechanics = max(0, $totalMechanics - $availableMechanics - $onDutyMechanics);

        return view('Maintenance.mechanic-list', compact(
            'mechanics',
            'totalMechanics',
            'availableMechanics',
            'notAvailableMechanics',
            'onDutyMechanics'
        ));
    }
}
