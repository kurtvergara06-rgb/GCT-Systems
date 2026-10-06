<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryLedgerService;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehousePartRequestController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private InventoryLedgerService $ledger;

    public function __construct(InventoryLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

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

    private array $historyStatuses = [
        'Issued',
        'Rejected',
        'Cancelled',
        'Completed',
    ];

    /*
    |--------------------------------------------------------------------------
    | Warehouse Maintenance PR Base Query
    |--------------------------------------------------------------------------
    | This page must show Maintenance Job Order part requests only.
    | Inventory restock requests like RST-2026-0001 must NOT appear here.
    |
    | Missing-parts copies are identified by the controlled remarks marker
    | written by sendToPurchase(). Do not classify them by a broad "%-P%"
    | pattern because legitimate PR numbers such as DEMO-PR-0001 contain "-P".
    */
    private function warehouseMaintenanceRequestQuery()
    {
        return PurchaseRequest::query()
            ->where(function ($q) {
                $q->whereNull('remarks')
                    ->orWhere('remarks', 'not like', 'Missing parts from %');
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
            ->where('remarks', 'like', 'Missing parts from %')
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
        $currentView = strtolower(trim((string) $request->input('view', 'active')));
        if (! in_array($currentView, ['active', 'history'], true)) {
            $currentView = 'active';
        }

        $statusOptions = $currentView === 'history'
            ? $this->historyStatuses
            : $this->statuses;

        $query = $this->warehouseMaintenanceRequestQuery()
            ->whereIn('status', $statusOptions);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

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

        $activeCount = $this->warehouseMaintenanceRequestQuery()
            ->whereIn('status', $this->statuses)
            ->count();

        $historyCount = $this->warehouseMaintenanceRequestQuery()
            ->whereIn('status', $this->historyStatuses)
            ->count();

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

        return view('Warehouse.part-requests', compact(
            'purchaseRequests',
            'approved',
            'forPurchase',
            'ordered',
            'delivered',
            'issued',
            'currentView',
            'statusOptions',
            'activeCount',
            'historyCount'
        ));
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseCapability('edit');

        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be issued from Warehouse Part Requests.');
        }

        $parts = $this->parseParts($purchaseRequest->item);

        if (empty($parts)) {
            return redirect()
                ->back()
                ->with('error', 'No requested parts found on this requisition.');
        }

        $issuedQuantities = [];
        $issueDetails = [];

        foreach ($parts as $index => $part) {
            $requested = (int) ($part['quantity'] ?? 1);
            $actual = $requested; // Requested Quantity = Issued Quantity

            $issuedQuantities[$index] = $actual;
            $issueDetails[] = [
                'name' => $part['name'],
                'requested' => $requested,
                'issued' => $actual,
                'unit' => $part['unit'] ?? '',
            ];
        }

        DB::transaction(function () use ($purchaseRequest, $parts, $issuedQuantities, $issueDetails) {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($this->isRestockRequest($lockedRequest)
                || $lockedRequest->status === 'Issued'
                || $lockedRequest->warehouse_status === 'Issued') {
                throw ValidationException::withMessages([
                    'workflow' => 'This request has already been issued or cannot be issued from Warehouse Part Requests.',
                ]);
            }

            $missingPurchaseRequest = $this->getMissingPurchaseRequest($lockedRequest);
            $isPurchaseFlowReady = $missingPurchaseRequest
                ? in_array($missingPurchaseRequest->status, ['Delivered', 'Picked Up'], true)
                : $lockedRequest->status === 'Approved';

            if (! $isPurchaseFlowReady) {
                throw ValidationException::withMessages([
                    'workflow' => 'This request is not ready for issuance yet. Complete the Purchase and Warehouse receipt flow first.',
                ]);
            }

            $issuance = InventoryIssuance::create([
                'issue_no' => $this->generateIssueNo(),
                'issued_to' => 'Maintenance',
                'purpose' => 'Parts issued for '.$lockedRequest->job_order_no,
                'reference_no' => $lockedRequest->pr_no,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);

            foreach ($parts as $index => $part) {
                $matchedItem = $this->findInventoryItem($part['name'], $part['unit'] ?? '');
                $inventoryItem = $matchedItem
                    ? InventoryItem::query()->whereKey($matchedItem->id)->lockForUpdate()->first()
                    : null;
                $actual = (int) $issuedQuantities[$index];

                if (! $inventoryItem || (int) $inventoryItem->quantity_available < $actual) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for {$part['name']}.",
                    ]);
                }

                $movement = $this->ledger->stockOut(
                    $inventoryItem,
                    $actual,
                    $lockedRequest->pr_no ?? $issuance->issue_no,
                    'Issued through Warehouse Part Request '.$lockedRequest->pr_no.'.',
                    auth()->id()
                );

                InventoryIssuanceItem::create([
                    'inventory_issuance_id' => $issuance->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'item_code' => $inventoryItem->item_code,
                    'item_name' => $inventoryItem->parts_name ?? $inventoryItem->item_name ?? 'Inventory Item',
                    'quantity' => $actual,
                    'unit' => $inventoryItem->unit_of_measurement ?: $inventoryItem->unit,
                    'previous_stock' => $movement->previous_stock,
                    'new_stock' => $movement->new_stock,
                    'stock_movement_id' => $movement->id,
                ]);
            }

            $lockedRequest->update([
                'status' => 'Issued',
                'warehouse_status' => 'Issued',
                'warehouse_issue_quantities' => $issueDetails,
                'issued_at' => now(),
            ]);

            if ($missingPurchaseRequest) {
                PurchaseRequest::query()->whereKey($missingPurchaseRequest->id)->lockForUpdate()->firstOrFail()->update([
                    'status' => 'Issued',
                    'warehouse_status' => 'Issued',
                    'warehouse_issue_quantities' => $issueDetails,
                    'issued_at' => now(),
                ]);
            }

            $hasOutstandingRequest = $this->warehouseMaintenanceRequestQuery()
                ->where('job_order_no', $lockedRequest->job_order_no)
                ->where('id', '!=', $lockedRequest->id)
                ->where('status', '!=', 'Issued')
                ->exists();

            if (! $hasOutstandingRequest) {
                JobOrder::where('job_order_no', $lockedRequest->job_order_no)
                    ->update(['part_status' => 'Issued']);
            }
        });

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'Warehouse issued a purchase request.'
        );

        return redirect()
            ->back()
            ->with('success', 'Parts issued successfully.');
    }

    public function approveForIssue(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseCapability('approve');

        $error = DB::transaction(function () use ($purchaseRequest): ?string {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($this->isRestockRequest($lockedRequest) || $lockedRequest->status === 'Issued') {
                return 'This request cannot be approved for issuance.';
            }

            $displayStatus = $this->getMissingPurchaseRequest($lockedRequest)?->status ?? $lockedRequest->status;
            $inventoryCheck = $this->checkInventoryAvailability($this->parseParts($lockedRequest->item));

            if (! in_array($displayStatus, ['Approved', 'Delivered', 'Picked Up'], true) || ! $inventoryCheck['available']) {
                return 'All requested parts must be available before issuance approval.';
            }

            $lockedRequest->update([
                'warehouse_status' => 'Approved for Issue',
                'warehouse_approved_by' => auth()->id(),
                'warehouse_approved_at' => now(),
                'warehouse_prepared_by' => null,
                'warehouse_prepared_at' => null,
            ]);

            return null;
        });

        if ($error) {
            return redirect()->back()->with('error', $error);
        }

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'approved_for_issue',
            $purchaseRequest->id,
            'Warehouse approved a part request for issuance.'
        );

        return redirect()->back()->with('success', 'Part issuance approved. An authorized Warehouse user may now prepare the request.');
    }

    public function hold(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseCapability('approve');

        $error = DB::transaction(function () use ($purchaseRequest): ?string {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($lockedRequest->status === 'Issued' || $lockedRequest->warehouse_status === 'Issued') {
                return 'Issued requests cannot be placed on hold.';
            }

            $lockedRequest->update([
                'warehouse_status' => 'On Hold',
                'warehouse_prepared_by' => null,
                'warehouse_prepared_at' => null,
            ]);

            return null;
        });

        if ($error) {
            return redirect()->back()->with('error', $error);
        }

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'placed_on_hold',
            $purchaseRequest->id,
            'Warehouse placed a part request on hold.'
        );

        return redirect()->back()->with('success', 'Part issuance placed on hold.');
    }

    public function prepare(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseCapability('edit');

        $error = DB::transaction(function () use ($purchaseRequest): ?string {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($this->isRestockRequest($lockedRequest) || $lockedRequest->status === 'Issued') {
                return 'This request cannot be prepared.';
            }

            if ($lockedRequest->warehouse_status === 'Preparing') {
                return 'This request is already being prepared.';
            }

            $displayStatus = $this->getMissingPurchaseRequest($lockedRequest)?->status ?? $lockedRequest->status;
            if (! in_array($displayStatus, ['Approved', 'Delivered', 'Picked Up'], true)) {
                return 'This request is not ready for Warehouse preparation yet.';
            }

            $inventoryCheck = $this->checkInventoryAvailability($this->parseParts($lockedRequest->item));

            if (! $inventoryCheck['available']) {
                return 'Stock is not sufficient yet. Send missing parts to Purchase or wait for delivery.';
            }

            $lockedRequest->update([
                'warehouse_status' => 'Preparing',
                'warehouse_prepared_by' => auth()->id(),
                'warehouse_prepared_at' => now(),
            ]);

            return null;
        });

        if ($error) {
            return redirect()->back()->with('error', $error);
        }

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'preparing',
            $purchaseRequest->id,
            'Warehouse started preparing a part request.'
        );

        return redirect()->back()->with('success', 'Parts marked as preparing. Record actual quantities when issuing.');
    }

    public function sendToPurchase(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseCapability('approve');

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

        $failure = null;
        $missingPurchaseRequest = DB::transaction(function () use ($purchaseRequest, $missingParts, &$failure) {
            $lockedRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

            if ($lockedRequest->status !== 'Approved') {
                $failure = 'Only approved purchase requests can be sent to purchasing department.';

                return null;
            }

            if ($this->missingPurchaseRequestExists($lockedRequest)) {
                $failure = 'Missing parts were already sent to the Purchase Department.';

                return null;
            }

            $missingItemText = $this->buildPartsText($missingParts);
            $missingTotalQuantity = collect($missingParts)->sum('needed');
            $missingPrNo = $this->generateMissingPrNo($lockedRequest->pr_no);

            $createdRequest = PurchaseRequest::create([
                'pr_no' => $missingPrNo,
                'job_order_no' => $lockedRequest->job_order_no,
                'bus_no' => $lockedRequest->bus_no,
                'item' => $missingItemText,
                'quantity' => $missingTotalQuantity,
                'status' => 'For Purchase',
                'source_type' => 'Maintenance Request',
                'remarks' => 'Missing parts from '.$lockedRequest->pr_no.'. Only unavailable parts were sent to Purchase Department.',
                'date_requested' => now(),
            ]);

            $oldRemarks = trim($lockedRequest->remarks ?? '');
            $lockedRequest->update([
                'remarks' => trim($oldRemarks.' Missing parts sent to Purchase as '.$missingPrNo.'.'),
            ]);

            JobOrder::where('job_order_no', $lockedRequest->job_order_no)
                ->update(['part_status' => 'For Purchase']);

            return $createdRequest;
        });

        if (! $missingPurchaseRequest) {
            return redirect()->back()->with('error', $failure ?? 'Unable to send missing parts to Purchase.');
        }

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'created',
            $missingPurchaseRequest->id,
            'Warehouse sent missing parts to Purchase Department.'
        );

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'sent_to_purchase',
            $purchaseRequest->id,
            'Warehouse sent unavailable parts to Purchase.'
        );

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

        $isPurchaseFlowReady = $missingPrAlreadyCreated
            ? in_array($warehouseDisplayStatus, ['Delivered', 'Picked Up'], true)
            : $purchaseRequest->status === 'Approved';

        if ($purchaseRequest->status === 'Issued') {
            $workflowStatus = 'Issued';
        } elseif ($missingPrAlreadyCreated && $warehouseDisplayStatus === 'For Purchase') {
            $workflowStatus = 'Waiting for Purchase';
        } elseif ($missingPrAlreadyCreated && in_array($warehouseDisplayStatus, ['Ordered', 'For Pick-up', 'For Delivery'], true)) {
            $workflowStatus = 'Waiting for Delivery';
        } elseif ($inventoryCheck['available'] && $isPurchaseFlowReady) {
            $workflowStatus = 'Ready to Issue';
        } elseif (! $inventoryCheck['available'] && ! $missingPrAlreadyCreated) {
            $workflowStatus = 'Needs Purchase';
        } else {
            $workflowStatus = 'Waiting for Stock';
        }

        $purchaseRequest->warehouse_workflow_status = $workflowStatus;

        // Maintenance already approved the request. Warehouse has no second
        // approval/hold/prepare gate; it either routes missing stock to Purchase
        // or issues directly once the complete stock is physically available.
        $purchaseRequest->can_approve_for_issue = false;
        $purchaseRequest->can_hold = false;
        $purchaseRequest->can_prepare = false;

        $purchaseRequest->can_issue =
            $purchaseRequest->status !== 'Issued'
            && $inventoryCheck['available']
            && $isPurchaseFlowReady;

        $purchaseRequest->needs_purchase =
            $purchaseRequest->status === 'Approved'
            && ! $inventoryCheck['available']
            && ! $missingPrAlreadyCreated;

        return $purchaseRequest;
    }

    private function authorizeWarehouseCapability(string $capability): void
    {
        abort_unless(
            auth()->user()?->hasSystemPermission('warehouse', $capability) ?? false,
            403,
            "Your role does not have permission to {$capability} warehouse records."
        );
    }

    private function generateIssueNo(): string
    {
        $year = now()->format('Y');
        $latest = InventoryIssuance::query()
            ->where('issue_no', 'like', "ISS-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();
        $lastNumber = $latest?->issue_no ? (int) substr($latest->issue_no, -4) : 0;

        return 'ISS-'.$year.'-'.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
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
