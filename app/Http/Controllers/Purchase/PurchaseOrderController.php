<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private array $statuses = [
        'For Review',
        'Revision Required',
        'Rejected',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
    ];

    public function index(Request $request)
    {
        $activeTab = $request->query('view') === 'history' ? 'history' : 'active';
        $query = PurchaseOrder::query()
            ->whereIn('status', $activeTab === 'history'
                ? ['Delivered', 'Picked Up', 'Rejected']
                : ['For Review', 'Revision Required', 'Ordered', 'For Pick-up', 'For Delivery']);


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

        TableSort::apply($query, $request, ['po_date' => 'po_date', 'po_no' => 'po_no', 'supplier_name' => 'supplier_name', 'total_amount' => 'net_amount', 'status' => 'status'], 'po_date', 'desc');
        $purchaseOrders = $query
            ->paginate(8)
            ->withQueryString();

        $totalOrders = PurchaseOrder::count();
        $ordered = PurchaseOrder::where('status', 'Ordered')->count();
        $forReview = PurchaseOrder::where('status', 'For Review')->count();
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
            'activeTab',
            'purchaseOrders',
            'totalOrders',
            'ordered',
            'forReview',
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
            'status' => 'required|string|in:For Review',
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

        if ($totals['net_amount'] < 0) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot make the purchase order total negative.',
            ]);
        }

        $maintenanceRequest = null;

        if (! empty($validated['purchase_request_id'])) {
            $maintenanceRequest = MaintenanceRequest::find($validated['purchase_request_id']);
        }

        if (! $maintenanceRequest) {
            $maintenanceRequest = $this->findFirstMaintenanceRequest($items);
        }

        $newPurchaseOrder = null;

        DB::transaction(function () use ($validated, $items, $totals, $maintenanceRequest, &$newPurchaseOrder) {
            if ($maintenanceRequest) {
                $maintenanceRequest = MaintenanceRequest::query()
                    ->whereKey($maintenanceRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($maintenanceRequest->status !== 'For Purchase') {
                    throw ValidationException::withMessages([
                        'purchase_request_id' => 'Only a For Purchase request can be linked to a new purchase order.',
                    ]);
                }

                // A restocking PO must exactly reflect its signed Warehouse requisition.
                // Purchasing may set costs and supplier details, not alter approved quantities.
                if ($maintenanceRequest->source_type === 'Auto Restock' && $maintenanceRequest->restock_items) {
                    $approvedLines = collect(json_decode((string) $maintenanceRequest->restock_items, true) ?: []);
                    $matches = count($items) === $approvedLines->count();
                    foreach ($approvedLines as $line) {
                        $matches = $matches && collect($items)->contains(function ($poLine) use ($line, $maintenanceRequest) {
                            return trim((string) $poLine['pr_no']) === $maintenanceRequest->pr_no
                                && trim((string) $poLine['item_description']) === trim((string) $line['name'])
                                && (int) $poLine['quantity'] === (int) $line['quantity']
                                && (float) $poLine['quantity'] === (float) (int) $line['quantity'];
                        });
                    }
                    if (! $matches) {
                        throw ValidationException::withMessages([
                            'items' => 'Restocking PO items and quantities must match the signed Warehouse PR.',
                        ]);
                    }
                }

                if (PurchaseOrder::query()->where('purchase_request_id', $maintenanceRequest->id)->exists()) {
                    throw ValidationException::withMessages([
                        'purchase_request_id' => 'A purchase order already exists for this purchase request.',
                    ]);
                }
            }

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
                'status' => 'For Review',
            ]);

        });

        if ($newPurchaseOrder) {
            $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'created', $newPurchaseOrder->id, 'A new purchase order was created.');
        }

        session()->flash('success', 'Purchase order submitted for Purchase Head review.');

        return new RedirectResponse('/purchase-orders');
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['For Review', 'Revision Required', 'Ordered'], true)) {
            return redirect()->back()->with(
                'error',
                'Only purchase orders under review or still Ordered can be edited.'
            );
        }

        $validated = $request->validate([
            'po_no' => 'required|string|max:255|unique:purchase_orders,po_no,'.$purchaseOrder->id,
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
            'status' => 'required|string|in:Ordered,For Review,Revision Required',
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

        if ($totals['net_amount'] < 0) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot make the purchase order total negative.',
            ]);
        }

        $maintenanceRequest = null;

        if (! empty($validated['purchase_request_id'])) {
            $maintenanceRequest = MaintenanceRequest::find($validated['purchase_request_id']);
        }

        if (! $maintenanceRequest) {
            $maintenanceRequest = $purchaseOrder->maintenanceRequest ?: $this->findFirstMaintenanceRequest($items);
        }

        DB::transaction(function () use ($purchaseOrder, $validated, $items, $totals, $maintenanceRequest) {
            $lockedOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, ['For Review', 'Revision Required', 'Ordered'], true) || $lockedOrder->inventory_posted_at !== null) {
                throw ValidationException::withMessages([
                    'status' => 'Only an unreceived PO under review or Ordered can be edited.',
                ]);
            }

            $oldRequests = $this->relatedMaintenanceRequests($lockedOrder, true);
            $oldInventoryPostedAt = $lockedOrder->inventory_posted_at;

            if ($maintenanceRequest) {
                $lockedRequest = MaintenanceRequest::query()
                    ->whereKey($maintenanceRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $alreadyLinked = $oldRequests->contains('id', $lockedRequest->id);
                $linkedToAnotherOrder = PurchaseOrder::query()
                    ->whereKeyNot($lockedOrder->id)
                    ->where('purchase_request_id', $lockedRequest->id)
                    ->exists();

                if ((! $alreadyLinked && $lockedRequest->status !== 'For Purchase') || $linkedToAnotherOrder) {
                    throw ValidationException::withMessages([
                        'purchase_request_id' => 'The selected purchase request is not available for this purchase order.',
                    ]);
                }
            }

            $lockedOrder->update([
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
                'status' => $lockedOrder->status === 'Revision Required' ? 'For Review' : $lockedOrder->status,
                'reviewed_by' => $lockedOrder->status === 'Revision Required' ? null : $lockedOrder->reviewed_by,
                'reviewed_at' => $lockedOrder->status === 'Revision Required' ? null : $lockedOrder->reviewed_at,
                'inventory_posted_at' => $oldInventoryPostedAt,
            ]);

            if ($lockedOrder->status === 'Ordered') {
                $this->syncRelatedMaintenanceRequestsAndJobOrders($lockedOrder, 'Ordered');
            }

            $newRequestIds = $this->relatedMaintenanceRequests($lockedOrder, true)->pluck('id');
            $oldRequests
                ->reject(fn (MaintenanceRequest $request) => $newRequestIds->contains($request->id))
                ->each(fn (MaintenanceRequest $request) => $this->resetRequestAfterOrderRemoval($request));
        });

        $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'updated', $purchaseOrder->id, 'A purchase order was updated.');
        session()->flash('success', 'Purchase order updated successfully.');

        return new RedirectResponse('/purchase-orders');
    }

    public function approvalSignature(PurchaseOrder $purchaseOrder)
    {
        $path = (string) ($purchaseOrder->approval_signature_path ?? '');
        if (!$purchaseOrder->approved_at || !$path || !str_starts_with($path, 'purchase-order-approvals/')
            || !preg_match('/^[a-zA-Z0-9_\\/.-]+$/', $path)) {
            abort(404);
        }
        $disk = Storage::disk('local');
        if (!$disk->exists($path)) {
            abort(404);
        }
        $type = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
        return response($disk->get($path), 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'signature' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'review_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        // Keep signature private; do not publish a public storage URL.
        $signaturePath = $request->file('signature')->store('purchase-order-approvals', 'local');

        try {
            DB::transaction(function () use ($purchaseOrder, $request, $validated, $signaturePath) {
                $lockedOrder = PurchaseOrder::query()->whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->status !== 'For Review') {
                    throw ValidationException::withMessages(['status' => 'Only purchase orders awaiting review may be approved.']);
                }

                $lockedOrder->update([
                    'status' => 'Ordered',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'approval_signature_path' => $signaturePath,
                    'review_remarks' => $validated['review_remarks'] ?? null,
                ]);
                $this->syncRelatedMaintenanceRequestsAndJobOrders($lockedOrder, 'Ordered');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($signaturePath);
            throw $e;
        }

        $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'approved', $purchaseOrder->id, 'Purchase Head approved a purchase order.');
        return redirect('/purchase-orders')->with('success', 'Purchase Order approved and marked Ordered.');
    }

    public function reviewDecision(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:revision,reject'],
            'review_remarks' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $request, $validated) {
            $lockedOrder = PurchaseOrder::query()->whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== 'For Review') {
                throw ValidationException::withMessages(['status' => 'Only purchase orders awaiting review may be returned or rejected.']);
            }

            $lockedOrder->update([
                'status' => $validated['decision'] === 'revision' ? 'Revision Required' : 'Rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_remarks' => $validated['review_remarks'],
            ]);
        });

        $this->broadcastSystemDataUpdated('Purchase', 'PurchaseOrder', 'reviewed', $purchaseOrder->id, 'A Purchase Head review decision was recorded.');
        return redirect('/purchase-orders')->with('success', 'Purchase Order review decision saved.');
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:For Delivery',
        ]);

        $allowedTransitions = [
            'Ordered' => ['For Delivery'],
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
                throw ValidationException::withMessages([
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
        if ($purchaseOrder->inventory_posted_at !== null || in_array($purchaseOrder->status, ['Delivered', 'Picked Up'], true)) {
            return redirect()->back()->with(
                'error',
                'A received purchase order cannot be deleted because its inventory movement must remain auditable.'
            );
        }

        $purchaseOrderId = $purchaseOrder->id;

        DB::transaction(function () use ($purchaseOrder) {
            $lockedOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->inventory_posted_at !== null || in_array($lockedOrder->status, ['Delivered', 'Picked Up'], true)) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'A received purchase order cannot be deleted.',
                ]);
            }

            $maintenanceRequests = $this->relatedMaintenanceRequests($lockedOrder, true);
            $lockedOrder->delete();

            $maintenanceRequests->each(
                fn (MaintenanceRequest $request) => $this->resetRequestAfterOrderRemoval($request)
            );
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

            $maintenanceRequest = MaintenanceRequest::where('pr_no', $prNo)
                ->lockForUpdate()
                ->first();
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

        // Purchase progress must never erase physical issuance recorded by Warehouse.
        // Issued/Partial are ledger-backed JO states, not procurement milestones.
        if (in_array($jobOrder->part_status, ['Issued', 'Partial'], true)) {
            return;
        }

        $jobOrder->update(['part_status' => $partStatus]);
    }

    private function relatedMaintenanceRequests(PurchaseOrder $purchaseOrder, bool $lock = false): Collection
    {
        $ids = collect([$purchaseOrder->purchase_request_id])->filter();
        $prNumbers = collect($purchaseOrder->items ?? [])
            ->map(fn (array $item) => $this->normalizePrNo($item['pr_no'] ?? null))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() && $prNumbers->isEmpty()) {
            return collect();
        }

        $query = MaintenanceRequest::query()
            ->where(function ($builder) use ($ids, $prNumbers) {
                if ($ids->isNotEmpty()) {
                    $builder->whereIn('id', $ids);
                }

                if ($prNumbers->isNotEmpty()) {
                    $method = $ids->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $builder->{$method}('pr_no', $prNumbers);
                }
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function resetRequestAfterOrderRemoval(MaintenanceRequest $maintenanceRequest): void
    {
        if ($maintenanceRequest->status === 'Issued') {
            return;
        }

        if (PurchaseOrder::query()->where('purchase_request_id', $maintenanceRequest->id)->exists()) {
            return;
        }

        $maintenanceRequest->update(['status' => 'For Purchase']);
        $this->updateRelatedJobOrderPartStatus($maintenanceRequest, 'For Purchase');
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

        preg_match('/PO-'.$year.'-(\d+)/', $lastPurchaseOrder->po_no, $matches);
        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newPoNo = 'PO-'.$year.'-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseOrder::where('po_no', $newPoNo)->exists()) {
            $nextNumber++;
            $newPoNo = 'PO-'.$year.'-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPoNo;
    }
}
