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

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
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

        $availableMechanics = MechanicAttendance::query()
            ->whereIn('status', ['Present', 'On Duty'])
            ->orderBy('mechanic_name')
            ->get();

        return view('Maintenance.job-order', compact(
            'jobOrders',
            'onHold',
            'onGoing',
            'completed',
            'urgentRepair',
            'nextJobOrderNo',
            'availableMechanics'
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

        $validated['job_order_no'] = $this->generateJobOrderNo();
        $validated['start_date'] = now();
        $validated['completion_date'] = null;
        $validated['status'] = 'On Going';
        $validated['part_needed'] = $this->formatPartsNeeded($request->parts);
        $validated['part_status'] = $validated['part_needed'] ? 'Not Requested' : null;

        unset($validated['parts']);

        JobOrder::create($validated);

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order created successfully.');
    }

    public function update(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            return redirect()
                ->route('job-orders')
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

        $validated['part_needed'] = $this->formatPartsNeeded($request->parts);

        if (! $validated['part_needed']) {
            $validated['part_status'] = null;
        } elseif (! $jobOrder->part_status || $jobOrder->part_status === 'Unknown') {
            $validated['part_status'] = 'Not Requested';
        }

        unset($validated['parts']);

        $jobOrder->update($validated);

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order updated successfully.');
    }

    public function finish(JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            return redirect()
                ->route('job-orders')
                ->with('error', 'Job order is already completed.');
        }

        if (! empty($jobOrder->part_needed) && $jobOrder->part_status !== 'Issued') {
            return redirect()
                ->route('job-orders')
                ->with('error', 'This job order cannot be finished yet. The requested part must be issued first.');
        }

        $jobOrder->update([
            'completion_date' => now(),
            'status' => 'Completed',
        ]);

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order marked as completed.');
    }

    public function destroy(JobOrder $jobOrder)
    {
        $jobOrder->delete();

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order deleted successfully.');
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

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
        $nextNumber = $lastNumber + 1;

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