<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Services\PartParser;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryLedgerService;
use App\Support\TableSort;
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
        'Submitted',
        'Approved',
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
    ];

    private array $historyStatuses = [
        'Delivered',
        'Picked Up',
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
            ->where(function ($query) {
                $query->where('remarks', 'like', 'Missing parts from %')
                    ->orWhereIn('status', ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up']);
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

    public function index(Request $request)
    {
        $currentView = strtolower(trim((string) $request->input('view', 'active')));
        if (in_array($currentView, ['active', 'jo'], true)) {
            // Approved Job Orders remain in Maintenance until a linked PR is created.
            // Old Warehouse JO bookmarks now open the PR queue instead.
            $currentView = 'pr';
        }
        if (! in_array($currentView, ['jo', 'pr', 'history'], true)) {
            $currentView = 'pr';
        }

        // JO requests are sourced from signed, open Job Orders, not fabricated PRs.
        // This queue is read-only until the JO-specific stock ledger action is implemented.
        $joMaintenanceTypes = JobOrder::query()
            ->where('review_status', 'Approved')
            ->whereNotNull('maintenance_type')
            ->distinct()
            ->orderBy('maintenance_type')
            ->pluck('maintenance_type')
            ->filter()
            ->values()
            ->all();
        $jobOrderRequests = null;
        $jobOrderIssuances = $currentView === 'history'
            ? InventoryIssuance::query()
                ->whereIn('reference_no', JobOrder::query()->select('job_order_no'))
                ->with(['items', 'issuer'])
                ->orderByDesc('issued_at')
                ->limit(100)
                ->get()
                ->filter(fn ($issue) => $issue->reference_no
                    && str_starts_with((string) $issue->reference_no, 'JO-'))
            : collect();
        if ($currentView === 'jo') {
            $parser = app(PartParser::class);
            // Use the same exact-part matching and authoritative stock field as
            // Warehouse issuance. A linked PR must not hide a stocked JO.
            $linkedPrJobNumbers = $this->warehouseMaintenanceRequestQuery()
                ->whereNotNull('job_order_no')
                ->pluck('job_order_no')
                ->flip();
            $jobs = JobOrder::query()
                ->where('review_status', 'Approved')
                ->where('status', '!=', 'Completed')
                ->where(function ($q) {
                    $q->whereNull('part_status')->orWhere('part_status', '!=', 'Issued');
                })
                ->whereNotNull('part_needed')
                ->where('part_needed', '!=', '')
                ->orderByDesc('created_at')
                ->get()
                ->map(function (JobOrder $job) use ($parser, $linkedPrJobNumbers) {
                    $parts = $parser->parsePartText((string) $job->part_needed);
                    $job->warehouse_has_linked_pr = $linkedPrJobNumbers->has($job->job_order_no);
                    $job->warehouse_available_parts = collect($parts)->map(function ($part) {
                        $item = $this->findInventoryItem($part['name'], $part['unit'] ?? '');
                        return [
                            'name' => $part['name'],
                            'quantity' => (int) $part['quantity'],
                            'unit' => $part['unit'] ?? '',
                            'available' => max(0, (int) ($item?->quantity_available ?? 0)),
                        ];
                    })->values()->all();
                    $stocked = collect($job->warehouse_available_parts)->filter(fn ($part) => $part['available'] >= $part['quantity'])->count();
                    $some = collect($job->warehouse_available_parts)->filter(fn ($part) => $part['available'] > 0)->count();
                    $job->warehouse_stock_status = $stocked === count($parts) && $stocked > 0 ? 'Available' : ($some > 0 ? 'Partial' : 'Unavailable');
                    $job->warehouse_can_issue = ! $job->warehouse_has_linked_pr
                        && trim((string) $job->assigned_mechanic) !== ''
                        && count($job->warehouse_available_parts) === count($parts)
                        && collect($job->warehouse_available_parts)->every(
                            fn ($part) => (int) $part['available'] >= (int) $part['quantity']
                        );
                    return $job;
                })
                ->filter(fn ($job) => count($job->warehouse_available_parts) > 0);
            if ($request->filled('maintenance_type') && $request->input('maintenance_type') !== 'All Types') {
                $jobs = $jobs->filter(fn ($job) => $job->maintenance_type === $request->input('maintenance_type'));
            }
            if ($request->filled('search')) {
                $needle = mb_strtolower(trim((string) $request->input('search')));
                $jobs = $jobs->filter(fn ($job) => str_contains(mb_strtolower(
                    $job->job_order_no.' '.$job->bus_no.' '.$job->part_needed
                ), $needle));
            }
            $sortColumn = (string) $request->input('sort', 'created_at');
            $sortColumn = in_array($sortColumn, ['created_at', 'job_order_no', 'bus_no'], true)
                ? $sortColumn : 'created_at';
            $descending = $request->input('direction', 'desc') !== 'asc';
            $jobs = $jobs->sortBy($sortColumn, SORT_REGULAR, $descending)->values();
            $page = max(1, (int) $request->input('page', 1));
            $jobOrderRequests = new LengthAwarePaginator(
                $jobs->forPage($page, 8)->values(),
                $jobs->count(), 8, $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $statusOptions = $currentView === 'history'
            ? $this->historyStatuses
            : $this->statuses;

        // One visible PR queue; keep the two sources identified and handled independently.
        $query = PurchaseRequest::query()->with('requester')
            ->where(function ($group) {
                $group->where(function ($maintenance) {
                    $maintenance->where(function ($q) {
                        $q->whereNull('source_type')->orWhere('source_type', 'Maintenance Request');
                    })->where(function ($q) {
                        $q->whereNull('job_order_no')->orWhere('job_order_no', '!=', 'RESTOCK');
                    })->where(function ($q) {
                        $q->whereNull('bus_no')->orWhere('bus_no', '!=', 'RESTOCK');
                    })->where(function ($q) {
                        $q->whereNull('remarks')->orWhere('remarks', 'not like', 'Missing parts from %');
                    });
                })->orWhere(function ($restock) {
                    $restock->where('source_type', 'Auto Restock')->where('job_order_no', 'RESTOCK');
                });
            })->whereIn('status', $statusOptions);

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

        TableSort::apply($query, $request, ['pr_no' => 'pr_no', 'created_at' => 'created_at', 'status' => 'status'], 'created_at', 'desc');
        $purchaseRequests = $query
            ->paginate(8)
            ->withQueryString();

        $purchaseRequests->getCollection()->transform(function ($purchaseRequest) {
            return $purchaseRequest->source_type === 'Auto Restock'
                ? $purchaseRequest
                : $this->prepareRequestForWarehouse($purchaseRequest);
        });

        $activeCount = (clone $query)->whereIn('status', $this->statuses)->count();

        $historyCount = $this->warehouseMaintenanceRequestQuery()
            ->whereIn('status', $this->historyStatuses)
            ->count();

        $approvedJobOrders = JobOrder::query()
            ->select('job_order_no')
            ->where('review_status', 'Approved')
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->whereColumn('job_orders.bus_no', 'purchase_requests.bus_no');

        $approved = $this->warehouseMaintenanceRequestQuery()
            ->where(function ($query) use ($approvedJobOrders) {
                $query->where('status', 'Approved')
                    ->orWhere(function ($submitted) use ($approvedJobOrders) {
                        $submitted->where('status', 'Submitted')
                            ->whereIn('job_order_no', $approvedJobOrders);
                    });
            })
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

        // Restocking PRs are a separate Warehouse-origin queue, not Maintenance JO requests.
        $restockRequests = PurchaseRequest::query()
            ->where('source_type', 'Auto Restock')
            ->where('job_order_no', 'RESTOCK')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $restockInventoryItems = InventoryItem::query()->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'quantity_available', 'reorder_level', 'safety_stock', 'unit_of_measurement', 'supplier']);

        return view('Warehouse.part-requests', compact(
            'purchaseRequests',
            'approved',
            'forPurchase',
            'ordered',
            'delivered',
            'issued',
            'currentView',
            'jobOrderRequests',
            'joMaintenanceTypes',
            'jobOrderIssuances',
            'statusOptions',
            'activeCount',
            'historyCount',
            'restockInventoryItems',
            'restockRequests'
        ));
    }

    /**
     * JO-only Warehouse issuance is retired: all parts must go through an
     * authorized requisition, so direct calls cannot bypass the PR workflow.
     */
    public function issueJobOrder(JobOrder $jobOrder)
    {
        $this->authorizeWarehouseCapability('edit');

        return redirect()->route('part-requests', ['view' => 'pr'])
            ->with('error', 'Direct JO issuance is no longer available. Create a linked PR from an approved Job Order in Maintenance first.');
    }

    /**
     * Legacy PR stock-out is disabled. Physical maintenance part issuance must
     * go through an approved Item Request and signed warehouse handover (SWIS).
     * Preserve this route as a safe redirect for old links/forms.
     */
    public function issue(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseCapability('edit');

        return redirect()
            ->route('warehouse.item-requests')
            ->with('error', 'Direct issuance from Purchase Requests is disabled. Issue maintenance parts through an approved Item Request with a signed issuance slip.');
    }

    public function sendToPurchase(PurchaseRequest $purchaseRequest)
    {
        $this->authorizeWarehouseHead();

        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()->back()->with('error', 'Inventory restock requests cannot be forwarded from Warehouse Purchase Requests.');
        }

        // The Maintenance PR already contains the shortages approved through its
        // linked Job Order. Forward THAT record; never duplicate or subtract stock
        // again (available units may be allocated to an independent IR).
        $failure = null;
        $forwarded = DB::transaction(function () use ($purchaseRequest, &$failure) {
            $request = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);
            $jobOrder = JobOrder::query()
                ->where('job_order_no', $request->job_order_no)->lockForUpdate()->first();

            if (! $this->isAuthorizedForWarehouse($request, $jobOrder)) {
                $failure = 'Only submitted requests linked to an approved Job Order can be forwarded to Purchasing.';
                return false;
            }
            if ($this->missingPurchaseRequestExists($request)) {
                $failure = 'This Purchase Request was already forwarded to Purchasing.';
                return false;
            }
            $parts = $this->parseParts($request->item);
            if (empty($parts) || collect($parts)->every(fn ($part) => (int) ($part['quantity'] ?? 0) <= 0)) {
                $failure = 'This Purchase Request has no valid shortage quantities.';
                return false;
            }

            $request->update(['status' => 'For Purchase']);
            if ($jobOrder && ! in_array($jobOrder->part_status, ['Issued', 'Partial'], true)) {
                $jobOrder->update(['part_status' => 'For Purchase']);
            }
            return true;
        });

        if (! $forwarded) {
            return redirect()->back()->with('error', $failure ?? 'Unable to forward the Purchase Request.');
        }

        $this->broadcastSystemDataUpdated(
            'Warehouse', 'PurchaseRequest', 'sent_to_purchase',
            $purchaseRequest->id, 'Warehouse forwarded an existing Maintenance PR to Purchasing.'
        );

        return redirect()->back()->with('success', 'Purchase Request forwarded to Purchasing.');
    }

    private function prepareRequestForWarehouse(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $parts = $this->parseParts($purchaseRequest->item);
        $inventoryCheck = $this->checkInventoryAvailability($parts);

        $firstPart = $inventoryCheck['breakdown'][0] ?? null;
        $inventoryLabel = $this->getOverallInventoryLabel($inventoryCheck);

        $missingPurchaseRequest = $this->getMissingPurchaseRequest($purchaseRequest);
        $missingPrAlreadyCreated = $missingPurchaseRequest !== null;
        $jobOrder = JobOrder::query()->where('job_order_no', $purchaseRequest->job_order_no)->first();
        $partialIssuance = $jobOrder?->parts_issue_strategy === 'issue_available_only';

        $warehouseDisplayStatus = $purchaseRequest->status;

        if ($missingPurchaseRequest) {
            $warehouseDisplayStatus = $missingPurchaseRequest->status;
        }

        // Show the linked JO's full requirement as context, without changing PR shortage lines.
        $purchaseRequest->jo_parts_requirement = $jobOrder
            ? app(\App\Services\PartParser::class)->parsePartText((string) $jobOrder->part_needed)
            : [];
        $purchaseRequest->jo_ir_parts = $jobOrder
            ? \App\Models\Maintenance\ItemRequest::query()
                ->where('job_order_id', $jobOrder->id)
                ->get()
                ->flatMap(fn ($ir) => collect($ir->requested_parts ?? []))
                ->values()
                ->all()
            : [];
        // Display physical inventory consistently with the linked Warehouse IR.
        // This is informational only: do not modify PR purchase/issue eligibility,
        // which continues to use the existing inventoryCheck workflow.
        $linkedIrParts = collect($purchaseRequest->jo_ir_parts ?? []);
        $physicalByIdentity = [];
        foreach ($linkedIrParts as $linkedPart) {
            $inventoryId = (int) ($linkedPart['inventory_item_id'] ?? 0);
            if ($inventoryId <= 0) {
                continue;
            }
            $lookupName = $this->normalizeText(preg_replace('/\\s*\\[item:\\d+\\]/i', '', (string) ($linkedPart['name'] ?? '')));
            $lookupUnit = $this->normalizeUnit((string) ($linkedPart['unit'] ?? ''));
            if ($lookupName !== '') {
                $physicalByIdentity[$lookupName.'|'.$lookupUnit] = $inventoryId;
            }
        }
        $linkedInventory = InventoryItem::query()
            ->whereIn('id', array_values(array_unique($physicalByIdentity)))
            ->get()
            ->keyBy('id');
        foreach ($inventoryCheck['breakdown'] as &$stockLine) {
            $name = $this->normalizeText(preg_replace('/\\s*\\[item:\\d+\\]/i', '', (string) ($stockLine['name'] ?? '')));
            $unit = $this->normalizeUnit((string) ($stockLine['unit'] ?? ''));
            $inventoryId = $physicalByIdentity[$name.'|'.$unit] ?? null;
            $physicalItem = $inventoryId ? $linkedInventory->get($inventoryId) : null;
            if (! $physicalItem) {
                $physicalItem = $this->findInventoryItem((string) ($stockLine['name'] ?? ''), $unit);
            }
            $physical = $physicalItem ? max(0, (int) ($physicalItem->quantity_available ?? $physicalItem->on_hand ?? 0)) : 0;
            $stockLine['physical_on_hand'] = $physical;
            $stockLine['physical_on_hand_display'] = trim($physical.' '.($stockLine['unit'] ?? ''));
        }
        unset($stockLine);
        $purchaseRequest->parts_breakdown = $inventoryCheck['breakdown'];
        $purchaseRequest->inventory_check = $inventoryCheck;

        $purchaseRequest->first_item_display = $firstPart['name'] ?? $purchaseRequest->item ?? '—';
        $purchaseRequest->first_quantity_display = $firstPart['needed_display'] ?? '0';
        $purchaseRequest->first_on_hand_display = $inventoryCheck['breakdown'][0]['physical_on_hand_display'] ?? '0';

        $purchaseRequest->inventory_label = $inventoryLabel;
        $purchaseRequest->first_inventory_status = $inventoryLabel;
        $purchaseRequest->on_hand_available = $inventoryCheck['total_on_hand'];

        $purchaseRequest->missing_pr_already_created = $missingPrAlreadyCreated;
        $purchaseRequest->missing_purchase_request = $missingPurchaseRequest;
        $purchaseRequest->purchase_progress_status = $warehouseDisplayStatus;

        $isPurchaseFlowReady = $missingPrAlreadyCreated
            ? in_array($warehouseDisplayStatus, ['Delivered', 'Picked Up'], true)
            : $purchaseRequest->status === 'Approved';

        if ($purchaseRequest->warehouse_status === 'Partially Issued') {
            $workflowStatus = 'Partially Issued';
        } elseif ($purchaseRequest->status === 'Issued') {
            $workflowStatus = 'Issued';
        } elseif (in_array($purchaseRequest->status, ['Delivered', 'Picked Up'], true)) {
            // Procurement receipt is complete; IR/SWIS issuance is tracked separately.
            $workflowStatus = 'Delivered';
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
            && (($inventoryCheck['available'] && $isPurchaseFlowReady)
                || ($partialIssuance && $missingPrAlreadyCreated && $inventoryCheck['total_on_hand'] > 0));

        $purchaseRequest->needs_purchase =
            $this->isAuthorizedForWarehouse($purchaseRequest, $jobOrder)
            && ! $inventoryCheck['available']
            && ! $missingPrAlreadyCreated;

        return $purchaseRequest;
    }

    private function isAuthorizedForWarehouse(
        PurchaseRequest $purchaseRequest,
        ?JobOrder $jobOrder = null
    ): bool {
        if ($purchaseRequest->status === 'Approved') {
            return true;
        }

        if (
            $purchaseRequest->status !== 'Submitted'
            || blank($purchaseRequest->job_order_no)
            || $this->isRestockRequest($purchaseRequest)
        ) {
            return false;
        }

        $jobOrder ??= JobOrder::query()
            ->where('job_order_no', $purchaseRequest->job_order_no)
            ->first();

        return $jobOrder !== null
            && $jobOrder->review_status === 'Approved'
            && ! in_array($jobOrder->status, ['Completed', 'Cancelled'], true)
            && $this->normalizeText((string) $jobOrder->bus_no)
                === $this->normalizeText((string) $purchaseRequest->bus_no);
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
        // Current flow forwards the original PR. Legacy -P copied requests
        // remain discoverable for compatibility with existing records.
        if (in_array($purchaseRequest->status, ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up'], true)) {
            return $purchaseRequest;
        }
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
            ->where('pr_no', 'like', $purchaseRequest->pr_no.'-P%')
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
                        'needed_display' => trim(max(1, $quantity).($unit ? ' '.$unit : '')),
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
                        'needed_display' => trim(max(1, $quantity).($unit ? ' '.$unit : '')),
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
                'needed_display' => trim($neededQty.($inventoryUnit ? ' '.$inventoryUnit : '')),
                'available' => $availableQty,
                'available_display' => trim($availableQty.($inventoryUnit ? ' '.$inventoryUnit : '')),
                'status' => $partStatus,
                'matched_inventory_id' => $inventoryItem?->id,
            ];

            if (! $isAvailable) {
                $shortageQty = max(0, $neededQty - $availableQty);
                $missing[] = [
                    'name' => $name,
                    'needed' => $shortageQty,
                    'unit' => $inventoryUnit,
                    'needed_display' => trim($shortageQty.($inventoryUnit ? ' '.$inventoryUnit : '')),
                    'available' => $availableQty,
                    'available_display' => trim($availableQty.($inventoryUnit ? ' '.$inventoryUnit : '')),
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

        $identityScope = static function ($query) use ($partName): void {
            $query->whereRaw('LOWER(TRIM(item_name)) = ?', [$partName])
                ->orWhereRaw('LOWER(TRIM(item_code)) = ?', [$partName])
                ->orWhereRaw('LOWER(TRIM(parts_name)) = ?', [$partName]);
        };

        // Never take an arbitrary first stock row when names are duplicated.
        // This must agree with Maintenance's conservative identity policy.
        $candidates = InventoryItem::query()->where($identityScope)->get();
        if ($unit !== '') {
            $unitMatched = $candidates->filter(fn (InventoryItem $item) =>
                $this->normalizeUnit((string) ($item->unit_of_measurement ?: $item->unit)) === $unit
            );
            // A mismatch in units is not permission to select a different unit.
            $candidates = $unitMatched;
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function buildPartsText(array $parts): string
    {
        return collect($parts)
            ->map(function ($part) {
                $name = trim($part['name'] ?? '');
                $qty = (int) ($part['needed'] ?? 1);
                $unit = $this->normalizeUnit($part['unit'] ?? '');

                return $name.' - Qty: '.$qty.($unit ? ' '.$unit : '');
            })
            ->implode(', ');
    }

    private function generateMissingPrNo(string $originalPrNo): string
    {
        $base = $originalPrNo.'-P';
        $prNo = $base;
        $counter = 2;

        while (PurchaseRequest::where('pr_no', $prNo)->exists()) {
            $prNo = $base.$counter;
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

    private function authorizeWarehouseHead(): void
    {
        $user = auth()->user();
        $department = strtolower(trim((string) $user?->department));
        $role = strtolower(trim((string) $user?->role));
        $isSystemAdmin = ($department === 'admin' && $role === 'head')
            || $role === 'system admin';

        abort_unless(
            $isSystemAdmin || ($department === 'warehouse' && in_array($role, ['head', 'warehouse head'], true)),
            403,
            'Only the Warehouse Head may authorize Purchase Requests for Purchasing.'
        );
    }
}
