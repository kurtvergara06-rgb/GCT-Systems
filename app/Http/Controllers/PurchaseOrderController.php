<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhere('first_item', 'like', "%{$search}%")
                    ->orWhere('pr_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All States') {
            $query->where('status', $request->status);
        }

        $purchaseOrders = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $totalOrders = PurchaseOrder::count();
        $forPurchase = PurchaseOrder::where('status', 'Ordered')->count();
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')->count();
        $delivered = PurchaseOrder::whereIn('status', ['Delivered', 'Picked Up'])->count();

        return view('Purchase.purchase-orders', compact(
            'purchaseOrders',
            'totalOrders',
            'forPurchase',
            'forDelivery',
            'delivered'
        ));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'supplier' => 'nullable|string|max:255',
            'employee' => 'nullable|string|max:255',
            'net_amount' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:Ordered,For Pick-up,For Delivery,Delivered,Picked Up',
        ]);

        $purchaseOrder->update([
            'supplier' => $validated['supplier'] ?? $purchaseOrder->supplier,
            'employee' => $validated['employee'] ?? $purchaseOrder->employee,
            'net_amount' => $validated['net_amount'] ?? $purchaseOrder->net_amount,
            'status' => $validated['status'],
        ]);

        $this->syncRequestAndJobOrder($purchaseOrder, $validated['status']);

        return redirect()
            ->back()
            ->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseRequest = $purchaseOrder->purchaseRequest;

        if ($purchaseRequest && $purchaseRequest->status === 'Ordered') {
            $purchaseRequest->update([
                'status' => 'For Purchase',
            ]);

            $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');
        }

        $purchaseOrder->delete();

        return redirect()
            ->back()
            ->with('success', 'Purchase order deleted successfully.');
    }

    private function syncRequestAndJobOrder(PurchaseOrder $purchaseOrder, string $status): void
    {
        $purchaseRequest = $purchaseOrder->purchaseRequest;

        if (! $purchaseRequest) {
            $purchaseRequest = PurchaseRequest::where('pr_no', $purchaseOrder->pr_no)->first();
        }

        if (! $purchaseRequest) {
            return;
        }

        $purchaseRequest->update([
            'status' => $status,
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, $status);
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
}