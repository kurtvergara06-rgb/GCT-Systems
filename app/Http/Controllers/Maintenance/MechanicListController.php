<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Support\TableSort;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MechanicListController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $request->filled('attendance_date')
            ? Carbon::parse($request->attendance_date)->toDateString()
            : now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $activeJobs = JobOrder::query()->with('mechanicAssignments')
            ->whereIn('status', ['On Going', 'On Hold'])
            ->where('review_status', 'Approved')
            ->latest('start_date')
            ->latest('id')
            ->get()
            ->flatMap(function (JobOrder $jobOrder) {
                $names = $jobOrder->mechanicAssignments->pluck('mechanic_name');
                if ($names->isEmpty()) {
                    $names = collect(preg_split('/\s*,\s*/', (string) $jobOrder->assigned_mechanic) ?: []);
                }

                return $names->filter()->map(fn ($name) => (object) [
                    'mechanic_name' => $name,
                    'jobOrder' => $jobOrder,
                ]);
            })
            ->groupBy(fn ($assignment) => Str::lower(trim($assignment->mechanic_name)))
            ->map(fn ($assignments) => $assignments->first()->jobOrder);

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

        TableSort::apply($query, $request, ['mechanic_name' => 'mechanic_name', 'mechanic_id' => 'mechanic_id', 'shift' => 'shift', 'employment_status' => 'employment_status'], 'mechanic_name');
        $mechanics = $query
            ->paginate(10)
            ->withQueryString();

        $mechanics->getCollection()->transform(function (Mechanic $mechanic) use ($attendanceByMechanic, $activeJobs, $selectedDate) {
            $attendance = $attendanceByMechanic->get($mechanic->mechanic_id);
            $activeJob = $activeJobs->get(Str::lower(trim($mechanic->mechanic_name)));
            $baseStatus = $attendance?->status ?? 'No Attendance';

            $mechanic->setAttribute('attendance_date', $attendance?->attendance_date ?? Carbon::parse($selectedDate));
            $mechanic->setAttribute('time_in', $attendance?->time_in);
            $mechanic->setAttribute('time_out', $attendance?->time_out);
            $mechanic->setAttribute('assigned_job', $attendance?->assigned_job);
            $mechanic->setAttribute('status', $baseStatus);
            $mechanic->setAttribute('active_job', $activeJob);
            $isPresent = in_array($baseStatus, ['Present', 'Late', 'On Duty'], true);
            $working = $activeJob && $activeJob->actual_started_at !== null
                && (trim((string) $activeJob->part_needed) === '' || $activeJob->part_status === 'Issued');
            $mechanic->setAttribute('assignment_status', ! $isPresent || $mechanic->employment_status !== 'Active'
                ? 'Not Available'
                : ($working ? 'Working' : ($activeJob ? 'Assigned' : 'Available')));
            $mechanic->setAttribute(
                'effective_status',
                $activeJob && $activeJob->actual_started_at !== null
                    && ($activeJob->part_needed === null || trim((string) $activeJob->part_needed) === '' || $activeJob->part_status === 'Issued')
                    && in_array($baseStatus, ['Present', 'Late', 'On Duty'], true)
                    ? 'On Duty'
                    : $baseStatus
            );

            return $mechanic;
        });

        if ($request->filled('availability') && $request->availability !== 'All Types') {
            $filtered = $mechanics->getCollection()->filter(function (Mechanic $mechanic) use ($request) {
                return $mechanic->assignment_status === $request->availability;
            })->values();

            $mechanics->setCollection($filtered);
        }

        $allMechanics = Mechanic::query()
            ->where('employment_status', 'Active')
            ->get();
        $totalMechanics = $allMechanics->count();
        $assignmentCounts = $allMechanics->countBy(function (Mechanic $mechanic) use ($attendanceByMechanic, $activeJobs): string {
            $attendance = $attendanceByMechanic->get($mechanic->mechanic_id);
            if (! in_array($attendance?->status, ['Present', 'Late', 'On Duty'], true)) {
                return 'Not Available';
            }
            $job = $activeJobs->get(Str::lower(trim($mechanic->mechanic_name)));
            if (! $job) {
                return 'Available';
            }
            return $job->actual_started_at !== null
                && (trim((string) $job->part_needed) === '' || $job->part_status === 'Issued')
                ? 'Working' : 'Assigned';
        });
        $availableMechanics = $assignmentCounts->get('Available', 0);
        $assignedMechanics = $assignmentCounts->get('Assigned', 0);
        $onDutyMechanics = $assignmentCounts->get('Working', 0);
        $notAvailableMechanics = $assignmentCounts->get('Not Available', 0);

        return view('Maintenance.mechanic-list', compact(
            'mechanics',
            'totalMechanics',
            'availableMechanics',
            'assignedMechanics',
            'notAvailableMechanics',
            'onDutyMechanics'
        ));
    }
}
