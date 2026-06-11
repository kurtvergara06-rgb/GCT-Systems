<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\MechanicAttendance;
use Illuminate\Http\Request;

class JobOrderController extends Controller
{
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
        $urgentRepair = JobOrder::where('status', 'Urgent Repair')->count();
        $nextJobOrderNo = $this->generateJobOrderNo();

        $assignedActiveMechanics = JobOrder::query()
            ->where('status', '!=', 'Completed')
            ->whereNotNull('assigned_mechanic')
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
            'urgentRepair',
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
            'assigned_mechanic' => 'required|string|max:255',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
        ]);

        $mechanic = MechanicAttendance::where('mechanic_name', $validated['assigned_mechanic'])->first();
        $hasActiveJobOrder = JobOrder::where('assigned_mechanic', $validated['assigned_mechanic'])
            ->where('status', '!=', 'Completed')
            ->exists();

        if (! $mechanic) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Selected mechanic was not found.');
        }

        if ($mechanic->status !== 'Present' || $hasActiveJobOrder) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Selected mechanic is not available. Only present mechanics without active job orders can be assigned.');
        }

        $partNeeded = $this->formatPartsNeeded($request->parts);

        JobOrder::create([
            'job_order_no' => $this->generateJobOrderNo(),
            'bus_no' => $validated['bus_no'],
            'problem_issue' => $validated['problem_issue'],
            'maintenance_type' => $validated['maintenance_type'],
            'assigned_mechanic' => $validated['assigned_mechanic'],
            'part_needed' => $partNeeded,
            'start_date' => now(),
            'completion_date' => null,
            'status' => 'On Going',
            'part_status' => $partNeeded ? 'Not Requested' : 'No Parts Needed',
        ]);

        $this->setMechanicStatus($validated['assigned_mechanic'], 'On Duty');

        return redirect()
            ->back()
            ->with('success', 'Job order created successfully.');
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
            'assigned_mechanic' => 'required|string|max:255',
            'status' => 'required|string|in:On Hold,On Going',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
        ]);

        $oldMechanic = $jobOrder->assigned_mechanic;
        $newMechanic = $validated['assigned_mechanic'];

        if ($oldMechanic !== $newMechanic) {
            $mechanic = MechanicAttendance::where('mechanic_name', $newMechanic)->first();

            if (! $mechanic) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Selected mechanic was not found.');
            }

            $hasActiveJobOrder = JobOrder::where('assigned_mechanic', $newMechanic)
                ->where('status', '!=', 'Completed')
                ->whereKeyNot($jobOrder->id)
                ->exists();

            if ($mechanic->status !== 'Present' || $hasActiveJobOrder) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Selected mechanic is already on duty. Please choose a present mechanic without an active job order.');
            }
        }

        $partNeeded = $this->formatPartsNeeded($request->parts);
        $partStatus = $jobOrder->part_status;

        if (! $partNeeded) {
            $partStatus = 'No Parts Needed';
        } elseif (! $partStatus || in_array($partStatus, ['Unknown', 'No Parts Needed'], true)) {
            $partStatus = 'Not Requested';
        }

        $jobOrder->update([
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'problem_issue' => $validated['problem_issue'],
            'maintenance_type' => $validated['maintenance_type'],
            'assigned_mechanic' => $validated['assigned_mechanic'],
            'status' => $validated['status'],
            'part_needed' => $partNeeded,
            'part_status' => $partStatus,
        ]);

        if ($oldMechanic !== $newMechanic) {
            $this->setMechanicStatus($oldMechanic, 'Present');
            $this->setMechanicStatus($newMechanic, 'On Duty');
        }

        return redirect()
            ->back()
            ->with('success', 'Job order updated successfully.');
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

        return redirect()
            ->back()
            ->with('success', 'Job order marked as completed.');
    }

    public function destroy(JobOrder $jobOrder)
    {
        $assignedMechanic = $jobOrder->assigned_mechanic;

        $jobOrder->delete();
        $this->setMechanicStatus($assignedMechanic, 'Present');

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

    private function formatPartsNeeded(?array $parts): ?string
    {
        if (! $parts) {
            return null;
        }

        $formattedParts = [];

        foreach ($parts as $part) {
            $name = trim($part['name'] ?? '');
            $quantity = $part['quantity'] ?? null;

            if ($name !== '') {
                $formattedParts[] = $quantity
                    ? "{$name} - Qty: {$quantity}"
                    : $name;
            }
        }

        return count($formattedParts) > 0
            ? implode(', ', $formattedParts)
            : null;
    }
}

