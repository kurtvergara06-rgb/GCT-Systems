<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryLedgerService;
use App\Support\TableSort;
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
        $inventoryScope = static fn () => InventoryItem::query();

        $query = $inventoryScope();

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

        TableSort::apply($query, $request, ['item_name' => 'item_name', 'item_code' => 'item_code', 'category' => 'category', 'quantity' => 'quantity_available'], 'item_name');
        $inventoryItems = $query
            ->paginate(8)
            ->withQueryString();

        // Auto-calculated Reorder Point is read-only and derived from signed
        // issuances. Historical gaps retain the existing configured threshold.
        $calculator = app(\App\Services\Warehouse\InventoryThresholdCalculator::class);
        $calculatedThresholds = $inventoryItems->getCollection()
            ->mapWithKeys(fn (InventoryItem $item) => [
                $item->id => $calculator->calculate($item),
            ]);

        // Actual stock stays unchanged on JO reservation. Show committed and free balances separately.
        $reservedByItem = \App\Models\Maintenance\JobOrderPartReservation::query()
            ->whereIn('inventory_item_id', $inventoryItems->getCollection()->pluck('id'))
            ->select('inventory_item_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as reserved'))
            ->groupBy('inventory_item_id')
            ->pluck('reserved', 'inventory_item_id');

        $categories = $inventoryScope()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $suppliers = $inventoryScope()->whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->distinct()->orderBy('supplier')->pluck('supplier');

        $totalItemsInStock = $inventoryScope()->count();

        // Count against usable stock, not physical on-hand stock. A reservation
        // does not deduct inventory until a signed Warehouse issuance occurs.
        $reservations = \App\Models\Maintenance\JobOrderPartReservation::query()
            ->select('inventory_item_id', DB::raw('SUM(quantity) AS reserved'))
            ->groupBy('inventory_item_id');

        $lowStockAlerts = 0;
        $criticalItems = 0;
        $itemsAtRisk = 0;
        [$periodStart, $periodEnd] = $calculator->completeMonthWindow();
        $signedUsage = DB::table('inventory_issuance_items as lines')
            ->join('inventory_issuances as issues', 'issues.id', '=', 'lines.inventory_issuance_id')
            ->whereNotNull('issues.handover_confirmed_at')
            ->whereNotNull('issues.signed_slip_path')
            ->where('issues.issued_at', '>=', $periodStart)
            ->where('issues.issued_at', '<', $periodEnd)
            ->select('lines.inventory_item_id', DB::raw('SUM(lines.quantity) AS issued_units'))
            ->groupBy('lines.inventory_item_id');

        foreach ($inventoryScope()
            ->leftJoinSub($reservations, 'jo_reservations', 'inventory_items.id', '=', 'jo_reservations.inventory_item_id')
            ->leftJoinSub($signedUsage, 'signed_usage', 'inventory_items.id', '=', 'signed_usage.inventory_item_id')
            ->select('inventory_items.quantity_available', 'inventory_items.reorder_level',
                'inventory_items.created_at', 'jo_reservations.reserved', 'signed_usage.issued_units')
            ->cursor() as $stock) {
            $available = max(0, (int) $stock->quantity_available - max(0, (int) $stock->reserved));
            $issued = (int) $stock->issued_units;
            $completeHistory = $calculator->hasEnoughHistory(
                $stock->created_at !== null ? \Carbon\CarbonImmutable::parse($stock->created_at) : null,
                $periodStart,
                $issued
            );
            $minimum = $completeHistory ? $calculator->roundedMonthlyUsage($issued) : (int) $stock->reorder_level;
            $status = $calculator->stockStatus((int) $stock->quantity_available, (int) $stock->reserved, $minimum > 0 ? $minimum : null);
            if ($status === 'Critical') {
                $criticalItems++;
                $itemsAtRisk++;
            } elseif ($status === 'Low Stock') {
                $lowStockAlerts++;
                $itemsAtRisk++;
            }
        }
        $issueItems = InventoryItem::query()
            ->where('quantity_available', '>', 0)
            ->orderBy('item_name')
            ->get();

        return view('Warehouse.inventory', compact(
            'inventoryItems',
            'reservedByItem',
            'calculatedThresholds',
            'calculator',
            'categories',
            'suppliers',
            'totalItemsInStock',
            'lowStockAlerts',
            'criticalItems',
            'itemsAtRisk',
            'issueItems'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWarehouseEdit();

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
            'reorder_level' => ['prohibited'],
            'safety_stock' => ['prohibited'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'adjustment_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'item_code.unique' => 'The item code already exists. Please use a different item code.',
        ]);

        $inventoryItem = null;
        $initialStock = (int) $validated['on_hand'];
        $openingBalanceNote = trim((string) ($validated['adjustment_reason'] ?? ''));

        unset($validated['on_hand'], $validated['quantity_available'], $validated['adjustment_reason']);

        DB::transaction(function () use ($validated, $initialStock, $openingBalanceNote, &$inventoryItem) {
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

            // No manual threshold: zero is a storage fallback until history qualifies.
            // The Inventory UI labels this as pending, not an auto-calculated zero.
            $validated['reorder_level'] = 0;
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
                    $openingBalanceNote !== '' ? $openingBalanceNote : 'Opening balance on item creation.',
                    auth()->id()
                );
            }

            // Low-stock items remain visible in Inventory alerts; a Warehouse Head must authorize the restock PR.
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
        $this->authorizeWarehouseEdit();

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'reorder_level' => ['prohibited'],
            'safety_stock' => ['prohibited'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $inventoryItem): void {
            $lockedItem = InventoryItem::query()
                ->whereKey($inventoryItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Item edits never adjust physical inventory or overwrite immutable
            // identifiers. All quantity changes must go through the ledger.
            $validated['parts_name'] = $validated['item_name'];
            $validated['location'] = $validated['storage_location'] ?? null;
            $validated['status'] = $this->inventoryStatus(
                (int) ($lockedItem->quantity_available ?? $lockedItem->on_hand ?? 0),
                (int) $lockedItem->reorder_level
            );
            $lockedItem->update($validated);
            // Editing thresholds does not create an unsigned purchase request.
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
        $this->authorizeWarehouseEdit();

        $deleted = DB::transaction(function () use ($inventoryItem): bool {
            $lockedItem = InventoryItem::query()
                ->whereKey($inventoryItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->movements()->exists()) {
                return false;
            }

            return (bool) $lockedItem->delete();
        });

        if (! $deleted) {
            return redirect('/inventory')->with(
                'error',
                'Inventory items with stock movement history cannot be deleted. Update the item instead.'
            );
        }

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
        $this->authorizeWarehouseEdit();

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

                $rawOnHand = $data['on_hand'] ?? $data['quantity_available'] ?? 0;
                $onHand = filter_var($rawOnHand, FILTER_VALIDATE_INT);

                if ($onHand === false || $onHand < 0) {
                    throw ValidationException::withMessages([
                        'inventory_file' => "Invalid negative or non-integer quantity for {$itemCode}.",
                    ]);
                }

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
                    'supplier' => trim((string) ($data['supplier'] ?? '')) ?: null,
                    'location' => $location ?: null,
                    'storage_location' => $location ?: null,
                ];

                $inventoryItem = null;

                if ($itemCode !== '') {
                    $inventoryItem = InventoryItem::query()
                        ->where('item_code', $itemCode)
                        ->lockForUpdate()
                        ->first();
                }

                if ($inventoryItem) {
                    $previousStock = (int) (
                        $inventoryItem->quantity_available
                ?? $inventoryItem->on_hand
                        ?? 0
                    );

                    // Imports must not override either legacy or computed reorder points.
                    $inventoryItem->update($payload + [
                        'status' => $this->inventoryStatus($onHand, (int) $inventoryItem->reorder_level),
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
                        'reorder_level' => 0,
                        'status' => $this->inventoryStatus($onHand, 0),
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

                // Imported low-stock inventory is flagged in the dashboard, not auto-purchased.
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
        $this->authorizeWarehouseEdit();

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'issued_to' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:500'],
            'reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = (int) $validated['quantity'];
        $issueNo = '';
        $itemId = (int) $validated['inventory_item_id'];

        DB::transaction(function () use ($itemId, $quantity, $validated, &$issueNo) {
            $item = InventoryItem::query()
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();
            $current = (int) ($item->quantity_available ?? $item->on_hand ?? 0);

            if ($quantity > $current) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock. Only {$current} available, but {$quantity} requested.",
                ]);
            }

            $issueNo = $this->generateIssueNo();

            $movement = $this->ledger->stockOut(
                $item,
                $quantity,
                $issueNo,
                'Issued to '
                    .trim((string) $validated['issued_to'])
                    .' for '
                    .trim((string) $validated['purpose'])
                    .'.',
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
            $itemId,
            "Stock was issued (#{$issueNo})."
        );

        session()->flash(
            'success',
            "Stock issued successfully. Issuance No: {$issueNo}."
        );

        return new RedirectResponse('/inventory');
    }

    private function authorizeWarehouseEdit(): void
    {
        abort_unless(
            auth()->user()?->hasSystemPermission('warehouse', 'edit') ?? false,
            403,
            'Your role does not have permission to edit warehouse records.'
        );
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
            .$year
            .'-'
            .str_pad(
                (string) ($lastNumber + 1),
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
