<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\JobOrder;
use Illuminate\Http\Request;

class WarehousePartRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseRequest::query()
            ->whereIn('status', [
                'Approved',
                'For Purchase',
                'Ordered',
                'For Pick-up',
                'For Delivery',
                'Delivered',
                'Picked Up',
                'Issued',
            ]);

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

        $partRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $approved = PurchaseRequest::where('status', 'Approved')->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
        $delivered = PurchaseRequest::where('status', 'Delivered')->count();
        $pickedUp = PurchaseRequest::where('status', 'Picked Up')->count();
        $issued = PurchaseRequest::where('status', 'Issued')->count();

        return view('Warehouse.part-requests', compact(
            'partRequests',
            'approved',
            'forPurchase',
            'delivered',
            'pickedUp',
            'issued'
        ));
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['Approved', 'Delivered', 'Picked Up'])) {
            return redirect()
                ->back()
                ->with('error', 'Only approved, delivered, or picked-up part requests can be issued.');
        }

        $purchaseRequest->update([
            'status' => 'Issued',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Issued');

        return redirect()
            ->back()
            ->with('success', 'Part request issued successfully.');
    }

    public function sendToPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved part requests can be sent to purchase.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');

        return redirect()
            ->back()
            ->with('success', 'Part request sent to purchase department.');
    }

    private function updateRelatedJobOrderPartStatus(PurchaseRequest $purchaseRequest, string $partStatus): void
    {
        $jobOrderNo = $purchaseRequest->job_order_no
            ?? $purchaseRequest->jo_no
            ?? null;

        if (! $jobOrderNo) {
            return;
        }

        $jobOrder = JobOrder::where('job_order_no', $jobOrderNo)->first();

        if (! $jobOrder) {
            return;
        }

        $jobOrder->update([
            'part_status' => $partStatus,
        ]);
    }
}
