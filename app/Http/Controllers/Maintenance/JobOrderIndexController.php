<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Operation\Mechanic;
use App\Services\Maintenance\JobOrderDurationService;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Support\TableSort;
use Illuminate\Http\Request;

class JobOrderIndexController extends Controller
{
    public function __construct(
        private readonly JobOrderWorkflowService $workflow,
        private readonly JobOrderDurationService $durations
    ) {}

    public function __invoke(Request $request)
    {
        $recordView = $request->input('record_view') === 'history'
            ? 'history'
            : 'active';

        $query = JobOrder::query()->with(['mechanicAssignments', 'audits.user', 'creator', 'approver']);

        if ($recordView === 'history') {
            $query->where(function ($nested): void {
                $nested->where('status', 'Completed')->orWhere('review_status', 'Rejected');
            });
        } else {
            $query->open();
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
                    ->orWhere('part_status', 'like', "%{$search}%")
                    ->orWhere('review_status', 'like', "%{$search}%");
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

        TableSort::apply($query, $request, ['created_at' => 'created_at', 'job_order_no' => 'job_order_no', 'bus_no' => 'bus_no', 'start_date' => 'start_date', 'status' => 'status', 'part_status' => 'part_status'], 'created_at', 'desc');
        $jobOrders = $query
            ->paginate($recordView === 'history' ? 20 : 1000)
            ->withQueryString();

        // Batch linked IR statuses for the visible JOs. Older requests may
        // exist while their JO part_status still reads "Not Requested".
        $existingIrStatuses = ItemRequest::query()
            ->whereIn('job_order_id', $jobOrders->getCollection()->pluck('id'))
            ->orderByDesc('id')
            ->get(['job_order_id', 'status'])
            ->unique('job_order_id')
            ->pluck('status', 'job_order_id');

        // Batch PR records once for the visible page instead of querying per row.
        $visibleJoNumbers = $jobOrders->getCollection()->pluck('job_order_no')->filter()->unique()->values();
        $linkedPrsByJobOrderNo = $visibleJoNumbers->isEmpty()
            ? collect()
            : \App\Models\Maintenance\PurchaseRequest::query()
                ->whereIn('job_order_no', $visibleJoNumbers)
                ->where('pr_no', 'not like', '%-P')
                ->where(function ($query) {
                    $query->whereNull('source_type')->orWhere('source_type', 'Maintenance Request');
                })
                ->orderByDesc('id')
                ->get()
                ->groupBy('job_order_no');

        $durationSuggestions = $jobOrders->getCollection()
            ->where('review_status', 'For Review')
            ->mapWithKeys(fn (JobOrder $jobOrder) => [
                $jobOrder->id => $this->durations->suggestion(
                    (string) $jobOrder->maintenance_type,
                    $jobOrder->work_to_perform
                ),
            ]);

        // Count the effective repair state, including legacy JOs started before parts arrived.
        $approvedActive = JobOrder::query()
            ->where('review_status', 'Approved')
            ->whereIn('status', ['On Hold', 'On Going'])
            ->get(['id', 'status', 'part_needed', 'part_status', 'review_status']);
        $onHold = $approvedActive->filter(fn (JobOrder $jobOrder) => $jobOrder->display_status === 'On Hold')->count();
        $onGoing = $approvedActive->filter(fn (JobOrder $jobOrder) => $jobOrder->display_status === 'In Progress')->count();
        $completed = JobOrder::where('status', 'Completed')->where('review_status', 'Approved')->count();

        $needParts = JobOrder::query()
            ->whereNotNull('part_needed')
            ->whereRaw("TRIM(part_needed) <> ''")
            ->where('status', '!=', 'Completed')
            ->whereNotIn('part_status', ['Issued'])
            ->count();

        $nextJobOrderNo = $this->generateJobOrderNo();

        $availableMechanics = $this->workflow->availableMechanics();
        $allMechanics = Mechanic::query()->where('employment_status', 'Active')->orderBy('mechanic_name')->get();

        $buses = Bus::query()
            ->with('authorizedDrivers:id,driver_name')
            ->where('status', 'Active')
            ->orderBy('bus_no')
            ->get();

        $activeBusNumbers = JobOrder::query()
            ->open()
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
            'existingIrStatuses',
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
            'durationSuggestions',
            'linkedPrsByJobOrderNo',
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

        preg_match('/JO-'.$year.'-(\d+)/', (string) ($lastJobOrder?->job_order_no ?? ''), $matches);
        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newJobOrderNo = 'JO-'.$year.'-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        while (JobOrder::where('job_order_no', $newJobOrderNo)->exists()) {
            $nextNumber++;
            $newJobOrderNo = 'JO-'.$year.'-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newJobOrderNo;
    }
}
