<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Services\PartParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        if ($request->query('view') === 'history') {
            return $this->history($request);
        }

        $baseQuery = $this->maintenanceBaseQuery();

        $query = (clone $baseQuery)
            ->whereIn('status', $this->purchaseStatuses);

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

        if (
            $request->filled('status')
            && $request->status !== 'All States'
        ) {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $purchaseRequests
            ->getCollection()
            ->transform(fn ($purchaseRequest) =>
                $this->prepareRequestForDisplay($purchaseRequest)
            );

        $totalRequests = (clone $baseQuery)
            ->whereIn('status', $this->purchaseStatuses)
            ->count();

        $forPurchase = (clone $baseQuery)
            ->where('status', 'For Purchase')
            ->count();

        $ordered = (clone $baseQuery)
            ->where('status', 'Ordered')
            ->count();

        $forPickup = (clone $baseQuery)
            ->where('status', 'For Pick-up')
            ->count();

        $forDelivery = (clone $baseQuery)
            ->where('status', 'For Delivery')
            ->count();

        $delivered = (clone $baseQuery)
            ->whereIn('status', [
                'Delivered',
                'Picked Up',
            ])
            ->count();

        $pickedUp = (clone $baseQuery)
            ->where('status', 'Picked Up')
            ->count();

        $statuses = $this->purchaseStatuses;

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
                'statuses'
            )
        );
    }

    private function history(Request $request)
    {
        $historyQuery = MaintenanceRequest::query()
            ->where(function ($query) {
                $query
                    ->where(function ($maintenance) {
                        $maintenance
                            ->where(function ($source) {
                                $source->whereNull('source_type')
                                    ->orWhere('source_type', 'Maintenance Request')
                                    ->orWhere('source_type', 'Job Order');
                            })
                            ->where('status', 'Issued')
                            ->where(function ($q) {
                                $q->whereNull('job_order_no')
                                    ->orWhere('job_order_no', '!=', 'RESTOCK');
                            });
                    })
                    ->orWhere(function ($restock) {
                        $restock
                            ->where('source_type', 'Auto Restock')
                            ->whereIn('status', [
                                'Delivered',
                                'Picked Up',
                                'Issued',
                            ]);
                    });
            });

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
                        ->orWhereIn('source_type', [
                            'Maintenance Request',
                            'Job Order',
                        ]);
                });
            } elseif ($request->source === 'Inventory Restock') {
                $historyQuery->where('source_type', 'Auto Restock');
            }
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $historyQuery->where('status', $request->status);
        }

        $historyRecords = $historyQuery
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        $historyRecords
            ->getCollection()
            ->transform(function ($record) {
                $record = $this->prepareRequestForDisplay($record);
                $record->history_source_label = $record->source_type === 'Auto Restock'
                    ? 'Inventory Restock'
                    : 'Maintenance Request';

                return $record;
            });

        $historyBase = MaintenanceRequest::query()
            ->where(function ($query) {
                $query
                    ->where(function ($maintenance) {
                        $maintenance
                            ->where(function ($source) {
                                $source->whereNull('source_type')
                                    ->orWhere('source_type', 'Maintenance Request')
                                    ->orWhere('source_type', 'Job Order');
                            })
                            ->where('status', 'Issued')
                            ->where(function ($q) {
                                $q->whereNull('job_order_no')
                                    ->orWhere('job_order_no', '!=', 'RESTOCK');
                            });
                    })
                    ->orWhere(function ($restock) {
                        $restock
                            ->where('source_type', 'Auto Restock')
                            ->whereIn('status', ['Delivered', 'Picked Up', 'Issued']);
                    });
            });

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
            ->where(
                'purchase_request_id',
                $maintenanceRequest->id
            )
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
            '/purchase-orders?create_from_pr='
            . $maintenanceRequest->id
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

    private function prepareRequestForDisplay(
        MaintenanceRequest $purchaseRequest
    ): MaintenanceRequest {
        $parts = collect(
            $this->partParser->parsePartText(
                $purchaseRequest->item
            )
        )
            ->map(function ($part) {
                $unit = ($part['unit'] ?? '') !== ''
                    ? $part['unit']
                    : '—';

                return [
                    'name' => $part['name'] ?? '',
                    'quantity' => $part['quantity'] ?? 1,
                    'unit' => $unit,
                    'quantity_display' => trim(
                        ($part['quantity'] ?? 1)
                        . ' '
                        . $unit
                    ),
                ];
            })
            ->filter(
                fn ($part) =>
                    is_array($part)
                    && ! empty($part['name'])
            )
            ->values()
            ->toArray();

        $purchaseRequest->parts_breakdown = $parts;

        $purchaseRequest->first_item_display =
            $parts[0]['name']
            ?? $purchaseRequest->item
            ?? '—';

        $firstQuantity =
            $parts[0]['quantity']
            ?? null;

        $firstUnit =
            $parts[0]['unit']
            ?? null;

        if (
            $firstQuantity
            && $firstUnit
            && $firstUnit !== '—'
        ) {
            $purchaseRequest->first_quantity_display =
                $firstQuantity . ' ' . $firstUnit;
        } elseif ($firstQuantity) {
            $purchaseRequest->first_quantity_display =
                $firstQuantity;
        } else {
            $purchaseRequest->first_quantity_display =
                $purchaseRequest->quantity
                ?? '—';
        }

        return $purchaseRequest;
    }
}
