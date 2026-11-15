<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Http\Request;

class WarehousePartRequestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Active statuses only
    |--------------------------------------------------------------------------
    | Issued is removed here because Issued should not appear in the active table.
    | Issued records will appear in the Issued Parts History table instead.
    */
    private array $statuses = [
        'Approved',
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
    ];

    /*
    |--------------------------------------------------------------------------
    | Warehouse Maintenance PR Base Query
    |--------------------------------------------------------------------------
    | This page must show Maintenance Job Order part requests only.
    | Inventory restock requests like RST-2026-0001 must NOT appear here.
    */
    private function warehouseMaintenanceRequestQuery()
    {
        return PurchaseRequest::query()
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->where(function ($q) {
                $q->whereNull('job_order_no')
                    ->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('bus_no')
                    ->orWhere('bus_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Purchase-side missing parts query
    |--------------------------------------------------------------------------
    | These are copied PRs created from unavailable Warehouse parts.
    | Restock copied/records must still be excluded.
    */
    private function missingPartsPurchaseQuery()
    {
        return PurchaseRequest::query()
            ->where('pr_no', 'like', '%-P%')
            ->where(function ($q) {
                $q->whereNull('job_order_no')
                    ->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('bus_no')
                    ->orWhere('bus_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            });
    }

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Active Part Requests
        |--------------------------------------------------------------------------
        | Show Maintenance PR only.
        | Hide:
        | - purchase-side copied PRs like PR-2026-0001-P
        | - inventory restock requests like RST-2026-0001
        | - RESTOCK job_order_no / bus_no
        |--------------------------------------------------------------------------
        */
        $query = $this->warehouseMaintenanceRequestQuery()
            ->whereIn('status', $this->statuses);

        if ($request->filled('search')) {
            $search = trim($request->search);

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
            return $this->prepareRequestForWarehouse($purchaseRequest);
        });

        /*
        |--------------------------------------------------------------------------
        | Issued History
        |--------------------------------------------------------------------------
        | Maintenance issued history only.
        |--------------------------------------------------------------------------
        */
        $issuedRequests = $this->warehouseMaintenanceRequestQuery()
            ->where('status', 'Issued')
            ->latest()
            ->paginate(5, ['*'], 'history_page')
            ->withQueryString();

        $issuedRequests->getCollection()->transform(function ($purchaseRequest) {
            return $this->prepareRequestForWarehouse($purchaseRequest);
        });

        /*
        |--------------------------------------------------------------------------
        | Summary Counts
        |--------------------------------------------------------------------------
        | Restock requests are excluded here too.
        |--------------------------------------------------------------------------
        */
        $approved = $this->warehouseMaintenanceRequestQuery()
            ->where('status', 'Approved')
            ->count();

        $forPurchase = $this->missingPartsPurchaseQuery()
            ->where('status', 'For Purchase')
            ->count();

        $ordered = $this->missingPartsPurchaseQuery()
            ->where('status', 'Ordered')
            ->count();

        $delivered = $this->missingPartsPurchaseQuery()
            ->whereIn('status', ['Delivered', 'Picked Up'])
            ->count();

        $issued = $this->warehouseMaintenanceRequestQuery()
            ->where('status', 'Issued')
            ->count();

        $statuses = $this->statuses;

        return view('Warehouse.part-requests', compact(
            'purchaseRequests',
            'issuedRequests',
            'approved',
            'forPurchase',
            'ordered',
            'delivered',
            'issued',
            'statuses'
        ));
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be issued from Warehouse Part Requests.');
        }

        $missingPurchaseRequest = $this->getMissingPurchaseRequest($purchaseRequest);
        $displayStatus = $missingPurchaseRequest?->status ?? $purchaseRequest->status;

        if (! in_array($displayStatus, ['Approved', 'Delivered', 'Picked Up'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Only approved, delivered, or picked up parts can be issued.');
        }

        $parts = $this->parseParts($purchaseRequest->item);
        $inventoryCheck = $this->checkInventoryAvailability($parts);

        if (! $inventoryCheck['available']) {
            return redirect()
                ->back()
                ->with('error', 'Cannot issue parts. One or more requested parts are not available in inventory yet.');
        }

        foreach ($parts as $part) {
            $inventoryItem = $this->findInventoryItem($part['name'], $part['unit'] ?? '');

            if ($inventoryItem) {
                $inventoryItem->update([
                    'quantity_available' => max(
                        0,
                        (int) $inventoryItem->quantity_available - (int) $part['quantity']
                    ),
                ]);
            }
        }

        $purchaseRequest->update([
            'status' => 'Issued',
        ]);

        if ($missingPurchaseRequest) {
            $missingPurchaseRequest->update([
                'status' => 'Issued',
            ]);
        }

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
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be sent from Warehouse Part Requests.');
        }

        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved purchase requests can be sent to purchasing department.');
        }

        if ($this->missingPurchaseRequestExists($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Missing parts were already sent to the Purchase Department.');
        }

        $parts = $this->parseParts($purchaseRequest->item);
        $inventoryCheck = $this->checkInventoryAvailability($parts);

        if ($inventoryCheck['available']) {
            return redirect()
                ->back()
                ->with('error', 'All requested parts are available. Please issue the parts instead.');
        }

        $missingParts = $inventoryCheck['missing'] ?? [];

        if (count($missingParts) === 0) {
            return redirect()
                ->back()
                ->with('error', 'No missing parts found to send to Purchase Department.');
        }

        $missingItemText = $this->buildPartsText($missingParts);
        $missingTotalQuantity = collect($missingParts)->sum('needed');
        $missingPrNo = $this->generateMissingPrNo($purchaseRequest->pr_no);

        PurchaseRequest::create([
            'pr_no' => $missingPrNo,
            'job_order_no' => $purchaseRequest->job_order_no,
            'bus_no' => $purchaseRequest->bus_no,
            'item' => $missingItemText,
            'quantity' => $missingTotalQuantity,
            'status' => 'For Purchase',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Missing parts from ' . $purchaseRequest->pr_no . '. Only unavailable parts were sent to Purchase Department.',
            'date_requested' => now(),
        ]);

        $oldRemarks = trim($purchaseRequest->remarks ?? '');

        $purchaseRequest->update([
            'remarks' => trim($oldRemarks . ' Missing parts sent to Purchase as ' . $missingPrNo . '.'),
        ]);

        JobOrder::where('job_order_no', $purchaseRequest->job_order_no)
            ->update([
                'part_status' => 'For Purchase',
            ]);

        return redirect()
            ->back()
            ->with('success', 'Only unavailable parts were sent to Purchase Department.');
    }

    private function prepareRequestForWarehouse(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $parts = $this->parseParts($purchaseRequest->item);
        $inventoryCheck = $this->checkInventoryAvailability($parts);

        $firstPart = $inventoryCheck['breakdown'][0] ?? null;
        $inventoryLabel = $this->getOverallInventoryLabel($inventoryCheck);

        $missingPurchaseRequest = $this->getMissingPurchaseRequest($purchaseRequest);
        $missingPrAlreadyCreated = $missingPurchaseRequest !== null;

        $warehouseDisplayStatus = $purchaseRequest->status;

        if ($missingPurchaseRequest) {
            $warehouseDisplayStatus = $missingPurchaseRequest->status;
        }

        $purchaseRequest->parts_breakdown = $inventoryCheck['breakdown'];
        $purchaseRequest->inventory_check = $inventoryCheck;

        $purchaseRequest->first_item_display = $firstPart['name'] ?? $purchaseRequest->item ?? '—';
        $purchaseRequest->first_quantity_display = $firstPart['needed_display'] ?? '0';
        $purchaseRequest->first_on_hand_display = $firstPart['available_display'] ?? '0';

        $purchaseRequest->inventory_label = $inventoryLabel;
        $purchaseRequest->first_inventory_status = $inventoryLabel;
        $purchaseRequest->on_hand_available = $inventoryCheck['total_on_hand'];

        $purchaseRequest->missing_pr_already_created = $missingPrAlreadyCreated;
        $purchaseRequest->missing_purchase_request = $missingPurchaseRequest;
        $purchaseRequest->purchase_progress_status = $warehouseDisplayStatus;

        $purchaseRequest->can_issue =
            $purchaseRequest->status !== 'Issued'
            && $inventoryCheck['available']
            && (
                $purchaseRequest->status === 'Approved'
                || in_array($warehouseDisplayStatus, ['Delivered', 'Picked Up'], true)
            );

        $purchaseRequest->needs_purchase =
            $purchaseRequest->status === 'Approved'
            && ! $inventoryCheck['available']
            && ! $missingPrAlreadyCreated;

        return $purchaseRequest;
    }

    private function getMissingPurchaseRequest(PurchaseRequest $purchaseRequest): ?PurchaseRequest
    {
        if (! $purchaseRequest->pr_no) {
            return null;
        }

        return PurchaseRequest::query()
            ->where('job_order_no', $purchaseRequest->job_order_no)
            ->where(function ($q) {
                $q->whereNull('bus_no')
                    ->orWhere('bus_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            })
            ->where('pr_no', 'like', $purchaseRequest->pr_no . '-P%')
            ->latest()
            ->first();
    }

    private function missingPurchaseRequestExists(PurchaseRequest $purchaseRequest): bool
    {
        return $this->getMissingPurchaseRequest($purchaseRequest) !== null;
    }

    private function isRestockRequest(PurchaseRequest $purchaseRequest): bool
    {
        return str_starts_with(strtoupper(trim($purchaseRequest->pr_no ?? '')), 'RST-')
            || strtoupper(trim($purchaseRequest->job_order_no ?? '')) === 'RESTOCK'
            || strtoupper(trim($purchaseRequest->bus_no ?? '')) === 'RESTOCK'
            || strtolower(trim($purchaseRequest->source_type ?? '')) === 'inventory restock';
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

                    $name = $this->cleanPartName($name);
                    $quantityWithUnit = trim($quantityWithUnit ?? '');

                    preg_match('/^(\d+)\s*(.*)$/', $quantityWithUnit, $matches);

                    $quantity = isset($matches[1]) ? (int) $matches[1] : 1;
                    $unit = isset($matches[2]) ? $this->normalizeUnit($matches[2]) : '';

                    return [
                        'name' => $name,
                        'quantity' => max(1, $quantity),
                        'unit' => $unit,
                        'needed_display' => trim(max(1, $quantity) . ($unit ? ' ' . $unit : '')),
                    ];
                }

                if (preg_match('/^(.*?)\s*\((\d+)\s*([^)]+)\)$/', $part, $matches)) {
                    $name = $this->cleanPartName($matches[1] ?? '');
                    $quantity = isset($matches[2]) ? (int) $matches[2] : 1;
                    $unit = isset($matches[3]) ? $this->normalizeUnit($matches[3]) : '';

                    return [
                        'name' => $name,
                        'quantity' => max(1, $quantity),
                        'unit' => $unit,
                        'needed_display' => trim(max(1, $quantity) . ($unit ? ' ' . $unit : '')),
                    ];
                }

                return [
                    'name' => $this->cleanPartName($part),
                    'quantity' => 1,
                    'unit' => '',
                    'needed_display' => '1',
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
        $availablePartCount = 0;

        foreach ($parts as $part) {
            $name = $part['name'] ?? '';
            $unit = $part['unit'] ?? '';

            $inventoryItem = $this->findInventoryItem($name, $unit);

            $neededQty = (int) ($part['quantity'] ?? 1);
            $availableQty = $inventoryItem ? (int) $inventoryItem->quantity_available : 0;

            $inventoryUnit = $inventoryItem
                ? $this->normalizeUnit($inventoryItem->unit_of_measurement)
                : $this->normalizeUnit($unit);

            $isAvailable = $inventoryItem && $availableQty >= $neededQty;

            if ($isAvailable) {
                $availablePartCount++;
            }

            $totalNeeded += $neededQty;
            $totalOnHand += $availableQty;

            $partStatus = $isAvailable ? 'Available' : 'Not Available';

            $breakdown[] = [
                'name' => $name,
                'needed' => $neededQty,
                'unit' => $inventoryUnit,
                'needed_display' => trim($neededQty . ($inventoryUnit ? ' ' . $inventoryUnit : '')),
                'available' => $availableQty,
                'available_display' => trim($availableQty . ($inventoryUnit ? ' ' . $inventoryUnit : '')),
                'status' => $partStatus,
                'matched_inventory_id' => $inventoryItem?->id,
            ];

            if (! $isAvailable) {
                $missing[] = [
                    'name' => $name,
                    'needed' => $neededQty,
                    'unit' => $inventoryUnit,
                    'needed_display' => trim($neededQty . ($inventoryUnit ? ' ' . $inventoryUnit : '')),
                    'available' => $availableQty,
                    'available_display' => trim($availableQty . ($inventoryUnit ? ' ' . $inventoryUnit : '')),
                    'status' => 'Not Available',
                ];
            }
        }

        return [
            'available' => count($parts) > 0 && count($missing) === 0,
            'missing' => $missing,
            'breakdown' => $breakdown,
            'total_needed' => $totalNeeded,
            'total_on_hand' => $totalOnHand,
            'available_part_count' => $availablePartCount,
            'total_part_count' => count($parts),
        ];
    }

    private function getOverallInventoryLabel(array $inventoryCheck): string
    {
        $totalPartCount = (int) ($inventoryCheck['total_part_count'] ?? 0);
        $availablePartCount = (int) ($inventoryCheck['available_part_count'] ?? 0);

        if ($totalPartCount <= 0) {
            return 'Not Available';
        }

        if ($availablePartCount === $totalPartCount) {
            return 'Available';
        }

        if ($availablePartCount > 0) {
            return 'Not Fully Available';
        }

        return 'Not Available';
    }

    private function findInventoryItem(string $partName, ?string $unit = null): ?InventoryItem
    {
        $partName = $this->normalizeText($partName);
        $unit = $this->normalizeUnit($unit);

        if ($partName === '') {
            return null;
        }

        $query = InventoryItem::query()
            ->where(function ($q) use ($partName) {
                $q->whereRaw('LOWER(TRIM(item_name)) = ?', [$partName])
                    ->orWhereRaw('LOWER(TRIM(item_code)) = ?', [$partName]);
            });

        if ($unit !== '') {
            $query->whereRaw('LOWER(TRIM(unit_of_measurement)) = ?', [$unit]);
        }

        $item = $query->first();

        if ($item) {
            return $item;
        }

        $item = InventoryItem::query()
            ->where(function ($q) use ($partName) {
                $q->whereRaw('LOWER(TRIM(item_name)) = ?', [$partName])
                    ->orWhereRaw('LOWER(TRIM(item_code)) = ?', [$partName]);
            })
            ->first();

        if ($item) {
            return $item;
        }

        return InventoryItem::query()
            ->whereRaw('LOWER(TRIM(item_name)) LIKE ?', ["%{$partName}%"])
            ->first();
    }

    private function buildPartsText(array $parts): string
    {
        return collect($parts)
            ->map(function ($part) {
                $name = trim($part['name'] ?? '');
                $qty = (int) ($part['needed'] ?? 1);
                $unit = $this->normalizeUnit($part['unit'] ?? '');

                return $name . ' - Qty: ' . $qty . ($unit ? ' ' . $unit : '');
            })
            ->implode(', ');
    }

    private function generateMissingPrNo(string $originalPrNo): string
    {
        $base = $originalPrNo . '-P';
        $prNo = $base;
        $counter = 2;

        while (PurchaseRequest::where('pr_no', $prNo)->exists()) {
            $prNo = $base . $counter;
            $counter++;
        }

        return $prNo;
    }

    private function cleanPartName(?string $name): string
    {
        $name = trim($name ?? '');
        $name = preg_replace('/\s*\(\d+\s*[^)]*\)$/', '', $name);

        return trim($name);
    }

    private function normalizeText(?string $value): string
    {
        $value = strtolower(trim($value ?? ''));
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function normalizeUnit(?string $unit): string
    {
        $unit = strtolower(trim($unit ?? ''));
        $unit = preg_replace('/\s+/', ' ', $unit);

        return match ($unit) {
            'liter', 'liters', 'litre', 'litres', 'ltr', 'ltrs', 'l' => 'liter',
            'piece', 'pieces', 'pc', 'pcs' => 'pcs',
            'set', 'sets' => 'set',
            'bottle', 'bottles' => 'bottle',
            'box', 'boxes' => 'box',
            'pack', 'packs' => 'pack',
            'pair', 'pairs' => 'pair',
            'roll', 'rolls' => 'roll',
            'tube', 'tubes' => 'tube',
            'gallon', 'gallons', 'gal' => 'gallon',
            'meter', 'meters', 'm' => 'meter',
            'kg', 'kilogram', 'kilograms' => 'kg',
            default => $unit,
        };
    }
}