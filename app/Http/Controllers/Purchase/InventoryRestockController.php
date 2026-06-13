<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use Illuminate\Http\Request;

class InventoryRestockController extends Controller
{
    private array $statuses = [
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
    ];

    public function index(Request $request)
    {
        $query = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', $this->statuses);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $restockRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $totalRequests = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', $this->statuses)
            ->count();

        $forPurchase = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->where('status', 'For Purchase')
            ->count();

        $ordered = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->where('status', 'Ordered')
            ->count();

        $delivered = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', ['Delivered', 'Picked Up'])
            ->count();

        $statuses = $this->statuses;

        return view('Purchase.inventory-restock', compact(
            'restockRequests',
            'totalRequests',
            'forPurchase',
            'ordered',
            'delivered',
            'statuses'
        ));
    }
}