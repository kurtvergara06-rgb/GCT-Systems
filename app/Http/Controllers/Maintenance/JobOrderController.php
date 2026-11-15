<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\MechanicAttendance;
use App\Services\PartParser;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;

class JobOrderController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private PartParser $partParser;

    public function __construct(PartParser $partParser)
    {
        $this->partParser = $partParser;
    }

    /* =========================================================
       INDEX
    ========================================================= */

    public function index(Request $request)
    {
        $query = JobOrder::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('problem_issue', 'like', "%{$search}%")
                    ->orWhere('maintenance_type', 'like', "%{$search}%")
                    ->orWhere('assigned_mechanic', 'like', "%{$search}%")
                    ->orWhere('part_needed', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('part_status', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('part_status')
            && $request->part_status !== 'All Part Statuses'
        ) {
            if ($request->part_status === 'No Parts Needed') {
                $query->where(function ($q) {
                    $q->whereNull('part_needed')
                        ->orWhere('part_needed', '')
                        ->orWhere(
                            'part_status',
                            'No Parts Needed'
                        );
                });
            } else {
                $query->where(
                    'part_status',
                    $request->part_status
                );
            }
        }

        if (
            $request->filled('maintenance_type')
            && $request->maintenance_type !== 'All Types'
        ) {
            $query->where(
                'maintenance_type',
                $request->maintenance_type
            );
        }

        /*
         * Load the complete filtered Job Order dataset into one paginator page.
         * The table itself handles vertical scrolling after eight visible rows,
         * so Previous/Next controls are no longer needed for normal JO volumes.
         */
        $jobOrders = $query
            ->latest()
            ->paginate(1000)
            ->withQueryString();

        $onHold = JobOrder::where(
            'status',
            'On Hold'
        )->count();

        $onGoing = JobOrder::where(
            'status',
            'On Going'
        )->count();

        $completed = JobOrder::where(
            'status',
            'Completed'
        )->count();

        $needParts = JobOrder::query()
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->where('status', '!=', 'Completed')
            ->whereNotIn('part_status', ['Issued'])
            ->count();

        $nextJobOrderNo =
            $this->generateJobOrderNo();

        /* =====================================================
           AVAILABLE MECHANICS
        ====================================================== */

        $assignedActiveMechanics =
            JobOrder::query()
                ->where(
                    'status',
                    '!=',
                    'Completed'
                )
                ->whereNotNull(
                    'assigned_mechanic'
                )
                ->where(
                    'assigned_mechanic',
                    '!=',
                    ''
                )
                ->pluck(
                    'assigned_mechanic'
                )
                ->filter()
                ->unique()
                ->values();

        $availableMechanics =
            MechanicAttendance::query()
                ->whereDate('attendance_date', today())
                ->whereIn(
                    'status',
                    ['Present', 'Late']
                )
                ->whereNotIn(
                    'mechanic_name',
                    $assignedActiveMechanics
                )
                ->orderBy(
                    'mechanic_name'
                )
                ->get();

        $allMechanics =
            MechanicAttendance::query()
                ->whereDate('attendance_date', today())
                ->orderBy(
                    'mechanic_name'
                )
                ->get();

        /* =====================================================
           BUSES
        ====================================================== */

        $buses = Bus::query()
            ->where(
                'status',
                'Active'
            )
            ->orderBy(
                'bus_no'
            )
            ->get();

        $activeBusNumbers =
            JobOrder::query()
                ->where(
                    'status',
                    '!=',
                    'Completed'
                )
                ->pluck(
                    'bus_no'
                )
                ->filter()
                ->unique()
                ->values();

        $availableBuses =
            $buses
                ->reject(
                    fn ($bus) =>
                        $activeBusNumbers
                            ->contains(
                                $bus->bus_no
                            )
                )
                ->values();

        /* =====================================================
           PMS CREATE
        ====================================================== */

        $pmsCreate = null;

        if (
            $request->boolean('create_pms')
            && $request->filled(
                'pms_schedule_id'
            )
        ) {
            $pmsCreate =
                PmsSchedule::find(
                    $request->integer(
                        'pms_schedule_id'
                    )
                );
        }

        return view(
            'Maintenance.job-order',
            compact(
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
                'pmsCreate'
            )
        );
    }

    public function availableMechanics()
    {
        $assignedActiveMechanics =
            JobOrder::query()
                ->where('status', '!=', 'Completed')
                ->whereNotNull('assigned_mechanic')
                ->where('assigned_mechanic', '!=', '')
                ->pluck('assigned_mechanic')
                ->filter()
                ->unique()
                ->values();

        $mechanics = MechanicAttendance::query()
            ->whereDate('attendance_date', today())
            ->whereIn('status', ['Present', 'Late'])
            ->whereNotIn('mechanic_name', $assignedActiveMechanics)
            ->orderBy('mechanic_name')
            ->get(['id', 'mechanic_name']);

        return response()->json($mechanics);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_no' => 'required|string|exists:buses,bus_no',
            'problem_issue' => 'required|string',
            'maintenance_type' => 'required|string|max:255',
            'assigned_mechanic' => 'nullable|string|max:255',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'pms_schedule_id' => 'nullable|integer|exists:pms_schedules,id',
        ]);

        $assignedMechanic = $validated['assigned_mechanic'] ?? null;
        $pmsSchedule = null;

        if (! empty($validated['pms_schedule_id'])) {
            $pmsSchedule = PmsSchedule::findOrFail($validated['pms_schedule_id']);

            if ($validated['bus_no'] !== $pmsSchedule->bus_no) {
                return redirect()->back()->withInput()->with('error', 'The selected bus does not match the PMS schedule.');
            }

            if ($validated['maintenance_type'] !== 'PMS') {
                return redirect()->back()->withInput()->with('error', 'A PMS schedule can only create a PMS Job Order.');
            }

            $hasActivePmsJobOrder = JobOrder::query()
                ->where('pms_schedule_id', $pmsSchedule->id)
                ->where('status', '!=', 'Completed')
                ->exists();

            if ($hasActivePmsJobOrder) {
                return redirect()->route('PMS-Scheduling')->with('error', 'This PMS schedule already has an active Job Order.');
            }
        }

        $status = 'On Hold';

        if ($assignedMechanic) {
            $mechanic = MechanicAttendance::query()
                ->where('mechanic_name', $assignedMechanic)
                ->whereDate('attendance_date', today())
                ->latest('id')
                ->first();

            if (! $mechanic) {
                return redirect()->back()->withInput()->with('error', 'Selected mechanic has no attendance record for today.');
            }

            $hasActiveJobOrder = JobOrder::where('assigned_mechanic', $assignedMechanic)
                ->where('status', '!=', 'Completed')
                ->exists();

            if (! in_array($mechanic->status, ['Present', 'Late'], true) || $hasActiveJobOrder) {
                return redirect()->back()->withInput()->with('error', 'Selected mechanic is not available.');
            }

            $status = 'On Going';
        }

        $parts = $assignedMechanic
            ? $this->partParser->normalizePartsInput($request->parts ?? [])
            : [];

        $partNeeded = count($parts) > 0
            ? $this->partParser->formatParts($parts)
            : null;

        $partStatus = $partNeeded ? 'Not Requested' : 'No Parts Needed';

        $jobOrder = JobOrder::create([
            'job_order_no' => $this->generateJobOrderNo(),
            'bus_no' => $validated['bus_no'],
            'problem_issue' => $validated['problem_issue'],
            'maintenance_type' => $validated['maintenance_type'],
            'assigned_mechanic' => $assignedMechanic,
            'part_needed' => $partNeeded,
            'start_date' => now(),
            'completion_date' => null,
            'status' => $status,
            'part_status' => $partStatus,
        ]);

        if ($pmsSchedule) {
            $jobOrder->pms_schedule_id = $pmsSchedule->id;
            $jobOrder->save();
        }

        if ($assignedMechanic) {
            $this->setMechanicStatus($assignedMechanic, 'On Duty');
        }

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'created', $jobOrder->id, 'A job order was created.');

        return redirect()->to(route('job-orders', [], false))->with(
            'success',
            $jobOrder->status === 'On Hold'
                ? 'Job order created and placed on hold because no mechanic was assigned.'
                : 'Job order created successfully.'
        );
    }

    public function update(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            return redirect()->back()->with('error', 'Completed job orders can only be viewed.');
        }

        if (in_array($jobOrder->part_status, [
            'Approved', 'For Purchase', 'Ordered', 'For Pick-up',
            'For Delivery', 'Delivered', 'Picked Up', 'Issued',
        ], true)) {
            return redirect()->back()->with('error', 'This Job Order can no longer be edited because its Purchase Request is already approved or being processed.');
        }

        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255|unique:job_orders,job_order_no,' . $jobOrder->id,
            'bus_no' => 'required|string|exists:buses,bus_no',
            'problem_issue' => 'required|string',
            'maintenance_type' => 'required|string|max:255',
            'assigned_mechanic' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:On Hold,On Going',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
        ]);

        $oldMechanic = $jobOrder->assigned_mechanic;
        $newMechanic = $validated['assigned_mechanic'] ?? null;

        if ($newMechanic && $oldMechanic !== $newMechanic) {
            $mechanic = MechanicAttendance::query()
                ->where('mechanic_name', $newMechanic)
                ->whereDate('attendance_date', today())
                ->latest('id')
                ->first();

            if (! $mechanic) {
                return redirect()->back()->withInput()->with('error', 'Selected mechanic has no attendance record for today.');
            }

            $hasActiveJobOrder = JobOrder::where('assigned_mechanic', $newMechanic)
                ->where('status', '!=', 'Completed')
                ->where('id', '!=', $jobOrder->id)
                ->exists();

            if (! in_array($mechanic->status, ['Present', 'Late'], true) || $hasActiveJobOrder) {
                return redirect()->back()->withInput()->with('error', 'Selected mechanic is already on duty.');
            }
        }

        $status = $newMechanic ? 'On Going' : 'On Hold';
        $parts = $newMechanic
            ? $this->partParser->normalizePartsInput($request->parts ?? [])
            : [];

        $partNeeded = count($parts) > 0
            ? $this->partParser->formatParts($parts)
            : null;

        $partStatus = $jobOrder->part_status;

        if (! $newMechanic || ! $partNeeded) {
            $partStatus = 'No Parts Needed';
        } elseif (! $partStatus || in_array($partStatus, ['Unknown', 'No Parts Needed'], true)) {
            $partStatus = 'Not Requested';
        }

        if ($jobOrder->part_status === 'Rejected') {
            $partStatus = 'Rejected';
        }

        $jobOrder->update([
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'problem_issue' => $validated['problem_issue'],
            'maintenance_type' => $validated['maintenance_type'],
            'assigned_mechanic' => $newMechanic,
            'status' => $status,
            'part_needed' => $partNeeded,
            'part_status' => $partStatus,
        ]);

        if ($oldMechanic && $oldMechanic !== $newMechanic) {
            $this->setMechanicStatus($oldMechanic, 'Present');
        }

        if ($newMechanic && $oldMechanic !== $newMechanic) {
            $this->setMechanicStatus($newMechanic, 'On Duty');
        }

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'updated', $jobOrder->id, 'A job order was updated.');

        return redirect()->to(route('job-orders', [], false))->with('success', 'Job order updated successfully.');
    }

    public function createPurchaseRequest(JobOrder $jobOrder)
    {
        if (empty($jobOrder->assigned_mechanic)) {
            return redirect()->back()->with('error', 'Assign a mechanic before creating a Purchase Request.');
        }

        if (empty($jobOrder->part_needed)) {
            return redirect()->back()->with('error', 'Cannot create PR because this Job Order has no requested parts.');
        }

        if ($jobOrder->status === 'Completed') {
            return redirect()->back()->with('error', 'Cannot create PR because this Job Order is already completed.');
        }

        if ($jobOrder->part_status === 'Rejected') {
            return redirect()->route('purchase-requests', ['search' => $jobOrder->job_order_no])
                ->with('error', 'The existing Purchase Request was rejected. Revise and resubmit the same PR instead of creating a new one.');
        }

        if (! in_array($jobOrder->part_status, [null, 'Not Requested'], true)) {
            return redirect()->back()->with('error', 'This Job Order already has a Purchase Request.');
        }

        $existingPr = $this->maintenancePurchaseRequestForJobOrder($jobOrder->job_order_no)
            ->latest()
            ->first();

        if ($existingPr) {
            return redirect()->route('purchase-requests', ['search' => $jobOrder->job_order_no])
                ->with('error', $existingPr->status === 'Rejected'
                    ? 'The existing Purchase Request is rejected. Revise and resubmit it.'
                    : 'This Job Order already has a Purchase Request.');
        }

        $parts = $this->partParser->parsePartText($jobOrder->part_needed);

        if (count($parts) === 0) {
            return redirect()->back()->with('error', 'No valid requested parts were found for this Job Order.');
        }

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => $this->generatePrNo(),
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'item' => $this->partParser->formatParts($parts),
            'quantity' => $this->partParser->calculateTotalQuantity($parts),
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Created from Job Order ' . $jobOrder->job_order_no,
            'date_requested' => now(),
        ]);

        $jobOrder->update(['part_status' => 'Submitted']);

        $this->broadcastSystemDataUpdated('Maintenance', 'PurchaseRequest', 'created', $purchaseRequest->id, 'A purchase request was created from a job order.');
        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'status_updated', $jobOrder->id, 'Job order part status was updated to Submitted.');

        return redirect()->to(route('job-orders', [], false))->with('success', 'Purchase request created successfully.');
    }

    public function finish(JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            return redirect()->back()->with('error', 'Job order is already completed.');
        }

        if ($jobOrder->status === 'On Hold') {
            return redirect()->back()->with('error', 'This job order cannot be finished because it is currently on hold.');
        }

        if (! $this->canFinishWithPartStatus($jobOrder)) {
            return redirect()->back()->with('error', 'This job order cannot be finished yet. The part status must be Issued or Rejected first.');
        }

        if ($jobOrder->maintenance_type === 'PMS') {
            if (! $jobOrder->pms_schedule_id) {
                return redirect()->back()->with('error', 'This PMS Job Order is not linked to a PMS schedule.');
            }

            $pmsSchedule = PmsSchedule::find($jobOrder->pms_schedule_id);

            if (! $pmsSchedule) {
                return redirect()->back()->with('error', 'The linked PMS schedule was not found.');
            }

            $bus = Bus::whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($jobOrder->bus_no))])->first();

            if (! $bus) {
                return redirect()->back()->with('error', 'The matching bus was not found in Bus Master List.');
            }

            $latestGps = GpsTripRecord::query()
                ->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($jobOrder->bus_no))])
                ->whereNotNull('mileage_km')
                ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'))
                ->orderByDesc('beginning_at')
                ->orderByDesc('id')
                ->first();

            $completedPmsKm = $latestGps
                ? (float) $latestGps->mileage_km
                : ($bus->latest_gps_km !== null
                    ? (float) $bus->latest_gps_km
                    : (float) $pmsSchedule->last_pms_km);

            $intervalKm = (float) $pmsSchedule->pms_interval_km;

            if ($intervalKm <= 0) {
                $intervalKm = 5000;
            }

            $nextPmsKm = $completedPmsKm + $intervalKm;

            $pmsSchedule->update([
                'last_pms_km' => $completedPmsKm,
                'pms_interval_km' => $intervalKm,
                'next_pms_km' => $nextPmsKm,
            ]);

            $busUpdateData = [
                'last_pms_km' => $completedPmsKm,
                'pms_interval_km' => $intervalKm,
                'next_pms_km' => $nextPmsKm,
            ];

            if ($latestGps) {
                $busUpdateData['latest_gps_km'] = $completedPmsKm;
                $busUpdateData['latest_gps_at'] = $latestGps->beginning_at ?? $latestGps->created_at;
            }

            $bus->update($busUpdateData);

            $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $bus->id, 'A completed PMS Job Order updated the bus PMS mileage.');
        }

        $jobOrder->update([
            'completion_date' => now(),
            'status' => 'Completed',
        ]);

        $this->setMechanicStatus($jobOrder->assigned_mechanic, 'Present');

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'status_updated', $jobOrder->id, 'A job order was marked as completed.');

        return redirect()->to(route('job-orders', [], false))->with('success', 'Job order marked as completed.');
    }

    public function destroy(JobOrder $jobOrder)
    {
        $hasLinkedPurchaseRequest = $this
            ->maintenancePurchaseRequestForJobOrder($jobOrder->job_order_no)
            ->exists();

        if ($hasLinkedPurchaseRequest) {
            return redirect()->back()->with('error', 'This Job Order cannot be deleted because it already has a linked Purchase Request.');
        }

        $jobOrderId = $jobOrder->id;
        $assignedMechanic = $jobOrder->assigned_mechanic;
        $jobOrder->delete();

        if ($assignedMechanic) {
            $this->setMechanicStatus($assignedMechanic, 'Present');
        }

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'deleted', $jobOrderId, 'A job order was deleted.');

        return redirect()->to(route('job-orders', [], false))->with('success', 'Job order deleted successfully.');
    }

    private function maintenancePurchaseRequestForJobOrder(string $jobOrderNo)
    {
        return PurchaseRequest::query()
            ->where('job_order_no', $jobOrderNo)
            ->where('pr_no', 'not like', '%-P')
            ->where(function ($query) {
                $query->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            });
    }

    private function setMechanicStatus(?string $mechanicName, string $status): void
    {
        if (! $mechanicName) {
            return;
        }

        $attendance = MechanicAttendance::query()
            ->where('mechanic_name', $mechanicName)
            ->whereDate('attendance_date', today())
            ->latest('id')
            ->first();

        if (! $attendance) {
            return;
        }

        $attendance->update(['status' => $status]);
    }

    private function canFinishWithPartStatus(JobOrder $jobOrder): bool
    {
        if (empty($jobOrder->part_needed)) {
            return true;
        }

        return in_array($jobOrder->part_status, ['Issued', 'Rejected'], true);
    }

    private function generateJobOrderNo(): string
    {
        $year = now()->format('Y');
        $lastJobOrder = JobOrder::where('job_order_no', 'like', "JO-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $lastJobOrder) {
            return "JO-{$year}-0001";
        }

        preg_match('/JO-' . $year . '-(\d+)/', $lastJobOrder->job_order_no, $matches);
        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newJobOrderNo = 'JO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (JobOrder::where('job_order_no', $newJobOrderNo)->exists()) {
            $nextNumber++;
            $newJobOrderNo = 'JO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newJobOrderNo;
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');
        $lastPr = PurchaseRequest::where('pr_no', 'like', "PR-{$year}-%")
            ->where('pr_no', 'not like', '%-P')
            ->orderByDesc('id')
            ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match('/PR-' . $year . '-(\d+)/', $lastPr->pr_no, $matches);
        $nextNumber = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }
}
