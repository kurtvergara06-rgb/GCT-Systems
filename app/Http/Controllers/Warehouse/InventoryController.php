<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use App\Services\Warehouse\InventoryLedgerService;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private InventoryLedgerService $ledger;

    public function __construct(InventoryLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function index(Request $request)
    {
        $this->syncAutoRestockRequests();

        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                    ->orWhere('parts_name', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('storage_location', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('category')
            && $request->category !== 'All Categories'
        ) {
            $query->where('category', $request->category);
        }

        $inventoryItems = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $categories = InventoryItem::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $issueItems = InventoryItem::query()
            ->orderBy('item_name')
            ->get(['id', 'item_code', 'item_name', 'parts_name', 'on_hand', 'quantity_available', 'unit', 'unit_of_measurement']);

        $totalItemsInStock = InventoryItem::count();

        $lowStockAlerts = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->where('on_hand', '>', 0)
            ->count();

        $criticalItems = InventoryItem::query()
            ->where('on_hand', '<=', 0)
            ->count();

        $forecastedStockouts = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->count();

        $itemsAtRisk = $forecastedStockouts;

        return view('Warehouse.inventory', compact(
            'inventoryItems',
            'categories',
            'issueItems',
            'totalItemsInStock',
            'lowStockAlerts',
            'criticalItems',
            'forecastedStockouts',
            'itemsAtRisk'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_items', 'item_code'),
            ],
            'parts_name' => ['nullable', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_of_measurement' => ['required', 'string', 'max:255'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ], [
            'item_code.unique' => 'The item code already exists. Please use a different item code.',
        ]);

        $inventoryItem = null;
        $initialStock = (int) $validated['on_hand'];

        unset($validated['on_hand'], $validated['quantity_available']);

        DB::transaction(function () use ($validated, $initialStock, &$inventoryItem) {
            $validated['parts_name'] =
                $validated['parts_name']
                ?? $validated['item_name'];

            $validated['unit'] =
                $validated['unit']
                ?? $validated['unit_of_measurement'];

            $validated['location'] =
                $validated['location']
                ?? $validated['storage_location']
                ?? null;

            $validated['on_hand'] = 0;
            $validated['quantity_available'] = 0;

            $validated['status'] = $this->inventoryStatus(
                0,
                (int) $validated['reorder_level']
            );

            $inventoryItem = InventoryItem::create($validated);

            if ($initialStock > 0) {
                $this->ledger->adjustTo(
                    $inventoryItem,
                    $initialStock,
                    $inventoryItem->item_code ?? $inventoryItem->item_name,
                    'Opening balance on item creation.',
                    auth()->id()
                );
            }

            $this->createAutoRestockRequestIfNeeded($inventoryItem);
        });

        if ($inventoryItem) {
            $this->broadcastSystemDataUpdated(
                'Warehouse',
                'Inventory',
                'created',
                $inventoryItem->id,
                'A new inventory item was added.'
            );
        }

        session()->flash(
            'success',
            'Inventory item added successfully.'
        );

        return new RedirectResponse('/inventory');
    }

    public function update(
        Request $request,
        InventoryItem $inventoryItem
    ): RedirectResponse {
        $validated = $request->validate([
            'item_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_items', 'item_code')
                    ->ignore($inventoryItem->id),
            ],
            'parts_name' => ['nullable', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_of_measurement' => ['required', 'string', 'max:255'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ], [
            'item_code.unique' => 'The item code already belongs to another inventory item.',
        ]);

        $targetStock = (int) $validated['on_hand'];

        unset($validated['on_hand'], $validated['quantity_available']);

        DB::transaction(function () use ($validated, $targetStock, $inventoryItem) {
            $validated['parts_name'] =
                $validated['parts_name']
                ?? $validated['item_name'];

            $validated['unit'] =
                $validated['unit']
                ?? $validated['unit_of_measurement'];

            $validated['location'] =
                $validated['location']
                ?? $validated['storage_location']
                ?? null;

            $validated['status'] = $this->inventoryStatus(
                $targetStock,
                (int) $validated['reorder_level']
            );

            $inventoryItem->update($validated);

            $freshItem = $inventoryItem->fresh();

            $previousStock = (int) (
                $freshItem->on_hand
                ?? $freshItem->quantity_available
                ?? 0
            );

            if ($targetStock !== $previousStock) {
                $this->ledger->adjustTo(
                    $freshItem,
                    $targetStock,
                    $freshItem->item_code ?? $freshItem->item_name,
                    'Manual inventory adjustment.',
                    auth()->id()
                );
            }

            $this->createAutoRestockRequestIfNeeded(
                $freshItem
            );
        });

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'Inventory',
            'updated',
            $inventoryItem->id,
            'An inventory item was updated.'
        );

        session()->flash(
            'success',
            'Inventory item updated successfully.'
        );

        return new RedirectResponse('/inventory');
    }

    public function destroy(
        InventoryItem $inventoryItem
    ): RedirectResponse {
        $inventoryItem->delete();

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'Inventory',
            'deleted',
            $inventoryItem->id,
            'An inventory item was deleted.'
        );

        session()->flash(
            'success',
            'Inventory item deleted successfully.'
        );

        return new RedirectResponse('/inventory');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'inventory_file' => [
                'required',
                'file',
                'mimes:csv,txt',
            ],
        ]);

        $file = $request->file('inventory_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            session()->flash(
                'error',
                'Unable to read the uploaded file.'
            );

            return new RedirectResponse('/inventory');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            session()->flash(
                'error',
                'The uploaded CSV file is empty.'
            );

            return new RedirectResponse('/inventory');
        }

        $header = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            $header
        );

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $handle,
            $header,
            &$created,
            &$updated
        ) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($header) !== count($row)) {
                    continue;
                }

                $data = array_combine($header, $row);

                if (! $data) {
                    continue;
                }

                $itemCode = trim((string) ($data['item_code'] ?? ''));

                $partsName = trim((string) (
                    $data['parts_name']
                    ?? $data['item_name']
                    ?? ''
                ));

                if ($itemCode === '' && $partsName === '') {
                    continue;
                }

                $onHand = max(0, (int) (
                    $data['on_hand']
                    ?? $data['quantity_available']
                    ?? 0
                ));

                $reorderLevel = (int) (
                    $data['reorder_level']
                    ?? 0
                );

                $unit = trim((string) (
                    $data['unit']
                    ?? $data['unit_of_measurement']
                    ?? ''
                ));

                $location = trim((string) (
                    $data['location']
                    ?? $data['storage_location']
                    ?? ''
                ));

                $payload = [
                    'item_code' => $itemCode ?: null,
                    'parts_name' => $partsName ?: null,
                    'item_name' => $partsName ?: null,
                    'category' => trim((string) ($data['category'] ?? '')) ?: null,
                    'unit' => $unit ?: null,
                    'unit_of_measurement' => $unit ?: null,
                    'reorder_level' => $reorderLevel,
                    'supplier' => trim((string) ($data['supplier'] ?? '')) ?: null,
                    'location' => $location ?: null,
                    'storage_location' => $location ?: null,
                ];

                $inventoryItem = null;

                if ($itemCode !== '') {
                    $inventoryItem = InventoryItem::query()
                        ->where('item_code', $itemCode)
                        ->first();
                }

                if ($inventoryItem) {
                    $previousStock = (int) (
                        $inventoryItem->on_hand
                        ?? $inventoryItem->quantity_available
                        ?? 0
                    );

                    $inventoryItem->update($payload + [
                        'status' => $this->inventoryStatus(
                            $onHand,
                            $reorderLevel
                        ),
                    ]);

                    if ($onHand !== $previousStock) {
                        $this->ledger->adjustTo(
                            $inventoryItem->fresh(),
                            $onHand,
                            $inventoryItem->item_code ?? $inventoryItem->item_name,
                            'Inventory import stock reconciliation.',
                            auth()->id()
                        );
                    }

                    $updated++;
                } else {
                    $inventoryItem = InventoryItem::create($payload + [
                        'on_hand' => 0,
                        'quantity_available' => 0,
                        'status' => $this->inventoryStatus(
                            $onHand,
                            $reorderLevel
                        ),
                    ]);

                    if ($onHand > 0) {
                        $this->ledger->adjustTo(
                            $inventoryItem,
                            $onHand,
                            $inventoryItem->item_code ?? $inventoryItem->item_name,
                            'Opening balance on inventory import.',
                            auth()->id()
                        );
                    }

                    $created++;
                }

                $this->createAutoRestockRequestIfNeeded(
                    $inventoryItem->fresh()
                );
            }
        });

        fclose($handle);

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'Inventory',
            'updated',
            null,
            "Inventory import completed. Created: {$created}, Updated: {$updated}."
        );

        session()->flash(
            'success',
            "Inventory import completed. Created: {$created}, Updated: {$updated}."
        );

        return new RedirectResponse('/inventory');
    }

    public function issue(Request $request): RedirectResponse
    {
        $this->authorizeIssue();

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'issued_to' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:500'],
            'reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        $item = InventoryItem::findOrFail((int) $validated['inventory_item_id']);
        $quantity = (int) $validated['quantity'];
        $issueNo = '';

        DB::transaction(function () use ($item, $quantity, $validated, &$issueNo) {
            $current = (int) ($item->on_hand ?? $item->quantity_available ?? 0);

            if ($quantity > $current) {
                throw ValidationException::withMessages([
                    'quantity' =>
                        "Insufficient stock. Only {$current} available, but {$quantity} requested.",
                ]);
            }

            $issueNo = $this->generateIssueNo();

            $movement = $this->ledger->stockOut(
                $item,
                $quantity,
                $issueNo,
                'Issued to '
                    . trim((string) $validated['issued_to'])
                    . ' for '
                    . trim((string) $validated['purpose'])
                    . '.',
                auth()->id()
            );

            $issuance = InventoryIssuance::create([
                'issue_no' => $issueNo,
                'issued_to' => trim((string) $validated['issued_to']),
                'purpose' => trim((string) $validated['purpose']),
                'reference_no' => trim((string) (
                    $validated['reference_no'] ?? ''
                )) ?: null,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);

            InventoryIssuanceItem::create([
                'inventory_issuance_id' => $issuance->id,
                'inventory_item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->parts_name ?? $item->item_name ?? 'Inventory Item',
                'quantity' => $quantity,
                'unit' => $item->unit_of_measurement ?: $item->unit,
                'previous_stock' => $movement->previous_stock,
                'new_stock' => $movement->new_stock,
                'stock_movement_id' => $movement->id,
            ]);
        });

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'Inventory',
            'issued',
            $item->id,
            "Stock was issued (#{$issueNo})."
        );

        session()->flash(
            'success',
            "Stock issued successfully. Issuance No: {$issueNo}."
        );

        return new RedirectResponse('/inventory');
    }

    private function authorizeIssue(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'You are not authorized to issue inventory.');
        }

        $department = strtolower(trim((string) ($user->department ?? '')));
        $role = strtolower(trim((string) ($user->role ?? '')));

        $isSystemAdmin =
            ($department === 'admin' && $role === 'head')
            || $role === 'system admin';

        if ($isSystemAdmin || $department === 'warehouse') {
            return;
        }

        abort(403, 'Only Warehouse personnel can issue inventory stock.');
    }

    private function generateIssueNo(): string
    {
        $year = now()->format('Y');

        $latest = InventoryIssuance::query()
            ->where('issue_no', 'like', "ISS-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $lastNumber = $latest && $latest->issue_no
            ? (int) substr($latest->issue_no, -4)
            : 0;

        return 'ISS-'
            . $year
            . '-'
            . str_pad(
                (string) ($lastNumber + 1),
                4,
                '0',
                STR_PAD_LEFT
            );
    }

    private function syncAutoRestockRequests(): void
    {
        $lowStockItems = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->get();

        foreach ($lowStockItems as $inventoryItem) {
            $this->createAutoRestockRequestIfNeeded($inventoryItem);
        }
    }

    private function createAutoRestockRequestIfNeeded(
        InventoryItem $inventoryItem
    ): void {
        $itemName = $this->getInventoryItemName($inventoryItem);
        $unit = $this->getInventoryUnit($inventoryItem);

        $onHand = (int) (
            $inventoryItem->on_hand
            ?? $inventoryItem->quantity_available
            ?? 0
        );

        $reorderLevel = (int) (
            $inventoryItem->reorder_level
            ?? 0
        );

        if ($itemName === '' || $reorderLevel <= 0) {
            return;
        }

        if ($onHand > $reorderLevel) {
            return;
        }

        $existingActiveRequest = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->where(function ($q) use ($itemName) {
                $q->where('item', 'like', "{$itemName}%")
                    ->orWhere('remarks', 'like', "%{$itemName}%");
            })
            ->whereIn('status', [
                'For Purchase',
                'Ordered',
                'For Pick-up',
                'For Delivery',
                'Delivered',
                'Picked Up',
            ])
            ->exists();

        if ($existingActiveRequest) {
            return;
        }

        $neededQuantity = max(
            $reorderLevel - $onHand,
            1
        );

        MaintenanceRequest::create([
            'pr_no' => $this->generateRestockPrNo(),
            'job_order_no' => 'RESTOCK',
            'bus_no' => 'RESTOCK',
            'item' => "{$itemName} - Qty: {$neededQuantity} {$unit}",
            'quantity' => $neededQuantity,
            'status' => 'For Purchase',
            'source_type' => 'Auto Restock',
            'remarks' =>
                "Auto restock request from Warehouse Inventory. "
                . "{$itemName} is below reorder level. "
                . "Current stock: {$onHand} {$unit}. "
                . "Reorder level: {$reorderLevel} {$unit}.",
        ]);
    }

    private function generateRestockPrNo(): string
    {
        $year = now()->format('Y');

        $latest = MaintenanceRequest::query()
            ->where('pr_no', 'like', "RST-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $latest || ! $latest->pr_no) {
            return "RST-{$year}-0001";
        }

        $lastNumber = (int) substr($latest->pr_no, -4);
        $nextNumber = $lastNumber + 1;

        return "RST-{$year}-"
            . str_pad(
                (string) $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );
    }

    private function inventoryStatus(
        int $onHand,
        int $reorderLevel
    ): string {
        if ($onHand <= 0) {
            return 'Critical';
        }

        if (
            $reorderLevel > 0
            && $onHand <= $reorderLevel
        ) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    private function getInventoryItemName(
        InventoryItem $inventoryItem
    ): string {
        return trim((string) (
            $inventoryItem->parts_name
            ?? $inventoryItem->item_name
            ?? ''
        ));
    }

    private function getInventoryUnit(
        InventoryItem $inventoryItem
    ): string {
        return trim((string) (
            $inventoryItem->unit
            ?? $inventoryItem->unit_of_measurement
            ?? 'pcs'
        )) ?: 'pcs';
    }
}