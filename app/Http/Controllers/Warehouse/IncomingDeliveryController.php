<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Http\Request;

class IncomingDeliveryController extends Controller
{
    public function index(Request $request)
    {
        return view('Warehouse.incoming-deliveries', $this->data($request));
    }

    public function data(Request $request): array
    {
        $deliveryQuery = PurchaseOrder::query()
            ->whereIn('status', ['For Delivery', 'Delivered', 'For Pick-up', 'Picked Up']);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $deliveryQuery->where(function ($query) use ($search) {
                $query->where('po_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $request->status === 'Received'
                ? $deliveryQuery->whereNotNull('inventory_posted_at')
                : $deliveryQuery->where('status', $request->status);
        }

        $deliveries = $deliveryQuery->latest()->paginate(20)->withQueryString();
        $totalIncoming = PurchaseOrder::whereIn('status', ['For Delivery', 'For Pick-up'])->whereNull('inventory_posted_at')->count();
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')->whereNull('inventory_posted_at')->count();
        $delivered = PurchaseOrder::whereIn('status', ['Delivered', 'Picked Up'])->whereNotNull('inventory_posted_at')->count();
        $receivedToday = PurchaseOrder::whereDate('inventory_posted_at', today())->count();

        return compact('deliveries', 'totalIncoming', 'forDelivery', 'delivered', 'receivedToday');
    }
}
