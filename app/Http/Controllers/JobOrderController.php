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
                    ->orWhere('assigned_mechanic', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        if ($request->filled('maintenance_type') && $request->maintenance_type !== 'All Types') {
            $query->where('maintenance_type', $request->maintenance_type);
        }

        $jobOrders = $query->latest()->paginate(6)->withQueryString();

        $onHold = JobOrder::where('status', 'On Hold')->count();
        $onGoing = JobOrder::where('status', 'On Going')->count();
        $completed = JobOrder::where('status', 'Completed')->count();
        $urgentRepair = JobOrder::where('status', 'Urgent Repair')->count();

        return view('Maintenance.job-order', compact(
            'jobOrders',
            'onHold',
            'onGoing',
            'completed',
            'urgentRepair'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255|unique:job_orders,job_order_no',
            'bus_no' => 'required|string|max:255',
            'problem_issue' => 'required|string',
            'maintenance_type' => 'required|string|in:PMS,Repair,Urgent Repair',
            'assigned_mechanic' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'completion_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|string|in:On Hold,On Going,Completed,Urgent Repair',
        ]);

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
            'maintenance_type' => 'required|string|in:PMS,Repair,Urgent Repair',
            'assigned_mechanic' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'completion_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|string|in:On Hold,On Going,Completed,Urgent Repair',
        ]);

        $jobOrder->update($validated);

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order updated successfully.');
    }

    public function destroy(JobOrder $jobOrder)
    {
        $jobOrder->delete();

        return redirect()
            ->route('job-orders')
            ->with('success', 'Job order deleted successfully.');
    }
}