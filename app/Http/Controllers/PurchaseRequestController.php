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
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $draft = PurchaseRequest::where('status', 'Draft')->count();
        $submitted = PurchaseRequest::where('status', 'Submitted')->count();
        $rejected = PurchaseRequest::where('status', 'Rejected')->count();
        $approved = PurchaseRequest::where('status', 'Approved')->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();

        /*
        |--------------------------------------------------------------------------
        | JO Dropdown
        |--------------------------------------------------------------------------
        | Only On Going JO will appear.
        | JO with Approved / processed PR will not appear anymore.
        |--------------------------------------------------------------------------
        */
        $usedJobOrderNos = PurchaseRequest::whereIn('status', [
                'Approved',
                'For Purchase',
                'Pending Purchase',
                'Delivering',
                'Delivered',
                'Issued',
            ])
            ->pluck('job_order_no')
            ->toArray();

        $jobOrders = JobOrder::where('status', 'On Going')
            ->whereNotIn('job_order_no', $usedJobOrderNos)
            ->orderBy('job_order_no')
            ->get();

        $nextPrNo = $this->generatePrNo();

        return view('Maintenance.purchase-request', compact(
            'purchaseRequests',
            'draft',
            'submitted',
            'rejected',
            'approved',
            'forPurchase',
            'jobOrders',
            'nextPrNo'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:255',
            'submit_action' => 'required|string|in:draft,submit',
        ]);

        $jobOrder = JobOrder::where('job_order_no', $validated['job_order_no'])->first();

        if (! $jobOrder) {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Selected job order was not found.');
        }

        if ($jobOrder->status !== 'On Going') {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Only ongoing job orders can be used to create a purchase request.');
        }

        $alreadyApproved = PurchaseRequest::where('job_order_no', $validated['job_order_no'])
            ->whereIn('status', [
                'Approved',
                'For Purchase',
                'Pending Purchase',
                'Delivering',
                'Delivered',
                'Issued',
            ])
            ->exists();

        if ($alreadyApproved) {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'This job order already has an approved purchase request.');
        }

        $validated['pr_no'] = $this->generatePrNo();

        $validated['status'] = $request->submit_action === 'submit'
            ? 'Submitted'
            : 'Draft';

        unset($validated['submit_action']);

        $purchaseRequest = PurchaseRequest::create($validated);

        // Update related job order part_status when PR is submitted
        if ($request->submit_action === 'submit') {
            $jobOrder = JobOrder::where('job_order_no', $validated['job_order_no'])->first();
            if ($jobOrder) {
                $jobOrder->update(['part_status' => 'Submitted']);
            }
        }

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request created successfully.');
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        /*
        |--------------------------------------------------------------------------
        | Approved PR is View Only
        |--------------------------------------------------------------------------
        | Once approved by sub admin, it cannot be edited anymore.
        |--------------------------------------------------------------------------
        */
        if (! in_array($purchaseRequest->status, ['Draft', 'Submitted'])) {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Approved purchase requests can only be viewed.');
        }

        $validated = $request->validate([
            'pr_no' => 'required|string|max:255|unique:purchase_requests,pr_no,' . $purchaseRequest->id,
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:255',
            'submit_action' => 'nullable|string|in:draft,submit',
        ]);

        $jobOrder = JobOrder::where('job_order_no', $validated['job_order_no'])->first();

        if (! $jobOrder) {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Selected job order was not found.');
        }

        if ($jobOrder->status !== 'On Going') {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Only ongoing job orders can be used for a purchase request.');
        }

        $alreadyApproved = PurchaseRequest::where('job_order_no', $validated['job_order_no'])
            ->where('id', '!=', $purchaseRequest->id)
            ->whereIn('status', [
                'Approved',
                'For Purchase',
                'Pending Purchase',
                'Delivering',
                'Delivered',
                'Issued',
            ])
            ->exists();

        if ($alreadyApproved) {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'This job order already has an approved purchase request.');
        }

        if ($request->submit_action === 'submit') {
            $validated['status'] = 'Submitted';
        } elseif ($request->submit_action === 'draft') {
            $validated['status'] = 'Draft';
        }

        unset($validated['submit_action']);

        $purchaseRequest->update($validated);

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request updated successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->delete();

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request deleted successfully.');
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Only submitted purchase requests can be approved.');
        }

        $alreadyApproved = PurchaseRequest::where('job_order_no', $purchaseRequest->job_order_no)
            ->where('id', '!=', $purchaseRequest->id)
            ->whereIn('status', [
                'Approved',
                'For Purchase',
                'Pending Purchase',
                'Delivering',
                'Delivered',
                'Issued',
            ])
            ->exists();

        if ($alreadyApproved) {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'This job order already has an approved purchase request.');
        }

        $purchaseRequest->update([
            'status' => 'Approved',
        ]);

        // Update related job order part_status when PR is approved
        $jobOrder = JobOrder::where('job_order_no', $purchaseRequest->job_order_no)->first();
        if ($jobOrder) {
            $jobOrder->update(['part_status' => 'Approved']);
        }

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request approved successfully.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->route('purchase-requests')
                ->with('error', 'Only submitted purchase requests can be rejected.');
        }

        $purchaseRequest->update([
            'status' => 'Rejected',
            'remarks' => $request->remarks ?? $purchaseRequest->remarks,
        ]);

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request rejected successfully.');
    }

    public function markForPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved purchase requests can be marked for purchase.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked for purchase.');
    }

    public function markPendingPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'For Purchase') {
            return redirect()
                ->back()
                ->with('error', 'Only for-purchase requests can be marked as pending purchase.');
        }

        $purchaseRequest->update([
            'status' => 'Pending Purchase',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as pending purchase.');
    }

    public function markDelivering(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Pending Purchase') {
            return redirect()
                ->back()
                ->with('error', 'Only pending purchase requests can be marked as delivering.');
        }

        $purchaseRequest->update([
            'status' => 'Delivering',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as delivering.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Delivering') {
            return redirect()
                ->back()
                ->with('error', 'Only delivering requests can be marked as delivered.');
        }

        $purchaseRequest->update([
            'status' => 'Delivered',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as delivered.');
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['Approved', 'Delivered'])) {
            return redirect()
                ->back()
                ->with('error', 'Only approved or delivered requests can be issued.');
        }

        $purchaseRequest->update([
            'status' => 'Issued',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Parts issued successfully.');
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');

        $lastPurchaseRequest = PurchaseRequest::where('pr_no', 'like', "PR-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $lastPurchaseRequest) {
            return "PR-{$year}-0001";
        }

        preg_match('/PR-' . $year . '-(\d+)/', $lastPurchaseRequest->pr_no, $matches);

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
        $nextNumber = $lastNumber + 1;

        $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }

    
}