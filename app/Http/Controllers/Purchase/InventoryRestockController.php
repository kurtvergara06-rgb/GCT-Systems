<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InventoryRestockController extends Controller
{
    private array $activeStatuses = [
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
    ];

    public function approvalSignature(MaintenanceRequest $maintenanceRequest)
    {
        abort_unless($maintenanceRequest->source_type === 'Auto Restock'
            && $maintenanceRequest->job_order_no === 'RESTOCK', 404);

        $path = (string) ($maintenanceRequest->restock_signature_path ?? '');
        if (! str_starts_with($path, 'warehouse-restock-approvals/')
            || ! preg_match('/^warehouse-restock-approvals\\/[a-zA-Z0-9_-]+\\.(?:png|jpe?g|webp)$/', $path)) {
            abort(404);
        }
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

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
            ->with('requester')
            ->paginate(8)
            ->withQueryString();

        // Receipt progress comes from the linked PO, never from a manually edited PR status.
        $linkedOrders = PurchaseOrder::query()
            ->whereIn('purchase_request_id', $restockRequests->getCollection()->pluck('id'))
            ->orderByDesc('id')
            ->get(['id', 'purchase_request_id', 'status', 'items', 'received_quantities', 'inventory_posted_at'])
            ->unique('purchase_request_id')->keyBy('purchase_request_id');
        $restockRequests->getCollection()->each(function ($pr) use ($linkedOrders): void {
            $po = $linkedOrders->get($pr->id);
            $pr->purchasing_display_status = $po?->receipt_display_status ?? $pr->status;
        });

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
