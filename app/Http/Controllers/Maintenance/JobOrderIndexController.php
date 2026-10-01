<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Http\Request;

class JobOrderIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $recordView = $request->input('record_view') === 'history'
            ? 'history'
            : 'active';

        $query = JobOrder::query();

        if ($recordView === 'history') {
            $query->where('status', 'Completed');
        } else {
            $query->where('status', '!=', 'Completed');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('problem_issue', 'like', "%{$search}%")
                    ->orWhere('work_to_perform', 'like', "%{$search}%")
                    ->orWhere('maintenance_type', 'like', "%{$search}%")
                    ->orWhere('assigned_mechanic', 'like', "%{$search}%")
                    ->orWhere('part_needed', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('part_status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('part_status') && $request->part_status !== 'All Part Statuses') {
            if (in_array($request->part_status, ['No Parts Required', 'No Parts Needed'], true)) {
                $query->where(function ($q) {
                    $q->whereNull('part_needed')
                        ->orWhereRaw("TRIM(COALESCE(part_needed, '')) = ''")
                        ->orWhereIn('part_status', ['No Parts Required', 'No Parts Needed']);
                });
            } elseif ($request->part_status === 'Not Requested') {
                $query->where('part_status', 'Not Requested')
                    ->whereNotNull('part_needed')
                    ->whereRaw("TRIM(part_needed) != ''");
            } else {
                $query->where('part_status', $request->part_status);
            }
        }

        if ($request->filled('maintenance_type') && $request->maintenance_type !== 'All Types') {
            $query->where('maintenance_type', $request->maintenance_type);
        }

        $jobOrders = $query
            ->latest()
            ->paginate(1000)
            ->withQueryString();

        $onHold = JobOrder::where('status', 'On Hold')->count();
        $onGoing = JobOrder::where('status', 'On Going')->count();
        $completed = JobOrder::where('status', 'Completed')->count();

        $needParts = JobOrder::query()
            ->whereNotNull('part_needed')
            ->whereRaw("TRIM(part_needed) <> ''")
            ->where('status', '!=', 'Completed')
            ->whereNotIn('part_status', ['Issued'])
            ->count();

        $nextJobOrderNo = $this->generateJobOrderNo();

        $assignedActiveMechanics = JobOrder::query()
            ->where('status', '!=', 'Completed')
            ->whereNotNull('assigned_mechanic')
            ->where('assigned_mechanic', '!=', '')
            ->pluck('assigned_mechanic')
            ->filter()
            ->unique()
            ->values();

        $availableMechanics = MechanicAttendance::query()
            ->whereDate('attendance_date', today())
            ->whereIn('status', ['Present', 'Late'])
            ->whereNotIn('mechanic_name', $assignedActiveMechanics)
            ->orderBy('mechanic_name')
            ->get();

        $allMechanics = MechanicAttendance::query()
            ->whereDate('attendance_date', today())
            ->orderBy('mechanic_name')
            ->get();

        $buses = Bus::query()
            ->where('status', 'Active')
            ->orderBy('bus_no')
            ->get();

        $activeBusNumbers = JobOrder::query()
            ->where('status', '!=', 'Completed')
            ->pluck('bus_no')
            ->filter()
            ->unique()
            ->values();

        $availableBuses = $buses
            ->reject(fn ($bus) => $activeBusNumbers->contains($bus->bus_no))
            ->values();

        $pmsCreate = null;
        if ($request->boolean('create_pms') && $request->filled('pms_schedule_id')) {
            $pmsCreate = PmsSchedule::find($request->integer('pms_schedule_id'));
        }

        return view('Maintenance.job-order', compact(
            'jobOrders',
            'onHold',
            'onGoing',
            'completed',
            'needParts',
            'nextJobOrderNo',
            'availableMechanics',
            'allMechanics',
            'buses',
            'availableBuses',
            'pmsCreate',
            'recordView'
        ));
    }

    private function generateJobOrderNo(): string
    {
        $year = now()->format('Y');
        $lastJobOrder = JobOrder::where('job_order_no', 'like', "JO-{$year}-%")
            ->orderByDesc('id')
            ->first();

        preg_match('/JO-' . $year . '-(\d+)/', (string) ($lastJobOrder?->job_order_no ?? ''), $matches);
        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newJobOrderNo = 'JO-' . $year . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        while (JobOrder::where('job_order_no', $newJobOrderNo)->exists()) {
            $nextNumber++;
            $newJobOrderNo = 'JO-' . $year . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newJobOrderNo;
    }
}
