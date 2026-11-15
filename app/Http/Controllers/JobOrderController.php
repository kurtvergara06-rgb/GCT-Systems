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
                    ->orWhere('service', 'like', "%{$search}%")
                    ->orWhere('assigned_mechanic', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        if ($request->filled('type') && $request->type !== 'All Types') {
            $query->where('type', $request->type);
        }

        $jobOrders = $query->latest()->paginate(6);

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
        $request->validate([
            'job_order_no' => 'required|unique:job_orders,job_order_no',
            'bus_no' => 'required',
            'service' => 'required',
            'type' => 'required|in:PMS,Repair',
            'assigned_mechanic' => 'nullable',
            'status' => 'required|in:On Hold,On Going,Completed,Urgent Repair',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'date_reported' => 'required|date',
        ]);

        JobOrder::create($request->all());

        return redirect()->route('job-orders')->with('success', 'Job order created successfully.');
    }

    public function destroy(JobOrder $jobOrder)
    {
        $jobOrder->delete();

        return redirect()->route('job-orders')->with('success', 'Job order deleted successfully.');
    }

    public function update(Request $request, JobOrder $jobOrder)
{
    $request->validate([
        'job_order_no' => 'required|unique:job_orders,job_order_no,' . $jobOrder->id,
        'bus_no' => 'required',
        'service' => 'required',
        'type' => 'required|in:PMS,Repair',
        'assigned_mechanic' => 'nullable',
        'status' => 'required|in:On Hold,On Going,Completed,Urgent Repair',
        'start_time' => 'nullable',
        'end_time' => 'nullable',
        'date_reported' => 'required|date',
    ]);

    $jobOrder->update($request->all());

    return redirect()->route('job-orders')->with('success', 'Job order updated successfully.');
}
}