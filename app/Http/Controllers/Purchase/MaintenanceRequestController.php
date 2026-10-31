<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Services\PartParser;
use App\Support\TableSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MaintenanceRequestController extends Controller
{
    private array $purchaseStatuses = [
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
    ];

    private PartParser $partParser;

    public function __construct(PartParser $partParser)
    {
        $this->partParser = $partParser;
    }

    public function index(Request $request)
    {
        if ($request->query('view') === 'history' || $request->query('record_view') === 'history') {
            return $this->history($request);
        }

        // Keep actionable requests separate from orders already moving through
        // procurement.  The tab is display-only; create PO remains limited to
        // For Purchase and completed receipts continue to move to History.
        $recordView = in_array($request->query('record_view'), ['pending', 'in_progress'], true)
            ? $request->query('record_view')
            : 'pending';
        $visibleStatuses = $recordView === 'pending'
            ? ['For Purchase']
            : ['Ordered', 'For Pick-up', 'For Delivery'];

        $baseQuery = $this->maintenanceBaseQuery();
        $query = (clone $baseQuery)->whereIn('status', $visibleStatuses);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('quantity', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All States') {
            $query->where('status', $request->status);
        }

        TableSort::apply($query, $request, ['created_at' => 'created_at', 'pr_no' => 'pr_no', 'status' => 'status'], 'created_at', 'desc');
        $purchaseRequests = $query
            ->with('requester')
            ->paginate(8)
            ->withQueryString();

        // A linked PO is the source of truth for Purchasing progress.
        // Keep the original PR status unchanged for downstream warehouse workflows.
        $linkedOrders = PurchaseOrder::query()
            ->whereIn('purchase_request_id', $purchaseRequests->getCollection()->pluck('id'))
            ->orderByDesc('id')->get(['id', 'po_no', 'purchase_request_id', 'status'])
            ->unique('purchase_request_id')->keyBy('purchase_request_id');
        $purchaseRequests->getCollection()->each(function ($pr) use ($linkedOrders) {
            $po = $linkedOrders->get($pr->id);
            $pr->linked_po_no = $po?->po_no;
            $pr->purchasing_display_status = $po
                ? ($po->status === 'For Review' ? 'PO For Review' : ($po->status === 'Revision Required' ? 'PO Revision Required' : $po->status))
                : $pr->status;
            $pr->can_create_po = $pr->status === 'For Purchase' && ! $po;
        });

        $jobOrders = JobOrder::query()
            ->with(['approver', 'audits'])
            ->whereIn('job_order_no', $purchaseRequests->getCollection()->pluck('job_order_no')->filter()->unique())
            ->get()
            ->keyBy('job_order_no');
        $inventoryItems = InventoryItem::query()->get();
        $purchaseRequests->getCollection()->transform(
            fn ($purchaseRequest) => $this->prepareRequestForDisplay(
                $purchaseRequest,
                $jobOrders->get($purchaseRequest->job_order_no),
                $inventoryItems
            )
        );

        $totalRequests = (clone $baseQuery)
            ->whereIn('status', $this->purchaseStatuses)
            ->count();
        $forPurchase = (clone $baseQuery)->where('status', 'For Purchase')
            ->whereNotIn('id', PurchaseOrder::query()->whereNotNull('purchase_request_id')->select('purchase_request_id'))
            ->count();
        $ordered = (clone $baseQuery)->where('status', 'Ordered')->count();
        $forPickup = (clone $baseQuery)->where('status', 'For Pick-up')->count();
        $forDelivery = (clone $baseQuery)->where('status', 'For Delivery')->count();
        $delivered = (clone $baseQuery)
            ->whereIn('status', ['Delivered', 'Picked Up'])
            ->count();
        $pickedUp = (clone $baseQuery)->where('status', 'Picked Up')->count();
        $statuses = $visibleStatuses;

        return view(
            'Purchase.Requested_Purchase.maintenance-requests',
            compact(
                'purchaseRequests',
                'totalRequests',
                'forPurchase',
                'ordered',
                'forPickup',
                'forDelivery',
                'delivered',
                'pickedUp',
                'statuses',
                'recordView'
            )
        );
    }

    private function history(Request $request)
    {
        $historyQuery = $this->historyBaseQuery();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $historyQuery->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('source') && $request->source !== 'All Sources') {
            if ($request->source === 'Maintenance Request') {
                $historyQuery->where(function ($source) {
                    $source->whereNull('source_type')
                        ->orWhereIn('source_type', ['Maintenance Request', 'Job Order']);
                });
            } elseif ($request->source === 'Inventory Restock') {
                $historyQuery->where('source_type', 'Auto Restock');
            }
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $historyQuery->where('status', $request->status);
        }

        TableSort::apply($historyQuery, $request, ['updated_at' => 'updated_at', 'pr_no' => 'pr_no', 'status' => 'status'], 'updated_at', 'desc');
        $historyRecords = $historyQuery
            ->paginate(10)
            ->withQueryString();

        $historyRecords->getCollection()->transform(function ($record) {
            $record = $this->prepareRequestForDisplay($record);
            $record->history_source_label = $record->source_type === 'Auto Restock'
                ? 'Inventory Restock'
                : 'Maintenance Request';

            return $record;
        });

        $historyBase = $this->historyBaseQuery();
        $totalHistory = (clone $historyBase)->count();
        $maintenanceHistory = (clone $historyBase)
            ->where(function ($source) {
                $source->whereNull('source_type')
                    ->orWhereIn('source_type', ['Maintenance Request', 'Job Order']);
            })
            ->count();
        $restockHistory = (clone $historyBase)
            ->where('source_type', 'Auto Restock')
            ->count();
        $thisMonthHistory = (clone $historyBase)
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->count();

        return view(
            'Purchase.purchase-history',
            compact(
                'historyRecords',
                'totalHistory',
                'maintenanceHistory',
                'restockHistory',
                'thisMonthHistory'
            )
        );
    }

    public function createPo(
        MaintenanceRequest $maintenanceRequest
    ): RedirectResponse {
        if ($maintenanceRequest->status !== 'For Purchase') {
            session()->flash(
                'error',
                'Only For Purchase requests can create a purchase order.'
            );

            return new RedirectResponse('/maintenance-requests');
        }

        $purchaseOrderExists = PurchaseOrder::query()
            ->where('purchase_request_id', $maintenanceRequest->id)
            ->exists();

        if ($purchaseOrderExists) {
            session()->flash(
                'error',
                'A purchase order already exists for this maintenance request.'
            );

            return new RedirectResponse('/maintenance-requests');
        }

        session()->flash('open_po_modal', true);

        return new RedirectResponse(
            '/purchase-orders?create_from_pr='.$maintenanceRequest->id
        );
    }

    private function maintenanceBaseQuery()
    {
        return MaintenanceRequest::query()
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request')
                    ->orWhere('source_type', 'Job Order');
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
                $q->whereNull('pr_no')
                    ->orWhere('pr_no', 'not like', 'RST-%');
            });
    }

    private function historyBaseQuery()
    {
        return MaintenanceRequest::query()
            ->where(function ($query) {
                $query
                    ->where(function ($maintenance) {
                        $maintenance
                            ->where(function ($source) {
                                $source->whereNull('source_type')
                                    ->orWhere('source_type', 'Maintenance Request')
                                    ->orWhere('source_type', 'Job Order');
                            })
                            ->whereIn('status', ['Delivered', 'Picked Up', 'Issued'])
                            ->where(function ($q) {
                                $q->whereNull('job_order_no')
                                    ->orWhere('job_order_no', '!=', 'RESTOCK');
                            })
                            // Internal purchase-side copies (e.g. PR-...-P) mirror
                            // the original PR and must not appear as a second history row.
                            ->where(function ($q) {
                                $q->whereNull('pr_no')
                                    ->orWhere('pr_no', 'not like', '%-P%');
                            });
                    })
                    ->orWhere(function ($restock) {
                        $restock
                            ->where('source_type', 'Auto Restock')
                            ->whereIn('status', ['Delivered', 'Picked Up', 'Issued']);
                    });
            });
    }

    private function prepareRequestForDisplay(
        MaintenanceRequest $purchaseRequest,
        ?JobOrder $jobOrder = null,
        ?Collection $inventoryItems = null
    ): MaintenanceRequest {
        $inventoryIdsByPartIndex = collect(preg_split('/\s*,\s*/', (string) $purchaseRequest->item))
            ->map(function (string $part): ?int {
                return preg_match('/\[item:(\d+)\]/i', $part, $match)
                    ? (int) $match[1]
                    : null;
            });
        $inventoryItemsById = $inventoryItems?->keyBy('id');

        $parts = collect(
            $this->partParser->parsePartText($purchaseRequest->item)
        )
            ->map(function ($part, $index) use ($inventoryItems, $inventoryItemsById, $inventoryIdsByPartIndex) {
                $unit = ($part['unit'] ?? '') !== '' ? $part['unit'] : '—';
                $linkedInventoryId = $inventoryIdsByPartIndex->get($index);
                $inventoryItem = $linkedInventoryId
                    ? $inventoryItemsById?->get($linkedInventoryId)
                    : null;
                $inventoryItem ??= $inventoryItems
                    ? $this->findPhysicalInventoryItem($inventoryItems, (string) ($part['name'] ?? ''), $unit)
                    : null;
                $physicalOnHand = $inventoryItem
                    ? max(0, (int) ($inventoryItem->quantity_available ?? $inventoryItem->on_hand ?? 0))
                    : 0;

                return [
                    'name' => $part['name'] ?? '',
                    'quantity' => $part['quantity'] ?? 1,
                    'unit' => $unit,
                    'quantity_display' => trim(
                        ($part['quantity'] ?? 1).' '.$unit
                    ),
                    'physical_on_hand' => $physicalOnHand,
                    'physical_on_hand_display' => trim($physicalOnHand.' '.$unit),
                ];
            })
            ->filter(fn ($part) => is_array($part) && ! empty($part['name']))
            ->values()
            ->toArray();

        $purchaseRequest->withDisplayPartsBreakdown($parts);
        $purchaseRequest->first_item_display =
            $parts[0]['name'] ?? $purchaseRequest->item ?? '—';

        $firstQuantity = $parts[0]['quantity'] ?? null;
        $firstUnit = $parts[0]['unit'] ?? null;

        if ($firstQuantity && $firstUnit && $firstUnit !== '—') {
            $purchaseRequest->first_quantity_display = $firstQuantity.' '.$firstUnit;
        } elseif ($firstQuantity) {
            $purchaseRequest->first_quantity_display = $firstQuantity;
        } else {
            $purchaseRequest->first_quantity_display = $purchaseRequest->quantity ?? '—';
        }

        $approvalAudit = $jobOrder?->audits?->first(fn ($audit) =>
            $audit->action === 'approved'
            && is_string(data_get($audit->metadata, 'handwritten_signature_path'))
        );
        $signaturePath = data_get($approvalAudit?->metadata, 'handwritten_signature_path');
        $isAuthorized = $jobOrder?->review_status === 'Approved';
        $purchaseRequest->jo_approver = $isAuthorized
            ? ($jobOrder?->approver?->name ?? 'Not available')
            : 'Not approved';
        $purchaseRequest->jo_approval_status = $jobOrder?->review_status ?? 'JO not found';
        $purchaseRequest->jo_approved_at = ($jobOrder?->approved_at ?? $approvalAudit?->created_at)?->format('M d, Y · h:i A') ?? '';
        $purchaseRequest->jo_approval_signature = $isAuthorized && is_string($signaturePath) && $signaturePath !== ''
            ? route('purchase.job-orders.approval-signature', $jobOrder, false)
            : '';

        return $purchaseRequest;
    }

    private function findPhysicalInventoryItem(Collection $items, string $name, string $unit): ?InventoryItem
    {
        $normalize = static fn (string $value): string => mb_strtolower(
            preg_replace('/[^a-z0-9]+/i', '', trim($value))
        );
        $nameKey = $normalize(preg_replace('/\s*\[item:\d+\]/i', '', $name));
        $unitKey = $this->partParser->normalizeUnit($unit === '—' ? '' : $unit);
        $matches = $items->filter(function (InventoryItem $item) use ($normalize, $nameKey, $unitKey) {
            $itemName = $normalize((string) ($item->parts_name ?? $item->item_name ?? ''));
            $itemCode = $normalize((string) ($item->item_code ?? ''));
            $itemUnit = $this->partParser->normalizeUnit((string) ($item->unit_of_measurement ?? $item->unit ?? ''));

            return ($itemName === $nameKey || $itemCode === $nameKey)
                && ($unitKey === '' || $itemUnit === $unitKey);
        })->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
