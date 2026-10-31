<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\ItemRequest;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryLedgerService;
use App\Services\Maintenance\PartsReservationService;
use App\Services\Maintenance\ItemRequestPartsService;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncomingDeliveryController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function __construct(
        private InventoryLedgerService $ledger,
        private PartsReservationService $reservations,
        private ItemRequestPartsService $irParts
    ) {}

    public function index(Request $request)
    {
        return view('Warehouse.incoming-deliveries', $this->data($request));
    }

    public function data(Request $request): array
    {
        $currentView = strtolower(trim((string) $request->input('view', 'active')));
        if (! in_array($currentView, ['active', 'history'], true)) {
            $currentView = 'active';
        }

        $deliveryQuery = PurchaseOrder::query();

        if ($currentView === 'history') {
            $deliveryQuery->whereNotNull('inventory_posted_at');
            $statusOptions = ['Delivered', 'Picked Up'];
        } else {
            $deliveryQuery
                ->whereIn('status', ['Ordered', 'For Delivery', 'For Pick-up'])
                ->whereNull('inventory_posted_at');
            $statusOptions = ['Ordered', 'For Delivery', 'For Pick-up'];
        }

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
            $deliveryQuery->where('status', $request->status);
        }

        TableSort::apply($deliveryQuery, $request, ['po_date' => 'po_date', 'po_no' => 'po_no', 'supplier_name' => 'supplier_name', 'status' => 'status', 'inventory_posted_at' => 'inventory_posted_at'], 'po_date', 'desc');
        $deliveries = $deliveryQuery
            ->paginate(20)
            ->withQueryString();

        $activeCount = PurchaseOrder::query()
            ->whereIn('status', ['Ordered', 'For Delivery', 'For Pick-up'])
            ->whereNull('inventory_posted_at')
            ->count();

        $historyCount = PurchaseOrder::query()
            ->whereNotNull('inventory_posted_at')
            ->count();

        $totalIncoming = $activeCount;
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')
            ->whereNull('inventory_posted_at')
            ->count();
        $delivered = $historyCount;
        $receivedToday = PurchaseOrder::whereDate('inventory_posted_at', today())->count();

        return compact(
            'deliveries',
            'totalIncoming',
            'forDelivery',
            'delivered',
            'receivedToday',
            'currentView',
            'statusOptions',
            'activeCount',
            'historyCount'
        );
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeWarehouseCapability('edit');

        DB::transaction(function () use ($purchaseOrder, $request): void {
            $lockedOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->inventory_posted_at) {
                throw ValidationException::withMessages([
                    'delivery' => 'This purchase order has already been received.',
                ]);
            }

            $receivedStatus = match ($lockedOrder->status) {
                'Ordered' => 'Delivered',
                'For Delivery' => 'Delivered',
                'For Pick-up' => 'Picked Up',
                default => null,
            };

            if (! $receivedStatus) {
                throw ValidationException::withMessages([
                    'delivery' => 'Only ordered purchase orders awaiting receipt may be received.',
                ]);
            }

            $items = $this->validatedReceiptItems($lockedOrder);

            $cumulative = $lockedOrder->received_quantities ?? [];
            $posted = [];
            $requestedQuantities = $request->input('received_quantities', []);
            if (! is_array($requestedQuantities)) {
                throw ValidationException::withMessages(['delivery' => 'Invalid received quantities.']);
            }
            if ($lockedOrder->maintenanceRequest?->source_type === 'Auto Restock' && $requestedQuantities === []) {
                throw ValidationException::withMessages(['delivery' => 'Enter actual received quantities for this restocking PO.']);
            }
            foreach ($items as $index => $item) {
                $already = (int) ($cumulative[$index] ?? 0);
                $remaining = $item['quantity'] - $already;
                if ($remaining < 0) {
                    throw ValidationException::withMessages(['delivery' => 'Recorded receipts exceed the PO quantity.']);
                }
                $raw = $requestedQuantities[$index] ?? $remaining;
                if (! is_scalar($raw) || ! preg_match('/^\\d+$/', (string) $raw)) {
                    throw ValidationException::withMessages(['delivery' => 'Received quantities must be nonnegative whole numbers.']);
                }
                $quantity = (int) $raw;
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages(['delivery' => 'Received quantity exceeds the outstanding balance.']);
                }
                $posted[$index] = $quantity;
                $cumulative[$index] = $already + $quantity;
            }
            if (array_sum($posted) === 0) {
                throw ValidationException::withMessages(['delivery' => 'Enter at least one received item quantity.']);
            }

            foreach ($items as $index => $item) {
                if ($posted[$index] === 0) continue;
                $item['quantity'] = $posted[$index];
                foreach ($this->splitItemNames($item['name']) as $itemName) {
                    $inventoryItem = $this->findInventoryItemForUpdate($itemName);

                    if (! $inventoryItem) {
                        $inventoryItem = InventoryItem::create([
                            'item_code' => $this->generateInventoryItemCode(),
                            'parts_name' => $itemName,
                            'item_name' => $itemName,
                            'category' => 'Auto Parts',
                            'on_hand' => 0,
                            'quantity_available' => 0,
                            'unit' => $item['unit'],
                            'unit_of_measurement' => $item['unit'],
                            'reorder_level' => 5,
                            'supplier' => $lockedOrder->supplier_name ?: 'N/A',
                            'location' => 'Warehouse',
                            'storage_location' => 'Warehouse',
                            'source' => 'app',
                        ]);
                    }

                    $movement = $this->ledger->stockIn(
                        $inventoryItem,
                        $item['quantity'],
                        $lockedOrder->po_no,
                        'Received from Purchase Order.',
                        auth()->id()
                    );

                    $freshItem = $movement->inventoryItem()->firstOrFail();
                    $freshItem->forceFill([
                        'supplier' => $freshItem->supplier ?: ($lockedOrder->supplier_name ?: 'N/A'),
                        'unit' => $freshItem->unit ?: $item['unit'],
                        'unit_of_measurement' => $freshItem->unit_of_measurement ?: $item['unit'],
                    ])->save();
                }
            }

            $complete = collect($items)->every(fn ($item, $index) =>
                (int) ($cumulative[$index] ?? 0) === $item['quantity']
            );
            $lockedOrder->forceFill([
                'received_quantities' => $cumulative,
                'status' => $complete ? $receivedStatus : $lockedOrder->status,
                'inventory_posted_at' => $complete ? now() : null,
            ])->save();

            if ($complete) {
                $this->syncRelatedRequestsAndJobOrders($lockedOrder, $receivedStatus);
            }
            // Reassess the approved JO only after the receipt stock-in is posted.
            // This reserves newly available quantities for its existing approved
            // release policy; it does not create an IR, deduct stock, or issue SWIS.
            $this->reserveReceivedPartsForLinkedJobOrders($lockedOrder);
        });

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseOrder',
            'received',
            $purchaseOrder->id,
            'Warehouse received a purchase order.'
        );

        return redirect('/warehouse/incoming-deliveries')
            ->with('success', 'Delivery received and inventory updated successfully.');
    }

    private function validatedReceiptItems(PurchaseOrder $purchaseOrder): array
    {
        if (! is_array($purchaseOrder->items) || $purchaseOrder->items === []) {
            throw ValidationException::withMessages([
                'delivery' => 'This purchase order has no items to receive.',
            ]);
        }

        return collect($purchaseOrder->items)->map(function ($item): array {
            $name = trim((string) ($item['item_description'] ?? ''));
            $rawQuantity = $item['quantity'] ?? null;
            $numericQuantity = is_numeric($rawQuantity) ? (float) $rawQuantity : 0;
            $quantity = (int) $numericQuantity;

            if ($name === '' || $quantity <= 0 || $numericQuantity !== (float) $quantity) {
                throw ValidationException::withMessages([
                    'delivery' => 'Every purchase order item must have a description and a positive whole-number quantity.',
                ]);
            }

            return [
                'name' => $name,
                'quantity' => $quantity,
                'unit' => trim((string) ($item['unit'] ?? '')) ?: 'PC',
            ];
        })->all();
    }

    private function syncRelatedRequestsAndJobOrders(PurchaseOrder $purchaseOrder, string $status): void
    {
        $requests = collect();

        if ($purchaseOrder->maintenanceRequest) {
            $requests->push($purchaseOrder->maintenanceRequest);
        }

        foreach ($purchaseOrder->items ?? [] as $item) {
            $prNo = $this->normalizePrNo($item['pr_no'] ?? null);
            if ($prNo && ($request = MaintenanceRequest::where('pr_no', $prNo)->first())) {
                $requests->push($request);
            }
        }

        $requests->unique('id')->each(function (MaintenanceRequest $request) use ($status): void {
            if ($request->status === 'Issued') {
                return;
            }

            $request->update(['status' => $status]);

            if ($request->job_order_no !== 'RESTOCK') {
                JobOrder::query()
                    ->where('job_order_no', $request->job_order_no)
                    ->whereNotNull('part_needed')
                    // Receiving stock cannot undo an already recorded JO handover.
                    ->whereNotIn('part_status', ['Issued', 'Partial'])
                    ->update(['part_status' => $status]);
            }
        });
    }

    /** Replenished quantities must not be taken by another JO while awaiting IR handover. */
    private function reserveReceivedPartsForLinkedJobOrders(PurchaseOrder $purchaseOrder): void
    {
        $prNumbers = collect($purchaseOrder->items ?? [])
            ->pluck('pr_no')
            ->filter()
            ->map(fn ($prNo) => $this->normalizePrNo((string) $prNo))
            ->filter()->unique()->values();

        $requests = MaintenanceRequest::query()
            ->where(function ($query) use ($purchaseOrder, $prNumbers): void {
                if ($purchaseOrder->purchase_request_id) {
                    $query->whereKey($purchaseOrder->purchase_request_id);
                    if ($prNumbers->isNotEmpty()) {
                        $query->orWhereIn('pr_no', $prNumbers);
                    }
                } elseif ($prNumbers->isNotEmpty()) {
                    $query->whereIn('pr_no', $prNumbers);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->whereNotNull('job_order_no')
            ->where('job_order_no', '!=', 'RESTOCK')
            ->get();

        $jobNos = $requests->pluck('job_order_no')->filter()->unique();
        if ($jobNos->isEmpty()) {
            return;
        }

        // Stable row order and existing transaction protect against competing
        // IR submissions; topUp itself locks inventory and avoids double reservations.
        JobOrder::query()->whereIn('job_order_no', $jobNos)
            ->where('review_status', 'Approved')
            ->where('status', '!=', 'Completed')
            ->orderBy('id')
            ->lockForUpdate()->get()
            ->each(function (JobOrder $jobOrder): void {
                $this->reservations->topUp($jobOrder);
                $this->refreshSubmittedItemRequest($jobOrder);
            });
    }

    /**
     * Only a wholly unissued IR may grow after replenishment. A previously
     * issued IR is an immutable handover record; Maintenance must create its
     * next linked IR from the remaining-JO suggestion instead.
     */
    private function refreshSubmittedItemRequest(JobOrder $jobOrder): void
    {
        $ir = ItemRequest::query()
            ->where('job_order_id', $jobOrder->id)
            ->where('status', 'Submitted')
            ->lockForUpdate()
            ->first();

        if (!$ir) {
            return;
        }

        $additionalParts = $this->irParts->availableParts((string) $jobOrder->part_needed, $jobOrder);
        if ($additionalParts === []) {
            return;
        }

        $parts = collect($ir->requested_parts ?? []);
        foreach ($additionalParts as $newPart) {
            $index = $parts->search(fn ($part) =>
                (int) ($part['inventory_item_id'] ?? 0) === (int) ($newPart['inventory_item_id'] ?? 0)
                && mb_strtolower(trim((string) ($part['name'] ?? ''))) === mb_strtolower(trim((string) ($newPart['name'] ?? '')))
                && mb_strtolower(trim((string) ($part['unit'] ?? ''))) === mb_strtolower(trim((string) ($newPart['unit'] ?? '')))
            );
            if ($index === false) {
                $parts->push($newPart);
            } else {
                $part = $parts[$index];
                $part['quantity'] = (int) ($part['quantity'] ?? 0) + (int) $newPart['quantity'];
                $parts[$index] = $part;
            }
        }
        $ir->update(['requested_parts' => $parts->values()->all()]);
    }

    private function findInventoryItemForUpdate(string $itemName): ?InventoryItem
    {
        $normalizedName = strtolower(trim($itemName));

        return InventoryItem::query()
            ->where(function ($query) use ($normalizedName): void {
                $query->whereRaw('LOWER(TRIM(item_name)) = ?', [$normalizedName]);

                if (Schema::hasColumn('inventory_items', 'parts_name')) {
                    $query->orWhereRaw('LOWER(TRIM(parts_name)) = ?', [$normalizedName]);
                }

                $query->orWhereRaw('LOWER(TRIM(item_code)) = ?', [$normalizedName]);
            })
            ->lockForUpdate()
            ->first();
    }

    private function splitItemNames(string $itemName): array
    {
        return collect(explode(',', $itemName))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values()
            ->all();
    }

    private function generateInventoryItemCode(): string
    {
        do {
            $code = 'PART-'.strtoupper(Str::random(5));
        } while (InventoryItem::where('item_code', $code)->exists());

        return $code;
    }

    private function normalizePrNo(?string $prNo): ?string
    {
        $prNo = trim((string) $prNo);

        return $prNo === '' ? null : preg_replace('/-P(?:\d+)?$/i', '', $prNo);
    }

    private function authorizeWarehouseCapability(string $capability): void
    {
        abort_unless(
            auth()->user()?->hasSystemPermission('warehouse', $capability) ?? false,
            403,
            "Your role does not have permission to {$capability} warehouse records."
        );
    }
}
