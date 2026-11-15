<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;

use App\Models\Warehouse\InventoryItem;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Http\Request;

class WarehousePartRequestController extends Controller
{
    private array $statuses = [
        'Approved',
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
        'Issued',
    ];

    public function index(Request $request)
    {
        $query = PurchaseRequest::query()
            ->whereIn('status', $this->statuses);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $purchaseRequests->getCollection()->transform(function ($purchaseRequest) {
            $parts = $this->parseParts($purchaseRequest->item);
            $inventoryCheck = $this->checkInventoryAvailability($parts);

            $purchaseRequest->first_item_name = $parts[0]['name'] ?? $purchaseRequest->item;
            $purchaseRequest->parts_breakdown = $parts;
            $purchaseRequest->inventory_check = $inventoryCheck;

            // Show only available quantity, not "0 / 2"
            $purchaseRequest->on_hand_available = $inventoryCheck['total_on_hand'];

            $purchaseRequest->inventory_label = $inventoryCheck['available']
                ? 'Available'
                : 'Not Available';

            $purchaseRequest->can_issue =
                $purchaseRequest->status === 'Approved' &&
                $inventoryCheck['available'];

            $purchaseRequest->needs_purchase =
                $purchaseRequest->status === 'Approved' &&
                ! $inventoryCheck['available'];

            return $purchaseRequest;
        });

        $approved = PurchaseRequest::where('status', 'Approved')->count();
        $forPurchase = PurchaseRequest::where('status', 'For Purchase')->count();
        $delivered = PurchaseRequest::where('status', 'Delivered')->count();
        $issued = PurchaseRequest::where('status', 'Issued')->count();
        $statuses = $this->statuses;

        return view('Warehouse.part-requests', compact(
            'purchaseRequests',
            'approved',
            'forPurchase',
            'delivered',
            'issued',
            'statuses'
        ));
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved purchase requests can be issued.');
        }

        $parts = $this->parseParts($purchaseRequest->item);
        $inventoryCheck = $this->checkInventoryAvailability($parts);

        if (! $inventoryCheck['available']) {
            return redirect()
                ->back()
                ->with('error', 'Cannot issue parts. Some requested parts are not available in inventory.');
        }

        foreach ($parts as $part) {
            $inventoryItem = $this->findInventoryItem($part['name']);

            if ($inventoryItem) {
                $inventoryItem->update([
                    'on_hand' => max(0, (int) $inventoryItem->on_hand - (int) $part['quantity']),
                ]);
            }
        }

        $purchaseRequest->update([
            'status' => 'Issued',
        ]);

        JobOrder::where('job_order_no', $purchaseRequest->job_order_no)
            ->update([
                'part_status' => 'Issued',
            ]);

        return redirect()
            ->back()
            ->with('success', 'Parts issued successfully.');
    }

    public function sendToPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved purchase requests can be sent to purchasing department.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        JobOrder::where('job_order_no', $purchaseRequest->job_order_no)
            ->update([
                'part_status' => 'For Purchase',
            ]);

        return redirect()
            ->back()
            ->with('success', 'Purchase request sent to purchasing department.');
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

                if (str_contains($part, ' - Qty:')) {
                    [$name, $quantity] = explode(' - Qty:', $part, 2);

                    return [
                        'name' => trim($name),
                        'quantity' => max(1, (int) trim($quantity)),
                    ];
                }

                return [
                    'name' => $part,
                    'quantity' => 1,
                ];
            })
            ->filter(fn ($part) => is_array($part) && ! empty($part['name']))
            ->values()
            ->toArray();
    }

    private function checkInventoryAvailability(array $parts): array
    {
        $missing = [];
        $breakdown = [];
        $totalNeeded = 0;
        $totalOnHand = 0;

        foreach ($parts as $part) {
            $inventoryItem = $this->findInventoryItem($part['name']);

            $neededQty = (int) $part['quantity'];
            $availableQty = $inventoryItem ? (int) $inventoryItem->on_hand : 0;

            $totalNeeded += $neededQty;
            $totalOnHand += $availableQty;

            $partStatus = $availableQty >= $neededQty
                ? 'Available'
                : 'Not Available';

            $breakdown[] = [
                'name' => $part['name'],
                'needed' => $neededQty,
                'available' => $availableQty,
                'status' => $partStatus,
            ];

            if (! $inventoryItem || $availableQty < $neededQty) {
                $missing[] = [
                    'name' => $part['name'],
                    'needed' => $neededQty,
                    'available' => $availableQty,
                ];
            }
        }

        return [
            'available' => count($missing) === 0,
            'missing' => $missing,
            'breakdown' => $breakdown,
            'total_needed' => $totalNeeded,
            'total_on_hand' => $totalOnHand,
        ];
    }

    private function findInventoryItem(string $partName): ?InventoryItem
    {
        $partName = strtolower(trim($partName));

        if ($partName === '') {
            return null;
        }

        return InventoryItem::query()
            ->whereRaw('LOWER(item_name) = ?', [$partName])
            ->orWhereRaw('LOWER(parts_name) = ?', [$partName])
            ->orWhereRaw('LOWER(item_code) = ?', [$partName])
            ->first();
    }
}

