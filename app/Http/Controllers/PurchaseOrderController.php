<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\JobOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
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
        $forPurchase = PurchaseOrder::where('status', 'For Purchase')->count();
        $ordered = PurchaseOrder::where('status', 'Ordered')->count();
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')->count();
        $delivered = PurchaseOrder::where('status', 'Delivered')->count();

        $nextPoNo = $this->generatePoNo();
        $statuses = $this->statuses;

        $availablePurchaseRequests = PurchaseRequest::query()
            ->whereIn('status', [
                'For Purchase',
                'Pending Purchase',
                'Delivering',
                'Delivered',
                'Issued',
            ])
            ->orderBy('pr_no')
            ->get();

        return view('Purchase.purchase-orders', compact(
            'purchaseOrders',
            'totalOrders',
            'forPurchase',
            'ordered',
            'forDelivery',
            'delivered',
            'nextPoNo',
            'statuses',
            'availablePurchaseRequests'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'supplier_address_tel' => 'nullable|string',
            'terms' => 'nullable|string|max:255',
            'terms_of_payment' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'delivery_fee' => 'nullable',
            'discount' => 'nullable',
            'vat' => 'nullable',
            'status' => 'required|string|in:For Purchase,Ordered,For Pick-up,For Delivery,Delivered,Picked Up',

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
                ->route('purchase-orders')
                ->withInput()
                ->with('error', 'Please add at least one valid item.');
        }

        $totals = $this->calculateTotals(
            $items,
            $request->delivery_fee,
            $request->discount,
            $request->vat
        );

        PurchaseOrder::create([
            'po_no' => $this->generatePoNo(),
            'po_date' => now()->toDateString(),
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

        if ($validated['status'] === 'Delivered') {
            $this->markRelatedJobOrdersAsDelivered($items);
        }

        return redirect()
            ->route('purchase-orders')
            ->with('success', 'Purchase order created successfully.');
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'po_no' => 'required|string|max:255|unique:purchase_orders,po_no,' . $purchaseOrder->id,
            'po_date' => 'required|date',
            'supplier_name' => 'required|string|max:255',
            'supplier_address_tel' => 'nullable|string',
            'terms' => 'nullable|string|max:255',
            'terms_of_payment' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'delivery_fee' => 'nullable',
            'discount' => 'nullable',
            'vat' => 'nullable',
            'status' => 'required|string|in:For Purchase,Ordered,For Pick-up,For Delivery,Delivered,Picked Up',

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
                ->route('purchase-orders')
                ->withInput()
                ->with('error', 'Please add at least one valid item.');
        }

        $totals = $this->calculateTotals(
            $items,
            $request->delivery_fee,
            $request->discount,
            $request->vat
        );

        $purchaseOrder->update([
            'po_no' => $validated['po_no'],
            'po_date' => $validated['po_date'],
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

        if ($validated['status'] === 'Delivered') {
            $this->markRelatedJobOrdersAsDelivered($items);
        }

        return redirect()
            ->route('purchase-orders')
            ->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();

        return redirect()
            ->route('purchase-orders')
            ->with('success', 'Purchase order deleted successfully.');
    }

    private function markRelatedJobOrdersAsDelivered(array $items): void
    {
        foreach ($items as $item) {
            $prNo = $item['pr_no'] ?? null;

            if (empty($prNo)) {
                continue;
            }

            $purchaseRequest = PurchaseRequest::where('pr_no', $prNo)->first();

            if (! $purchaseRequest) {
                continue;
            }

            $jobOrderNo = $purchaseRequest->job_order_no
                ?? $purchaseRequest->jo_no
                ?? null;

            if (empty($jobOrderNo)) {
                continue;
            }

            $jobOrder = JobOrder::where('job_order_no', $jobOrderNo)->first();

            if ($jobOrder) {
                $jobOrder->update([
                    'part_status' => 'Delivered',
                ]);
            }
        }
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

        $netAmount = $grossAmount + $deliveryFee - $discount + $vat;

        return [
            'gross_amount' => $grossAmount,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'vat' => $vat,
            'net_amount' => $netAmount,
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

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
        $nextNumber = $lastNumber + 1;

        $newPoNo = 'PO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseOrder::where('po_no', $newPoNo)->exists()) {
            $nextNumber++;
            $newPoNo = 'PO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPoNo;
    }
}