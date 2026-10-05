<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;

class PurchaseOrderController extends Controller
{
    use SystemDataUpdateBroadcaster;

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
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('po_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('supplier_address_tel', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('status') &&
            ! in_array($request->status, ['All States', 'All Statuses'], true)
        ) {
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

        $usedMaintenanceRequestIds = PurchaseOrder::query()
            ->whereNotNull('purchase_request_id')
            ->pluck('purchase_request_id')
            ->toArray();

        $availablePurchaseRequests = MaintenanceRequest::query()
            ->where('status', 'For Purchase')
            ->when(! empty($usedMaintenanceRequestIds), function ($q) use ($usedMaintenanceRequestIds) {
                $q->whereNotIn('id', $usedMaintenanceRequestIds);
            })
            ->orderBy('pr_no')
            ->get();

        $selectedPurchaseRequest = null;

        if ($request->filled('create_from_pr')) {
            $selectedPurchaseRequest = MaintenanceRequest::query()
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
            'delivery_fee' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:Ordered,For Pick-up,For Delivery,Delivered,Picked Up',
            'items' => 'required|array|min:1',
            'items.*.pr_no' => 'nullable|string|max:255',
            'items.*.bus_no' => 'nullable|string|max:255',
            'items.*.employee' => 'nullable|string|max:255',
            'items.*.item_description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.cost' => 'required|numeric|min:0',
        ]);

        $items = $this->cleanItems($request->items);

        if (count($items) === 0) {
            return redirect()->back()->withInput()->with('error', 'Please add at least one valid item.');
        }

        $totals = $this->calculateTotals($items, $request->delivery_fee, $request->discount, $request->vat);

        $maintenanceRequest = null;

        if (! empty($validated['purchase_request_id'])) {
            $maintenanceRequest = MaintenanceRequest::find($validated['purchase_request_id']);
        }

        if (! $maintenanceRequest) {
            $maintenanceRequest = $this->findFirstMaintenanceRequest($items);
        }

        $newPurchaseOrder = null;

