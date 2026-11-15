<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    private array $statuses = [
        'Submitted',
        'Approved',
        'Rejected',
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
        'Issued',
    ];

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

        $submitted = PurchaseRequest::where('status', 'Submitted')->count();
        $approved = PurchaseRequest::where('status', 'Approved')->count();
        $rejected = PurchaseRequest::where('status', 'Rejected')->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
        $issued = PurchaseRequest::where('status', 'Issued')->count();

        $nextPrNo = $this->generatePrNo();

        $jobOrders = JobOrder::query()
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->where('status', '!=', 'Completed')
            ->orderByDesc('created_at')
            ->get();

        $selectedJobOrder = null;

        if ($request->filled('job_order_id')) {
            $selectedJobOrder = JobOrder::find($request->job_order_id);
        }

        $statuses = $this->statuses;

        return view('Maintenance.purchase-requests', compact(
            'purchaseRequests',
            'submitted',
            'approved',
            'rejected',
            'forPurchase',
            'issued',
            'nextPrNo',
            'jobOrders',
            'selectedJobOrder',
            'statuses'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'parts' => 'required|array|min:1',
            'parts.*.name' => 'required|string|max:255',
            'parts.*.quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $hasActiveRequest = PurchaseRequest::where('job_order_no', $validated['job_order_no'])
            ->whereNotIn('status', ['Rejected', 'Issued'])
            ->exists();

        if ($hasActiveRequest) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This job order already has an active purchase request.');
        }

        $formattedParts = $this->formatPartsNeeded($validated['parts']);
        $totalQuantity = $this->calculateTotalQuantity($validated['parts']);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => $this->generatePrNo(),
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'item' => $formattedParts,
            'quantity' => $totalQuantity,
            'status' => 'Submitted',
            'remarks' => $validated['remarks'] ?? null,
            'date_requested' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Submitted');

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request created successfully.');
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->back()
                ->with('error', 'Only submitted purchase requests can be edited.');
        }

        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'parts' => 'required|array|min:1',
            'parts.*.name' => 'required|string|max:255',
            'parts.*.quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $formattedParts = $this->formatPartsNeeded($validated['parts']);
        $totalQuantity = $this->calculateTotalQuantity($validated['parts']);

        $oldJobOrderNo = $purchaseRequest->job_order_no;

        $purchaseRequest->update([
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'item' => $formattedParts,
            'quantity' => $totalQuantity,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        if ($oldJobOrderNo !== $purchaseRequest->job_order_no) {
            $oldJobOrder = JobOrder::where('job_order_no', $oldJobOrderNo)->first();

            if ($oldJobOrder) {
                $hasOtherRequest = PurchaseRequest::where('job_order_no', $oldJobOrderNo)->exists();

                if (! $hasOtherRequest) {
                    $oldJobOrder->update([
                        'part_status' => 'Not Requested',
                    ]);
                }
            }
        }

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
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Approved');

        return redirect()
            ->back()
            ->with('success', 'Purchase request approved successfully.');
    }

    public function reject(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->back()
                ->with('error', 'Only submitted purchase requests can be rejected.');
        }

        $purchaseRequest->update([
            'status' => 'Rejected',
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
                ->with('error', 'Only approved purchase requests can be sent to purchase.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');

        return redirect()
            ->back()
            ->with('success', 'Purchase request sent to purchase successfully.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
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
        $purchaseRequest->update([
            'status' => 'Issued',
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

        if (! $jobOrder) {
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

        $nextNumber = isset($matches[1]) ? (int) $matches[1] + 1 : 1;

        $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }

    private function formatPartsNeeded(array $parts): string
    {
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

        return implode(', ', $formattedParts);
    }

    private function calculateTotalQuantity(array $parts): int
    {
        $total = 0;

        foreach ($parts as $part) {
            $quantity = (int) ($part['quantity'] ?? 1);
            $total += $quantity > 0 ? $quantity : 1;
        }

        return $total > 0 ? $total : 1;
    }
}

