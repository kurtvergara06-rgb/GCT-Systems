<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\InventoryItem;
use App\Models\Maintenance\JobOrder;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    private array $statuses = [
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
    ];

    public function index(Request $request)
    {
        $query = PurchaseOrder::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('po_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('supplier_address_tel', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All States') {
            $query->where('status', $request->status);
        }

        $purchaseOrders = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $totalOrders = PurchaseOrder::count();
        $ordered = PurchaseOrder::where('status', 'Ordered')->count();
        $forPickup = PurchaseOrder::where('status', 'For Pick-up')->count();
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')->count();
        $delivered = PurchaseOrder::whereIn('status', ['Delivered', 'Picked Up'])->count();

        $nextPoNo = $this->generatePoNo();
        $statuses = $this->statuses;

        $usedPurchaseRequestIds = PurchaseOrder::query()
            ->whereNotNull('purchase_request_id')
            ->pluck('purchase_request_id')
            ->toArray();

        $availablePurchaseRequests = PurchaseRequest::query()
            ->where('status', 'For Purchase')
            ->when(! empty($usedPurchaseRequestIds), function ($q) use ($usedPurchaseRequestIds) {
                $q->whereNotIn('id', $usedPurchaseRequestIds);
            })
            ->orderBy('pr_no')
            ->get();

        $selectedPurchaseRequest = null;

        if ($request->filled('create_from_pr')) {
            $selectedPurchaseRequest = PurchaseRequest::query()
                ->where('id', $request->create_from_pr)
                ->where('status', 'For Purchase')
                ->first();
        }

        $openPoModal = session('open_po_modal') || $selectedPurchaseRequest !== null;

        return view('Purchase.purchase-orders', compact(
            'purchaseOrders',
            'totalOrders',
            'ordered',
            'forPickup',
            'forDelivery',
            'delivered',
            'nextPoNo',
            'statuses',
            'availablePurchaseRequests',
            'selectedPurchaseRequest',
            'openPoModal'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'supplier_name' => 'required|string|max:255',
            'supplier_address_tel' => 'nullable|string',
            'terms' => 'nullable|string|max:255',
            'terms_of_payment' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'delivery_fee' => 'nullable',
            'discount' => 'nullable',
            'vat' => 'nullable',
            'status' => 'required|string|in:Ordered,For Pick-up,For Delivery,Delivered,Picked Up',
            'items' => 'required|array|min:1',
            'items.*.pr_no' => 'nullable|string|max:255',
            'items.*.bus_no' => 'nullable|string|max:255',
            'items.*.employee' => 'nullable|string|max:255',
            'items.*.item_description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.cost' => 'required',
        ]);

        $items = $this->cleanItems($request->items);

        if (count($items) === 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please add at least one valid item.');
        }

        $totals = $this->calculateTotals(
            $items,
            $request->delivery_fee,
            $request->discount,
            $request->vat
        );

        $purchaseRequest = null;

        if (! empty($validated['purchase_request_id'])) {
            $purchaseRequest = PurchaseRequest::find($validated['purchase_request_id']);
        }

        if (! $purchaseRequest) {
            $purchaseRequest = $this->findFirstPurchaseRequest($items);
        }

        DB::transaction(function () use ($validated, $items, $totals, $purchaseRequest) {
            $purchaseOrder = PurchaseOrder::create([
                'po_no' => $this->generatePoNo(),
                'po_date' => now()->toDateString(),
                'purchase_request_id' => $purchaseRequest?->id,
                'supplier_name' => $validated['supplier_name'],
                'supplier_address_tel' => $validated['supplier_address_tel'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'terms_of_payment' => $validated['terms_of_payment'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'items' => $items,
                'gross_amount' => $totals['gross_amount'],
                'delivery_fee' => $totals['delivery_fee'],
                'discount' => $totals['discount'],
                'vat' => $totals['vat'],
                'net_amount' => $totals['net_amount'],
                'status' => $validated['status'],
            ]);

            $this->syncRelatedPurchaseRequestsAndJobOrders($purchaseOrder, $validated['status']);

            if ($this->isInventoryPostingStatus($validated['status'])) {
                $this->postPurchaseOrderToInventory($purchaseOrder);
            }
        });

        return redirect()
            ->route('purchase-orders')
            ->with('success', 'Purchase order created successfully.');
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'po_no' => 'required|string|max:255|unique:purchase_orders,po_no,' . $purchaseOrder->id,
            'po_date' => 'required|date',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'supplier_name' => 'required|string|max:255',
            'supplier_address_tel' => 'nullable|string',
            'terms' => 'nullable|string|max:255',
            'terms_of_payment' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'delivery_fee' => 'nullable',
            'discount' => 'nullable',
            'vat' => 'nullable',
            'status' => 'required|string|in:Ordered,For Pick-up,For Delivery,Delivered,Picked Up',
            'items' => 'required|array|min:1',
            'items.*.pr_no' => 'nullable|string|max:255',
            'items.*.bus_no' => 'nullable|string|max:255',
            'items.*.employee' => 'nullable|string|max:255',
            'items.*.item_description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.cost' => 'required',
        ]);

        $items = $this->cleanItems($request->items);

        if (count($items) === 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please add at least one valid item.');
        }

        $totals = $this->calculateTotals(
            $items,
            $request->delivery_fee,
            $request->discount,
            $request->vat
        );

        $purchaseRequest = null;

        if (! empty($validated['purchase_request_id'])) {
            $purchaseRequest = PurchaseRequest::find($validated['purchase_request_id']);
        }

        if (! $purchaseRequest) {
            $purchaseRequest = $purchaseOrder->purchaseRequest ?: $this->findFirstPurchaseRequest($items);
        }

        DB::transaction(function () use ($purchaseOrder, $validated, $items, $totals, $purchaseRequest) {
            $oldInventoryPostedAt = $purchaseOrder->inventory_posted_at;

            $purchaseOrder->update([
                'po_no' => $validated['po_no'],
                'po_date' => $validated['po_date'],
                'purchase_request_id' => $purchaseRequest?->id,
                'supplier_name' => $validated['supplier_name'],
                'supplier_address_tel' => $validated['supplier_address_tel'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'terms_of_payment' => $validated['terms_of_payment'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'items' => $items,
                'gross_amount' => $totals['gross_amount'],
                'delivery_fee' => $totals['delivery_fee'],
                'discount' => $totals['discount'],
                'vat' => $totals['vat'],
                'net_amount' => $totals['net_amount'],
                'status' => $validated['status'],
                'inventory_posted_at' => $oldInventoryPostedAt,
            ]);

            $this->syncRelatedPurchaseRequestsAndJobOrders($purchaseOrder, $validated['status']);

            if ($this->isInventoryPostingStatus($validated['status'])) {
                $this->postPurchaseOrderToInventory($purchaseOrder);
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Purchase order updated successfully.');
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Ordered,For Pick-up,For Delivery,Delivered,Picked Up',
        ]);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update([
                'status' => $validated['status'],
            ]);

            $this->syncRelatedPurchaseRequestsAndJobOrders($purchaseOrder, $validated['status']);

            if ($this->isInventoryPostingStatus($validated['status'])) {
                $this->postPurchaseOrderToInventory($purchaseOrder);
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Purchase order status updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        DB::transaction(function () use ($purchaseOrder) {
            $purchaseRequest = $purchaseOrder->purchaseRequest;

            $purchaseOrder->delete();

            if ($purchaseRequest && in_array($purchaseRequest->status, $this->statuses, true)) {
                $purchaseRequest->update([
                    'status' => 'For Purchase',
                ]);

                $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Purchase order deleted successfully.');
    }

    private function isInventoryPostingStatus(string $status): bool
    {
        return in_array($status, ['Delivered', 'Picked Up'], true);
    }

    private function postPurchaseOrderToInventory(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->refresh();

        if ($purchaseOrder->inventory_posted_at) {
            return;
        }

        $items = $purchaseOrder->items;

        if (! is_array($items) || count($items) === 0) {
            return;
        }

        foreach ($items as $item) {
            $rawItemName = trim($item['item_description'] ?? '');

            if ($rawItemName === '') {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unit = trim($item['unit'] ?? 'PC');
            $supplier = $purchaseOrder->supplier_name ?: 'N/A';

            /*
            |--------------------------------------------------------------------------
            | Important:
            | If item_description is accidentally saved as "tire, oil",
            | split it so inventory will receive separate records:
            | tire
            | oil
            |--------------------------------------------------------------------------
            */
            $itemNames = $this->splitItemNames($rawItemName);

            foreach ($itemNames as $itemName) {
                $inventoryItem = $this->findInventoryItem($itemName);

                if ($inventoryItem) {
                    $newOnHand = (int) $inventoryItem->on_hand + $quantity;

                    $inventoryItem->update([
                        'on_hand' => $newOnHand,
                        'quantity_available' => $newOnHand,
                        'unit' => $inventoryItem->unit ?: $unit,
                        'unit_of_measurement' => $inventoryItem->unit_of_measurement ?: $unit,
                        'supplier' => $inventoryItem->supplier ?: $supplier,
                        'status' => $this->inventoryStatus(
                            $newOnHand,
                            (int) ($inventoryItem->reorder_level ?? 0)
                        ),
                    ]);
                } else {
                    InventoryItem::create([
                        'item_code' => $this->generateInventoryItemCode(),
                        'item_name' => $itemName,
                        'parts_name' => $itemName,
                        'category' => 'Auto Parts',
                        'on_hand' => $quantity,
                        'quantity_available' => $quantity,
                        'unit' => $unit,
                        'unit_of_measurement' => $unit,
                        'reorder_level' => 5,
                        'status' => $this->inventoryStatus($quantity, 5),
                        'supplier' => $supplier,
                        'location' => 'Warehouse',
                        'storage_location' => 'Warehouse',
                    ]);
                }
            }
        }

        $purchaseOrder->update([
            'inventory_posted_at' => now(),
        ]);
    }

    private function splitItemNames(string $itemName): array
    {
        return collect(explode(',', $itemName))
            ->map(fn ($name) => trim($name))
            ->filter(fn ($name) => $name !== '')
            ->values()
            ->toArray();
    }

    private function findInventoryItem(string $itemName): ?InventoryItem
    {
        $itemName = strtolower(trim($itemName));

        if ($itemName === '') {
            return null;
        }

        return InventoryItem::query()
            ->whereRaw('LOWER(item_name) = ?', [$itemName])
            ->orWhereRaw('LOWER(parts_name) = ?', [$itemName])
            ->orWhereRaw('LOWER(item_code) = ?', [$itemName])
            ->first();
    }

    private function inventoryStatus(int $onHand, int $reorderLevel): string
    {
        if ($onHand <= 0) {
            return 'Critical';
        }

        if ($reorderLevel > 0 && $onHand <= $reorderLevel) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    private function generateInventoryItemCode(): string
    {
        do {
            $code = 'PART-' . strtoupper(Str::random(5));
        } while (InventoryItem::where('item_code', $code)->exists());

        return $code;
    }

    private function syncRelatedPurchaseRequestsAndJobOrders(PurchaseOrder $purchaseOrder, string $status): void
    {
        $purchaseRequests = collect();

        $purchaseOrder->refresh();

        if ($purchaseOrder->purchaseRequest) {
            $purchaseRequests->push($purchaseOrder->purchaseRequest);
        }

        foreach ($purchaseOrder->items ?? [] as $item) {
            $prNo = $item['pr_no'] ?? null;

            if (! $prNo) {
                continue;
            }

            $purchaseRequest = PurchaseRequest::where('pr_no', $prNo)->first();

            if ($purchaseRequest) {
                $purchaseRequests->push($purchaseRequest);
            }
        }

        $purchaseRequests
            ->unique('id')
            ->each(function (PurchaseRequest $purchaseRequest) use ($status) {
                if ($purchaseRequest->status === 'Issued') {
                    return;
                }

                $purchaseRequest->update([
                    'status' => $status,
                ]);

                $this->updateRelatedJobOrderPartStatus($purchaseRequest, $status);
            });
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

    private function findFirstPurchaseRequest(array $items): ?PurchaseRequest
    {
        foreach ($items as $item) {
            $prNo = $item['pr_no'] ?? null;

            if (! $prNo) {
                continue;
            }

            $purchaseRequest = PurchaseRequest::where('pr_no', $prNo)->first();

            if ($purchaseRequest) {
                return $purchaseRequest;
            }
        }

        return null;
    }

    private function cleanItems(?array $items): array
    {
        $cleanedItems = [];

        foreach ($items ?? [] as $item) {
            $prNo = trim($item['pr_no'] ?? '');
            $busNo = trim($item['bus_no'] ?? '');
            $employee = trim($item['employee'] ?? '');
            $itemDescription = trim($item['item_description'] ?? '');
            $quantity = (float) ($item['quantity'] ?? 0);
            $unit = trim($item['unit'] ?? '');
            $cost = $this->cleanCurrency($item['cost'] ?? 0);
            $amount = $quantity * $cost;

            if ($itemDescription !== '' && $quantity > 0) {
                $cleanedItems[] = [
                    'pr_no' => $prNo,
                    'bus_no' => $busNo,
                    'employee' => $employee,
                    'item_description' => $itemDescription,
                    'quantity' => $quantity,
                    'unit' => $unit ?: 'PC',
                    'cost' => $cost,
                    'amount' => $amount,
                ];
            }
        }

        return $cleanedItems;
    }

    private function calculateTotals(array $items, $deliveryFee, $discount, $vat): array
    {
        $grossAmount = 0;

        foreach ($items as $item) {
            $grossAmount += (float) $item['amount'];
        }

        $deliveryFee = $this->cleanCurrency($deliveryFee ?? 0);
        $discount = $this->cleanCurrency($discount ?? 0);
        $vat = $this->cleanCurrency($vat ?? 0);

        return [
            'gross_amount' => $grossAmount,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'vat' => $vat,
            'net_amount' => $grossAmount + $deliveryFee - $discount + $vat,
        ];
    }

    private function cleanCurrency($value): float
    {
        $cleaned = preg_replace('/[^\d.]/', '', (string) $value);

        return $cleaned !== '' ? (float) $cleaned : 0;
    }

    private function generatePoNo(): string
    {
        $year = now()->format('Y');

        $lastPurchaseOrder = PurchaseOrder::where('po_no', 'like', "PO-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $lastPurchaseOrder) {
            return "PO-{$year}-0001";
        }

        preg_match('/PO-' . $year . '-(\d+)/', $lastPurchaseOrder->po_no, $matches);

        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newPoNo = 'PO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseOrder::where('po_no', $newPoNo)->exists()) {
            $nextNumber++;
            $newPoNo = 'PO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPoNo;
    }
}