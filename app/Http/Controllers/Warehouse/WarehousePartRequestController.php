<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
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
        /*
        |--------------------------------------------------------------------------
        | Active Part Requests
        |--------------------------------------------------------------------------
        | Show Maintenance PR only.
        | Hide:
        | - purchase-side copied PRs created for missing Warehouse parts
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

    public function issue(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseRole('staff');

        if ($this->isRestockRequest($purchaseRequest)) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be issued from Warehouse Part Requests.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be issued from Warehouse Part Requests.');
        }

        if ($purchaseRequest->warehouse_status !== 'Preparing') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only requests prepared by Warehouse Staff can be issued.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Only requests prepared by Warehouse Staff can be issued.');
        }

        $parts = $this->parseParts($purchaseRequest->item);

        if (empty($parts)) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'No requested parts found on this requisition.'], 422);
            }
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

            if ($lockedRequest->warehouse_status !== 'Preparing') {
                throw ValidationException::withMessages([
                    'workflow' => 'This request is no longer ready for issuance.',
                ]);
            }

            foreach ($parts as $index => $part) {
                $inventoryItem = $this->findInventoryItem($part['name'], $part['unit'] ?? '');
                $actual = (int) $issuedQuantities[$index];

                if (! $inventoryItem || (int) $inventoryItem->quantity_available < $actual) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for {$part['name']}.",
                    ]);
                }

                $this->ledger->stockOut(
                    $inventoryItem,
                    $actual,
                    $lockedRequest->pr_no ?? (string) $lockedRequest->id,
                    'Issued through Warehouse Part Request.',
                    auth()->id()
                );
            }

            $lockedRequest->update([
                'status' => 'Issued',
                'warehouse_status' => 'Issued',
                'warehouse_issue_quantities' => $issueDetails,
                'issued_at' => now(),
            ]);

            $missingPurchaseRequest = $this->getMissingPurchaseRequest($lockedRequest);

            if ($missingPurchaseRequest) {
                $missingPurchaseRequest->update([
                    'status' => 'Issued',
                    'warehouse_status' => 'Issued',
                    'warehouse_issue_quantities' => $issueDetails,
                    'issued_at' => now(),
                ]);
            }

            JobOrder::where('job_order_no', $lockedRequest->job_order_no)
                ->update(['part_status' => 'Issued']);
        });

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'Warehouse issued a purchase request.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Parts issued successfully.',
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Parts issued successfully.');
    }

    public function approveForIssue(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseRole('head');

        if ($this->isRestockRequest($purchaseRequest) || $purchaseRequest->status === 'Issued') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This request cannot be approved for issuance.'], 422);
            }
            return redirect()->back()->with('error', 'This request cannot be approved for issuance.');
        }

        $displayStatus = $this->getMissingPurchaseRequest($purchaseRequest)?->status ?? $purchaseRequest->status;
        $inventoryCheck = $this->checkInventoryAvailability($this->parseParts($purchaseRequest->item));

        if (! in_array($displayStatus, ['Approved', 'Delivered', 'Picked Up'], true) || ! $inventoryCheck['available']) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'All requested parts must be available before issuance approval.'], 422);
            }
            return redirect()->back()->with('error', 'All requested parts must be available before issuance approval.');
        }

        $purchaseRequest->update([
            'warehouse_status' => 'Approved for Issue',
            'warehouse_approved_by' => auth()->id(),
            'warehouse_approved_at' => now(),
            'warehouse_prepared_by' => null,
            'warehouse_prepared_at' => null,
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Part issuance approved. Warehouse Staff may now prepare the request.',
            ]);
        }

        return redirect()->back()->with('success', 'Part issuance approved. Warehouse Staff may now prepare the request.');
    }

    public function hold(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseRole('head');

        if ($purchaseRequest->status === 'Issued' || $purchaseRequest->warehouse_status === 'Issued') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Issued requests cannot be placed on hold.'], 422);
            }
            return redirect()->back()->with('error', 'Issued requests cannot be placed on hold.');
        }

        $purchaseRequest->update([
            'warehouse_status' => 'On Hold',
            'warehouse_prepared_by' => null,
            'warehouse_prepared_at' => null,
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Part issuance placed on hold.',
            ]);
        }

        return redirect()->back()->with('success', 'Part issuance placed on hold.');
    }

    public function prepare(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseRole('staff');

        if ($purchaseRequest->warehouse_status !== 'Approved for Issue') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Warehouse Head approval is required before preparation.'], 422);
            }
            return redirect()->back()->with('error', 'Warehouse Head approval is required before preparation.');
        }

        $inventoryCheck = $this->checkInventoryAvailability($this->parseParts($purchaseRequest->item));

        if (! $inventoryCheck['available']) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Stock is no longer sufficient for this request.'], 422);
            }
            return redirect()->back()->with('error', 'Stock is no longer sufficient for this request.');
        }

        $purchaseRequest->update([
            'warehouse_status' => 'Preparing',
            'warehouse_prepared_by' => auth()->id(),
            'warehouse_prepared_at' => now(),
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Parts marked as preparing. Record actual quantities when issuing.',
            ]);
        }

        return redirect()->back()->with('success', 'Parts marked as preparing. Record actual quantities when issuing.');
    }

    public function sendToPurchase(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseRole('head');

        if ($this->isRestockRequest($purchaseRequest)) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be sent from Warehouse Part Requests.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be sent from Warehouse Part Requests.');
        }

        if ($purchaseRequest->status !== 'Approved') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only approved purchase requests can be sent to purchasing department.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Only approved purchase requests can be sent to purchasing department.');
        }

        if ($this->missingPurchaseRequestExists($purchaseRequest)) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Missing parts were already sent to the Purchase Department.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'Missing parts were already sent to the Purchase Department.');
        }

        $parts = $this->parseParts($purchaseRequest->item);
        $inventoryCheck = $this->checkInventoryAvailability($parts);

        if ($inventoryCheck['available']) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'All requested parts are available. Please issue the parts instead.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'All requested parts are available. Please issue the parts instead.');
        }

        $missingParts = $inventoryCheck['missing'] ?? [];

        if (count($missingParts) === 0) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'No missing parts found to send to Purchase Department.'], 422);
            }
            return redirect()
                ->back()
                ->with('error', 'No missing parts found to send to Purchase Department.');
        }

        $missingItemText = $this->buildPartsText($missingParts);
        $missingTotalQuantity = collect($missingParts)->sum('needed');
        $missingPrNo = $this->generateMissingPrNo($purchaseRequest->pr_no);

        $missingPurchaseRequest = PurchaseRequest::create([
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

        $this->broadcastSystemDataUpdated(
            'Warehouse',
            'PurchaseRequest',
            'created',
            $missingPurchaseRequest->id,
            'Warehouse sent missing parts to Purchase Department.'
        );

        $oldRemarks = trim($purchaseRequest->remarks ?? '');

        $purchaseRequest->update([
            'remarks' => trim($oldRemarks . ' Missing parts sent to Purchase as ' . $missingPrNo . '.'),
        ]);

        JobOrder::where('job_order_no', $purchaseRequest->job_order_no)
            ->update([
                'part_status' => 'For Purchase',
            ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Only unavailable parts were sent to Purchase Department.',
            ]);
        }

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

        $workflowStatus = $purchaseRequest->warehouse_status;
        if (! $workflowStatus) {
            $workflowStatus = $purchaseRequest->status === 'Issued'
                ? 'Issued'
                : 'Pending Warehouse Approval';
        }
        $purchaseRequest->warehouse_workflow_status = $workflowStatus;

        $purchaseRequest->can_approve_for_issue =
            $purchaseRequest->status !== 'Issued'
            && $inventoryCheck['available']
            && in_array($workflowStatus, ['Pending Warehouse Approval', 'On Hold'], true)
            && (
                $purchaseRequest->status === 'Approved'
                || in_array($warehouseDisplayStatus, ['Delivered', 'Picked Up'], true)
            );

        $purchaseRequest->can_hold =
            $purchaseRequest->status !== 'Issued'
            && in_array($workflowStatus, ['Pending Warehouse Approval', 'Approved for Issue', 'Preparing'], true);

        $purchaseRequest->can_prepare =
            $workflowStatus === 'Approved for Issue'
            && $inventoryCheck['available'];

        $purchaseRequest->can_issue =
            $workflowStatus === 'Preparing'
            && $inventoryCheck['available'];

        $purchaseRequest->needs_purchase =
            $purchaseRequest->status === 'Approved'
            && ! $inventoryCheck['available']
            && ! $missingPrAlreadyCreated;

        return $purchaseRequest;
    }

    private function authorizeWarehouseRole(string $role): void
    {
        $user = auth()->user();

        abort_unless(
            $user
                && strtolower(trim((string) $user->department)) === 'warehouse'
                && strtolower(trim((string) $user->role)) === $role,
            403,
            "Only Warehouse {$role} may perform this action."
        );
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
