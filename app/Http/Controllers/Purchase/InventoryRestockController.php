<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use Illuminate\Http\Request;

class InventoryRestockController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Active Statuses
    |--------------------------------------------------------------------------
    | These stay in the main Inventory Restock Records table.
    */
    private array $activeStatuses = [
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
    ];

    /*
    |--------------------------------------------------------------------------
    | History Statuses
    |--------------------------------------------------------------------------
    | Delivered / Picked Up / Issued records should move to history.
    */
    private array $historyStatuses = [
        'Delivered',
        'Picked Up',
        'Issued',
    ];

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Active Inventory Restock Records
        |--------------------------------------------------------------------------
        */
        $query = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', $this->activeStatuses);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All States') {
            $query->where('status', $request->status);
        }

        $restockRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Restock History
        |--------------------------------------------------------------------------
        */
        $historyRequests = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', $this->historyStatuses)
            ->latest()
            ->paginate(5, ['*'], 'history_page')
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Summary Cards
        |--------------------------------------------------------------------------
        */
        $totalRequests = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', $this->activeStatuses)
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

        $statuses = array_merge($this->activeStatuses, $this->historyStatuses);

        return view('Purchase.inventory-restock', compact(
            'restockRequests',
            'historyRequests',
            'totalRequests',
            'forPurchase',
            'ordered',
            'delivered',
            'statuses'
        ));
    }
}