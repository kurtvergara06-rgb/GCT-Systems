<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryLedgerService;
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

    public function __construct(private InventoryLedgerService $ledger)
    {
    }

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
                ->whereIn('status', ['For Delivery', 'For Pick-up'])
                ->whereNull('inventory_posted_at');
            $statusOptions = ['For Delivery', 'For Pick-up'];
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

        $deliveries = $deliveryQuery
            ->latest($currentView === 'history' ? 'inventory_posted_at' : 'updated_at')
            ->paginate(20)
            ->withQueryString();

        $activeCount = PurchaseOrder::query()
            ->whereIn('status', ['For Delivery', 'For Pick-up'])
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

    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizeWarehouseCapability('edit');

        DB::transaction(function () use ($purchaseOrder): void {
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
                'For Delivery' => 'Delivered',
                'For Pick-up' => 'Picked Up',
                default => null,
            };

            if (! $receivedStatus) {
                throw ValidationException::withMessages([
                    'delivery' => 'Only purchase orders marked For Delivery or For Pick-up may be received.',
                ]);
            }

            $items = $this->validatedReceiptItems($lockedOrder);

            foreach ($items as $item) {
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

            $lockedOrder->forceFill([
                'status' => $receivedStatus,
                'inventory_posted_at' => now(),
            ])->save();

            $this->syncRelatedRequestsAndJobOrders($lockedOrder, $receivedStatus);
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
                    ->update(['part_status' => $status]);
            }
        });
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
