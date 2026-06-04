<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseRequest::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query->latest()->paginate(8)->withQueryString();

            $draft = PurchaseRequest::where('status', 'Draft')->count();
            $submitted = PurchaseRequest::where('status', 'Submitted')->count();
            $rejected = PurchaseRequest::where('status', 'Rejected')->count();
            $approved = PurchaseRequest::where('status', 'Approved')->count();
            $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
            $totalRequests = PurchaseRequest::count();

        $jobOrders = JobOrder::orderBy('job_order_no')->get();

                    return view('Maintenance.purchase-request', compact(
                    'purchaseRequests',
                    'draft',
                    'submitted',
                    'rejected',
                    'approved',
                    'forPurchase',
                    'totalRequests',
                    'jobOrders'
                ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pr_no' => 'required|string|max:255|unique:purchase_requests,pr_no',
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string',
        ]);

        $validated['status'] = $request->submit_action === 'submit'
            ? 'Submitted'
            : 'Draft';

        PurchaseRequest::create($validated);

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request created successfully.');
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $validated = $request->validate([
            'pr_no' => 'required|string|max:255|unique:purchase_requests,pr_no,' . $purchaseRequest->id,
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string',
        ]);

        $validated['status'] = $purchaseRequest->status;

        if ($request->submit_action === 'submit' && $purchaseRequest->status === 'Draft') {
            $validated['status'] = 'Submitted';
        }

        $purchaseRequest->update($validated);

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request updated successfully.');
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return back()->with('error', 'Only submitted purchase requests can be approved.');
        }

        $purchaseRequest->update([
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Purchase request approved.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return back()->with('error', 'Only submitted purchase requests can be rejected.');
        }

        $purchaseRequest->update([
            'status' => 'Rejected',
            'rejected_at' => now(),
            'remarks' => $request->remarks ?? 'Rejected by sub admin',
        ]);

        return back()->with('success', 'Purchase request rejected.');
    }

    public function markForPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Approved') {
            return back()->with('error', 'Only approved purchase requests can be marked as For Purchase.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        return back()->with('success', 'Purchase request marked as For Purchase.');
    }

    public function markPendingPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'For Purchase') {
            return back()->with('error', 'Only For Purchase requests can be marked as Pending Purchase.');
        }

        $purchaseRequest->update([
            'status' => 'Pending Purchase',
        ]);

        return back()->with('success', 'Purchase request marked as Pending Purchase.');
    }

    public function markDelivering(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Pending Purchase') {
            return back()->with('error', 'Only Pending Purchase requests can be marked as Delivering.');
        }

        $purchaseRequest->update([
            'status' => 'Delivering',
        ]);

        return back()->with('success', 'Purchase request marked as Delivering.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Delivering') {
            return back()->with('error', 'Only Delivering requests can be marked as Delivered.');
        }

        $purchaseRequest->update([
            'status' => 'Delivered',
        ]);

        return back()->with('success', 'Purchase request marked as Delivered.');
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['Approved', 'Delivered'])) {
            return back()->with('error', 'Only Approved or Delivered requests can be issued.');
        }

        $purchaseRequest->update([
            'status' => 'Issued',
            'issued_at' => now(),
        ]);

        return back()->with('success', 'Parts issued successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->delete();

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request deleted successfully.');
    }
}