        DB::transaction(function () use ($validated, $items, $totals, $maintenanceRequest, &$newPurchaseOrder) {
            $newPurchaseOrder = PurchaseOrder::create([
                'po_no' => $this->generatePoNo(),
                'po_date' => now()->toDateString(),
                'purchase_request_id' => $maintenanceRequest?->id,
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

            $this->syncRelatedMaintenanceRequestsAndJobOrders($newPurchaseOrder, $validated['status']);
        });

        if ($newPurchaseOrder) {
            $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'created', $newPurchaseOrder->id, 'A new purchase order was created.');
        }

        session()->flash('success', 'Purchase order created successfully.');

        return new RedirectResponse('/purchase-orders');
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
            'delivery_fee' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:Ordered,For Pick-up,For Delivery,Delivered,Picked Up',
            'items' => 'required|array|min:1',
            'items.*.pr_no' => 'nullable|string|max:255',
            'items.*.bus_no' => 'nullable|string|max:255',
            'items.*.employee' => 'nullable|string|max:255',
            'items.*.item_description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.cost' => 'required|numeric|min:0',
        ]);

        $items = $this->cleanItems($request->items);

        if (count($items) === 0) {
            return redirect()->back()->withInput()->with('error', 'Please add at least one valid item.');
        }

        $totals = $this->calculateTotals($items, $request->delivery_fee, $request->discount, $request->vat);

        $maintenanceRequest = null;

        if (! empty($validated['purchase_request_id'])) {
            $maintenanceRequest = MaintenanceRequest::find($validated['purchase_request_id']);
        }

        if (! $maintenanceRequest) {
            $maintenanceRequest = $purchaseOrder->maintenanceRequest ?: $this->findFirstMaintenanceRequest($items);
        }

        DB::transaction(function () use ($purchaseOrder, $validated, $items, $totals, $maintenanceRequest) {
            $oldInventoryPostedAt = $purchaseOrder->inventory_posted_at;

            $purchaseOrder->update([
                'po_no' => $validated['po_no'],
                'po_date' => $validated['po_date'],
                'purchase_request_id' => $maintenanceRequest?->id,
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

            $this->syncRelatedMaintenanceRequestsAndJobOrders($purchaseOrder, $validated['status']);
        });

        $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'updated', $purchaseOrder->id, 'A purchase order was updated.');
        session()->flash('success', 'Purchase order updated successfully.');

        return new RedirectResponse('/purchase-orders');
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:For Pick-up,For Delivery',
        ]);

        $allowedTransitions = [
            'Ordered' => ['For Pick-up', 'For Delivery'],
        ];

        if (! in_array($validated['status'], $allowedTransitions[$purchaseOrder->status] ?? [], true)) {
            return redirect()->back()->with('error', 'That purchase order status change is not allowed from the current workflow state.');
        }

        DB::transaction(function () use ($purchaseOrder, $validated, $allowedTransitions) {
            $lockedOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($validated['status'], $allowedTransitions[$lockedOrder->status] ?? [], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'That purchase order status change is no longer allowed.',
                ]);
            }

            $lockedOrder->update(['status' => $validated['status']]);
            $this->syncRelatedMaintenanceRequestsAndJobOrders($lockedOrder, $validated['status']);
        });

        $this->broadcastSystemDataUpdated(
            'Purchase',
            'PurchaseOrder',
            'status_updated',
            $purchaseOrder->id,
            'A purchase order status was updated.'
        );

        session()->flash('success', 'Purchase order status updated successfully.');

        return new RedirectResponse('/purchase-orders');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrderId = $purchaseOrder->id;

        DB::transaction(function () use ($purchaseOrder) {
            $maintenanceRequest = $purchaseOrder->maintenanceRequest;
            $purchaseOrder->delete();

            if ($maintenanceRequest && in_array($maintenanceRequest->status, $this->statuses, true)) {
                $maintenanceRequest->update(['status' => 'For Purchase']);
                $this->updateRelatedJobOrderPartStatus($maintenanceRequest, 'For Purchase');
            }
        });

        $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'deleted', $purchaseOrderId, 'A purchase order was deleted.');
        session()->flash('success', 'Purchase order deleted successfully.');

        return new RedirectResponse('/purchase-orders');
    }

    private function syncRelatedMaintenanceRequestsAndJobOrders(PurchaseOrder $purchaseOrder, string $status): void
    {
        $maintenanceRequests = collect();
        $purchaseOrder->refresh();

        if ($purchaseOrder->maintenanceRequest) {
            $maintenanceRequests->push($purchaseOrder->maintenanceRequest);
        }

        foreach ($purchaseOrder->items ?? [] as $item) {
            $prNo = $this->normalizePrNo($item['pr_no'] ?? null);

            if (! $prNo) {
                continue;
            }

            $maintenanceRequest = MaintenanceRequest::where('pr_no', $prNo)->first();
            if ($maintenanceRequest) {
                $maintenanceRequests->push($maintenanceRequest);
            }
        }

        $maintenanceRequests->unique('id')->each(function (MaintenanceRequest $maintenanceRequest) use ($status) {
            if ($maintenanceRequest->status === 'Issued') {
                return;
            }

            $maintenanceRequest->update(['status' => $status]);
            $this->updateRelatedJobOrderPartStatus($maintenanceRequest, $status);
        });
    }

    private function updateRelatedJobOrderPartStatus(MaintenanceRequest $maintenanceRequest, string $partStatus): void
    {
        if ($maintenanceRequest->job_order_no === 'RESTOCK') {
            return;
        }

        $jobOrder = JobOrder::where('job_order_no', $maintenanceRequest->job_order_no)->first();

        if (! $jobOrder || empty($jobOrder->part_needed)) {
            return;
        }

        $jobOrder->update(['part_status' => $partStatus]);
    }

    private function findFirstMaintenanceRequest(array $items): ?MaintenanceRequest
    {
        foreach ($items as $item) {
            $prNo = $this->normalizePrNo($item['pr_no'] ?? null);

            if (! $prNo) {
                continue;
            }

            $maintenanceRequest = MaintenanceRequest::where('pr_no', $prNo)->first();
            if ($maintenanceRequest) {
                return $maintenanceRequest;
            }
        }

        return null;
    }

    private function normalizePrNo(?string $prNo): ?string
    {
        $prNo = trim((string) $prNo);
        if ($prNo === '') {
            return null;
        }

        return preg_replace('/-P$/i', '', $prNo);
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
