<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
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

        if ($request->filled('part_status') && $request->part_status !== 'All Part Statuses') {
            if ($request->part_status === 'No Parts Needed') {
                $query->where(function ($q) {
                    $q->whereNull('part_needed')
                        ->orWhere('part_needed', '')
                        ->orWhere('part_status', 'No Parts Needed');
                });
            } else {
                $query->where('part_status', $request->part_status);
            }
        }

        if ($request->filled('maintenance_type') && $request->maintenance_type !== 'All Types') {
            $query->where('maintenance_type', $request->maintenance_type);
        }

        $jobOrders = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $onHold = JobOrder::where('status', 'On Hold')->count();
        $onGoing = JobOrder::where('status', 'On Going')->count();
        $completed = JobOrder::where('status', 'Completed')->count();

        $needParts = JobOrder::query()
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
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
            ->where('status', 'Present')
            ->whereNotIn('mechanic_name', $assignedActiveMechanics)
            ->orderBy('mechanic_name')
            ->get();

        $allMechanics = MechanicAttendance::query()
            ->orderBy('mechanic_name')
            ->get();

        return view('Maintenance.job-order', compact(
            'jobOrders',
            'onHold',
            'onGoing',
            'completed',
            'needParts',
            'nextJobOrderNo',
            'availableMechanics',
            'allMechanics'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_no' => 'required|string|max:255',
            'problem_issue' => 'required|string',
            'maintenance_type' => 'required|string|max:255',
            'assigned_mechanic' => 'nullable|string|max:255',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
        ]);

        $assignedMechanic = $validated['assigned_mechanic'] ?? null;

        $parts = $this->partParser->normalizePartsInput($request->parts ?? []);
        $partNeeded = count($parts) > 0
            ? $this->partParser->formatParts($parts)
            : null;

        $status = 'On Hold';

        if ($assignedMechanic) {
            $mechanic = MechanicAttendance::where(
                'mechanic_name',
                $assignedMechanic
            )->first();

            if (! $mechanic) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Selected mechanic was not found.');
            }

            $hasActiveJobOrder = JobOrder::where(
                'assigned_mechanic',
                $assignedMechanic
            )
                ->where('status', '!=', 'Completed')
                ->exists();

            if ($mechanic->status !== 'Present' || $hasActiveJobOrder) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Selected mechanic is not available.');
            }

            $status = 'On Going';
        }

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
            'part_status' => $partNeeded
                ? 'Not Requested'
                : 'No Parts Needed',
        ]);

        if ($assignedMechanic) {
            $this->setMechanicStatus($assignedMechanic, 'On Duty');
        }

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'created',
            $jobOrder->id,
            'A job order was created.'
        );

        return redirect()
            ->back()
            ->with(
                'success',
                $jobOrder->status === 'On Hold'
                    ? 'Job order created and placed on hold because no mechanic was assigned.'
                    : 'Job order created successfully.'
            );
    }

    public function update(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            return redirect()
                ->back()
                ->with('error', 'Completed job orders can only be viewed.');
        }

        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255|unique:job_orders,job_order_no,' . $jobOrder->id,
            'bus_no' => 'required|string|max:255',
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
            $mechanic = MechanicAttendance::where(
                'mechanic_name',
                $newMechanic
            )->first();

            if (! $mechanic) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Selected mechanic was not found.');
            }

            $hasActiveJobOrder = JobOrder::where(
                'assigned_mechanic',
                $newMechanic
            )
                ->where('status', '!=', 'Completed')
                ->where('id', '!=', $jobOrder->id)
                ->exists();

            if ($mechanic->status !== 'Present' || $hasActiveJobOrder) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Selected mechanic is already on duty.');
            }
        }

        $parts = $this->partParser->normalizePartsInput($request->parts ?? []);
        $partNeeded = count($parts) > 0
            ? $this->partParser->formatParts($parts)
            : null;

        $partStatus = $jobOrder->part_status;

        if (! $partNeeded) {
            $partStatus = 'No Parts Needed';
        } elseif (! $partStatus || in_array($partStatus, ['Unknown', 'No Parts Needed'], true)) {
            $partStatus = 'Not Requested';
        }

        $status = $validated['status'] ?? $jobOrder->status;

        if (! $newMechanic) {
            $status = 'On Hold';
        } elseif ($status === 'On Hold') {
            $status = 'On Going';
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

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'updated',
            $jobOrder->id,
            'A job order was updated.'
        );

        if ($oldMechanic && $oldMechanic !== $newMechanic) {
            $this->setMechanicStatus($oldMechanic, 'Present');
        }

        if ($newMechanic && $oldMechanic !== $newMechanic) {
            $this->setMechanicStatus($newMechanic, 'On Duty');
        }

        return redirect()
            ->back()
            ->with('success', 'Job order updated successfully.');
    }

    public function createPurchaseRequest(JobOrder $jobOrder)
    {
        if (empty($jobOrder->part_needed)) {
            return redirect()
                ->back()
                ->with('error', 'Cannot create PR because this job order has no parts needed.');
        }

        if ($jobOrder->status === 'Completed') {
            return redirect()
                ->back()
                ->with('error', 'Cannot create PR because this job order is already completed.');
        }

        if (! in_array($jobOrder->part_status, [null, 'Not Requested', 'Rejected'], true)) {
            return redirect()
                ->back()
                ->with('error', 'This job order already has an active purchase request.');
        }

        $hasActivePr = PurchaseRequest::where(
            'job_order_no',
            $jobOrder->job_order_no
        )
            ->whereNotIn('status', ['Rejected', 'Issued'])
            ->exists();

        if ($hasActivePr) {
            return redirect()
                ->back()
                ->with('error', 'This job order already has an active purchase request.');
        }

        $parts = $this->partParser->parsePartText($jobOrder->part_needed);

        $parsedParts = [
            'item' => $this->partParser->formatParts($parts),
            'quantity' => $this->partParser->calculateTotalQuantity($parts),
        ];

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => $this->generatePrNo(),
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'item' => $parsedParts['item'],
            'quantity' => $parsedParts['quantity'],
            'status' => 'Submitted',
            'remarks' => 'Created from Job Order ' . $jobOrder->job_order_no,
            'date_requested' => now(),
        ]);

        $jobOrder->update([
            'part_status' => 'Submitted',
        ]);

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'created',
            $purchaseRequest->id,
            'A purchase request was created from a job order.'
        );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'status_updated',
            $jobOrder->id,
            'Job order part status was updated to Submitted.'
        );

        return redirect()
            ->back()
            ->with('success', 'Purchase request created successfully.');
    }

    public function finish(JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            return redirect()
                ->back()
                ->with('error', 'Job order is already completed.');
        }

        if ($jobOrder->status === 'On Hold') {
            return redirect()
                ->back()
                ->with('error', 'This job order cannot be finished because it is currently on hold.');
        }

        if (! $this->canFinishWithPartStatus($jobOrder)) {
            return redirect()
                ->back()
                ->with('error', 'This job order cannot be finished yet. The part status must be Issued or Rejected first.');
        }

        $jobOrder->update([
            'completion_date' => now(),
            'status' => 'Completed',
        ]);

        $this->setMechanicStatus($jobOrder->assigned_mechanic, 'Present');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'status_updated',
            $jobOrder->id,
            'A job order was marked as completed.'
        );

        return redirect()
            ->back()
            ->with('success', 'Job order marked as completed.');
    }

    public function destroy(JobOrder $jobOrder)
    {
        $jobOrderId = $jobOrder->id;
        $assignedMechanic = $jobOrder->assigned_mechanic;

        $jobOrder->delete();

        if ($assignedMechanic) {
            $this->setMechanicStatus($assignedMechanic, 'Present');
        }

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'deleted',
            $jobOrderId,
            'A job order was deleted.'
        );

        return redirect()
            ->back()
            ->with('success', 'Job order deleted successfully.');
    }

    private function setMechanicStatus(?string $mechanicName, string $status): void
    {
        if (! $mechanicName) {
            return;
        }

        MechanicAttendance::where('mechanic_name', $mechanicName)
            ->update([
                'status' => $status,
            ]);
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

        $lastJobOrder = JobOrder::where(
            'job_order_no',
            'like',
            "JO-{$year}-%"
        )
            ->orderByDesc('id')
            ->first();

        if (! $lastJobOrder) {
            return "JO-{$year}-0001";
        }

        preg_match(
            '/JO-' . $year . '-(\d+)/',
            $lastJobOrder->job_order_no,
            $matches
        );

        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;

        $newJobOrderNo = 'JO-' . $year . '-' . str_pad(
            $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );

        while (JobOrder::where('job_order_no', $newJobOrderNo)->exists()) {
            $nextNumber++;

            $newJobOrderNo = 'JO-' . $year . '-' . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        return $newJobOrderNo;
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');

        $lastPr = PurchaseRequest::where(
            'pr_no',
            'like',
            "PR-{$year}-%"
        )
            ->orderByDesc('id')
            ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match(
            '/PR-' . $year . '-(\d+)/',
            $lastPr->pr_no,
            $matches
        );

        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;

        $newPrNo = 'PR-' . $year . '-' . str_pad(
            $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;

            $newPrNo = 'PR-' . $year . '-' . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        return $newPrNo;
    }
}