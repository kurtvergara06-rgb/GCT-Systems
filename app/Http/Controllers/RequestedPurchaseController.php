<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;

class RequestedPurchaseController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Requested Purchase Page
        |--------------------------------------------------------------------------
        | Only show PRs that already went to Purchase Department.
        | It starts when Warehouse marks the request as "For Purchase".
        */
        $purchaseStatuses = [
            'For Purchase',
            'Ordered',
            'For Pick-up',
            'For Delivery',
            'Delivered',
            'Picked Up',
        ];

        $query = PurchaseRequest::query()
            ->whereIn('status', $purchaseStatuses);

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

        if ($request->filled('status') && $request->status !== 'All States') {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $totalRequests = PurchaseRequest::whereIn('status', $purchaseStatuses)->count();

        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();

        $ordered = PurchaseRequest::where('status', 'Ordered')->count();

        $forPickup = PurchaseRequest::where('status', 'For Pick-up')->count();

        $forDelivery = PurchaseRequest::where('status', 'For Delivery')->count();

        $delivered = PurchaseRequest::where('status', 'Delivered')->count();

        $pickedUp = PurchaseRequest::where('status', 'Picked Up')->count();

        return view('Purchase.requested-purchase', compact(
            'purchaseRequests',
            'totalRequests',
            'forPurchase',
            'ordered',
            'forPickup',
            'forDelivery',
            'delivered',
            'pickedUp'
        ));
    }

    public function markOrdered(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'For Purchase') {
            return redirect()
                ->back()
                ->with('error', 'Only for-purchase requests can be marked as ordered.');
        }

        $purchaseRequest->update([
            'status' => 'Ordered',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Ordered');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as ordered.');
    }

    public function markForPickup(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['For Purchase', 'Ordered'])) {
            return redirect()
                ->back()
                ->with('error', 'Only for-purchase or ordered requests can be marked for pick-up.');
        }

        $purchaseRequest->update([
            'status' => 'For Pick-up',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Pick-up');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as for pick-up.');
    }

    public function markForDelivery(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['For Purchase', 'Ordered'])) {
            return redirect()
                ->back()
                ->with('error', 'Only for-purchase or ordered requests can be marked for delivery.');
        }

        $purchaseRequest->update([
            'status' => 'For Delivery',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Delivery');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as for delivery.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['For Purchase', 'Ordered', 'For Delivery'])) {
            return redirect()
                ->back()
                ->with('error', 'Only purchase requests in delivery process can be marked as delivered.');
        }

        $purchaseRequest->update([
            'status' => 'Delivered',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Delivered');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as delivered.');
    }

    public function markPickedUp(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['For Purchase', 'Ordered', 'For Pick-up'])) {
            return redirect()
                ->back()
                ->with('error', 'Only purchase requests in pick-up process can be marked as picked up.');
        }

        $purchaseRequest->update([
            'status' => 'Picked Up',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Picked Up');

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as picked up.');
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