<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RequestedPurchaseController extends Controller
{
    private array $purchaseStatuses = [
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
    ];

    public function index(Request $request)
    {
        $query = PurchaseRequest::query()
            ->whereIn('status', $this->purchaseStatuses);

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

        $totalRequests = PurchaseRequest::whereIn('status', $this->purchaseStatuses)->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
        $ordered = PurchaseRequest::where('status', 'Ordered')->count();
        $forPickup = PurchaseRequest::where('status', 'For Pick-up')->count();
        $forDelivery = PurchaseRequest::where('status', 'For Delivery')->count();
        $delivered = PurchaseRequest::where('status', 'Delivered')->count();
        $pickedUp = PurchaseRequest::where('status', 'Picked Up')->count();

        $statuses = $this->purchaseStatuses;

        return view('Purchase.requested-purchase', compact(
            'purchaseRequests',
            'totalRequests',
            'forPurchase',
            'ordered',
            'forPickup',
            'forDelivery',
            'delivered',
            'pickedUp',
            'statuses'
        ));
    }

    public function createPo(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        if ($purchaseRequest->status !== 'For Purchase') {
            return redirect()
                ->back()
                ->with('error', 'Only For Purchase requests can create a purchase order.');
        }

        if (PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->exists()) {
            return redirect()
                ->back()
                ->with('error', 'A purchase order already exists for this purchase request.');
        }

        return redirect()
            ->route('purchase-orders', [
                'create_from_pr' => $purchaseRequest->id,
            ])
            ->with('open_po_modal', true);
    }
}

