<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
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
            $search = trim($request->search);

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

        $purchaseRequests->getCollection()->transform(function ($purchaseRequest) {
            $parts = $this->parseParts($purchaseRequest->item);
            $firstPart = $parts[0] ?? null;

            $purchaseRequest->parts_breakdown = $parts;
            $purchaseRequest->first_item_display = $firstPart['name'] ?? $purchaseRequest->item ?? '—';
            $purchaseRequest->first_quantity_display = $firstPart['quantity_display'] ?? $purchaseRequest->quantity ?? '0';

            return $purchaseRequest;
        });

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

    private function parseParts(?string $partsText): array
    {
        if (! $partsText) {
            return [];
        }

        return collect(explode(',', $partsText))
            ->map(function ($part) {
                $part = trim($part);

                if ($part === '') {
                    return null;
                }

                if (str_contains(strtolower($part), ' - qty:')) {
                    [$name, $quantityWithUnit] = preg_split('/ - qty:/i', $part, 2);

                    $name = trim($name ?? '');
                    $quantityWithUnit = trim($quantityWithUnit ?? '');

                    preg_match('/^(\d+)\s*(.*)$/', $quantityWithUnit, $matches);

                    $quantity = isset($matches[1]) ? (int) $matches[1] : 1;
                    $unit = isset($matches[2]) ? trim($matches[2]) : '';

                    return [
                        'name' => $name,
                        'quantity' => max(1, $quantity),
                        'unit' => $unit,
                        'quantity_display' => trim(max(1, $quantity) . ($unit ? ' ' . $unit : '')),
                    ];
                }

                if (preg_match('/^(.*?)\s*\((\d+)\s*([^)]+)\)$/', $part, $matches)) {
                    $name = trim($matches[1] ?? '');
                    $quantity = isset($matches[2]) ? (int) $matches[2] : 1;
                    $unit = isset($matches[3]) ? trim($matches[3]) : '';

                    return [
                        'name' => $name,
                        'quantity' => max(1, $quantity),
                        'unit' => $unit,
                        'quantity_display' => trim(max(1, $quantity) . ($unit ? ' ' . $unit : '')),
                    ];
                }

                return [
                    'name' => $part,
                    'quantity' => 1,
                    'unit' => '',
                    'quantity_display' => '1',
                ];
            })
            ->filter(fn ($part) => is_array($part) && ! empty($part['name']))
            ->values()
            ->toArray();
    }
}