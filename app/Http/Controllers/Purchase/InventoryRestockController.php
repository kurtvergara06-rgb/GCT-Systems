<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Support\TableSort;
use Illuminate\Http\Request;

class InventoryRestockController extends Controller
{
    private array $activeStatuses = [
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
    ];

    private array $historyStatuses = ['Delivered', 'Picked Up', 'Issued'];

    public function index(Request $request)
    {
        $recordView = $request->query('record_view') === 'history' ? 'history' : 'active';
        $visibleStatuses = $recordView === 'history' ? $this->historyStatuses : $this->activeStatuses;

        $query = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->whereIn('status', $visibleStatuses);

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

        TableSort::apply($query, $request, ['created_at' => 'created_at', 'pr_no' => 'pr_no', 'status' => 'status'], 'created_at', 'desc');
        $restockRequests = $query
            ->paginate(8)
            ->withQueryString();

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
            ->whereIn('status', ['Delivered', 'Picked Up', 'Issued'])
            ->count();

        $statuses = $visibleStatuses;

        return view('Purchase.Requested_Purchase.inventory-restock', compact(
            'restockRequests',
            'totalRequests',
            'forPurchase',
            'ordered',
            'delivered',
            'statuses',
            'recordView'
        ));
    }
}
