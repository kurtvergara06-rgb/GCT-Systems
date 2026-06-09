<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\JobOrder;
use Illuminate\Http\Request;

class RequestedPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseRequest::query()
            ->whereIn('status', [
                'Approved',
                'For Purchase',
                'Pending Purchase',
                'Delivering',
                'Delivered',
                'Issued',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */
        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        /*
        |--------------------------------------------------------------------------
        | Table Data
        |--------------------------------------------------------------------------
        */
        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Summary Counts
        |--------------------------------------------------------------------------
        */
        $totalRequests = PurchaseRequest::whereIn('status', [
            'Approved',
            'For Purchase',
            'Pending Purchase',
            'Delivering',
            'Delivered',
            'Issued',
        ])->count();

        $approved = PurchaseRequest::where('status', 'Approved')->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
        $delivered = PurchaseRequest::where('status', 'Delivered')->count();
        $issued = PurchaseRequest::where('status', 'Issued')->count();

        return view('Purchase.requested-purchase', compact(
            'purchaseRequests',
            'totalRequests',
            'approved',
            'forPurchase',
            'delivered',
            'issued'
        ));
    }

    public function markForPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->route('requested-purchase')
                ->with('error', 'Only approved purchase requests can be marked for purchase.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');

        return redirect()
            ->route('requested-purchase')
            ->with('success', 'Purchase request marked as for purchase.');
    }

    public function markPendingPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'For Purchase') {
            return redirect()
                ->route('requested-purchase')
                ->with('error', 'Only for-purchase requests can be marked as pending purchase.');
        }

        $purchaseRequest->update([
            'status' => 'Pending Purchase',
        ]);

        return redirect()
            ->route('requested-purchase')
            ->with('success', 'Purchase request marked as pending purchase.');
    }

    public function markDelivering(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Pending Purchase') {
            return redirect()
                ->route('requested-purchase')
                ->with('error', 'Only pending purchase requests can be marked as delivering.');
        }

        $purchaseRequest->update([
            'status' => 'Delivering',
        ]);

        return redirect()
            ->route('requested-purchase')
            ->with('success', 'Purchase request marked as delivering.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Delivering') {
            return redirect()
                ->route('requested-purchase')
                ->with('error', 'Only delivering purchase requests can be marked as delivered.');
        }

        $purchaseRequest->update([
            'status' => 'Delivered',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Delivered');

        return redirect()
            ->route('requested-purchase')
            ->with('success', 'Purchase request marked as delivered.');
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