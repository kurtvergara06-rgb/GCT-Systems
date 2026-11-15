<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
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
                    ->orWhere('part_needed', 'like', "%{$search}%");
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

        return view('Maintenance.job-order', compact(
            'jobOrders',
            'onHold',
            'onGoing',
            'completed',
            'urgentRepair',
            'nextJobOrderNo'
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

        unset($validated['parts']);

        JobOrder::create($validated);

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order created successfully.');
    }

    public function update(Request $request, JobOrder $jobOrder)
    {
        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255|unique:job_orders,job_order_no,' . $jobOrder->id,
            'bus_no' => 'required|string|max:255',
            'problem_issue' => 'required|string',
            'maintenance_type' => 'required|string|max:255',
            'assigned_mechanic' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
        ]);

        $validated['part_needed'] = $this->formatPartsNeeded($request->parts);

        unset($validated['parts']);

        $jobOrder->update($validated);

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order updated successfully.');
    }

    public function finish(JobOrder $jobOrder)
    {
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
        $lastJobOrder = JobOrder::orderByDesc('id')->first();

        if (! $lastJobOrder) {
            return 'JO-000001';
        }

        preg_match('/JO-(\d+)/', $lastJobOrder->job_order_no, $matches);

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
        $nextNumber = $lastNumber + 1;

        $newJobOrderNo = 'JO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        while (JobOrder::where('job_order_no', $newJobOrderNo)->exists()) {
            $nextNumber++;
            $newJobOrderNo = 'JO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
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