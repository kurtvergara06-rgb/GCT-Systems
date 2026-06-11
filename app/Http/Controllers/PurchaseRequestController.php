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
                    ->orWhere('quantity', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $jobOrders = JobOrder::query()
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->where('status', '!=', 'Completed')
            ->where(function ($q) {
                $q->whereNull('part_status')
                    ->orWhere('part_status', 'Not Requested')
                    ->orWhere('part_status', 'No Parts Needed')
                    ->orWhere('part_status', 'Rejected');
            })
            ->orderBy('job_order_no')
            ->get();

        $submitted = PurchaseRequest::where('status', 'Submitted')->count();
        $approved = PurchaseRequest::where('status', 'Approved')->count();
        $rejected = PurchaseRequest::where('status', 'Rejected')->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
        $delivered = PurchaseRequest::where('status', 'Delivered')->count();
        $issued = PurchaseRequest::where('status', 'Issued')->count();
        $nextPrNo = $this->generatePrNo();

        return view('Maintenance.purchase-requests', compact(
            'purchaseRequests',
            'jobOrders',
            'submitted',
            'approved',
            'rejected',
            'forPurchase',
            'delivered',
            'issued',
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
            'remarks' => 'nullable|string|max:1000',
        ]);

        $jobOrder = JobOrder::where('job_order_no', $validated['job_order_no'])->first();

        if (! $jobOrder || empty($jobOrder->part_needed)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Cannot create a purchase request because this job order has no parts needed.');
        }

        $existingActiveRequest = PurchaseRequest::where('job_order_no', $validated['job_order_no'])
            ->whereNotIn('status', ['Rejected', 'Issued'])
            ->exists();

        if ($existingActiveRequest) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Cannot create a purchase request because this job order part request is already being processed.');
        }

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => $this->generatePrNo(),
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'item' => $validated['item'],
            'quantity' => $validated['quantity'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'Submitted',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Submitted');

        return redirect()
            ->back()
            ->with('success', 'Purchase request created successfully.');
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['Submitted', 'Rejected'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only submitted or rejected purchase requests can be edited.');
        }

        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'item' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $jobOrder = JobOrder::where('job_order_no', $validated['job_order_no'])->first();

        if (! $jobOrder || empty($jobOrder->part_needed)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Cannot update this purchase request because the selected job order has no parts needed.');
        }

        $purchaseRequest->update([
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'item' => $validated['item'],
            'quantity' => $validated['quantity'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, $purchaseRequest->status);

        return redirect()
            ->back()
            ->with('success', 'Purchase request updated successfully.');
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->back()
                ->with('error', 'Only submitted purchase requests can be approved.');
        }

        $purchaseRequest->update([
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Approved');

        return redirect()
            ->back()
            ->with('success', 'Purchase request approved successfully.');
    }

    public function reject(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['Submitted', 'Approved'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only submitted or approved purchase requests can be rejected.');
        }

        $purchaseRequest->update([
            'status' => 'Rejected',
            'rejected_at' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Rejected');

        return redirect()
            ->back()
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

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as for purchase.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['For Delivery', 'Picked Up'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only For Delivery or Picked Up purchase requests can be marked as delivered.');
        }

        $purchaseRequest->update([
            'status' => 'Delivered',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Delivered');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as delivered.');
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['Delivered', 'Picked Up'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only delivered or picked-up purchase requests can be issued.');
        }

        $purchaseRequest->update([
            'status' => 'Issued',
            'issued_at' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Issued');

        return redirect()
            ->back()
            ->with('success', 'Purchase request issued successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $jobOrderNo = $purchaseRequest->job_order_no;

        $purchaseRequest->delete();

        $jobOrder = JobOrder::where('job_order_no', $jobOrderNo)->first();

        if ($jobOrder && ! empty($jobOrder->part_needed)) {
            $hasOtherRequest = PurchaseRequest::where('job_order_no', $jobOrderNo)->exists();

            if (! $hasOtherRequest) {
                $jobOrder->update([
                    'part_status' => 'Not Requested',
                ]);
            }
        }

        return redirect()
            ->back()
            ->with('success', 'Purchase request deleted successfully.');
    }

    private function updateRelatedJobOrderPartStatus(PurchaseRequest $purchaseRequest, string $partStatus): void
    {
        $jobOrder = JobOrder::where('job_order_no', $purchaseRequest->job_order_no)->first();

        if (! $jobOrder || empty($jobOrder->part_needed)) {
            return;
        }

        $jobOrder->update([
            'part_status' => $partStatus,
        ]);
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');

        $lastPr = PurchaseRequest::where('pr_no', 'like', "PR-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match('/PR-' . $year . '-(\d+)/', $lastPr->pr_no, $matches);

        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }
}
