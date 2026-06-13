<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
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

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Base Query
        |--------------------------------------------------------------------------
        | Maintenance Requests only.
        | This includes original PR records and excludes Inventory Restock records.
        |--------------------------------------------------------------------------
        */
        $baseQuery = MaintenanceRequest::query()
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request')
                    ->orWhere('source_type', 'Job Order');
            });

        /*
        |--------------------------------------------------------------------------
        | Active Purchase Requests
        |--------------------------------------------------------------------------
        | Hide copied Purchase PR numbers with "-P" to avoid duplicate display.
        | Example hidden here: PR-2026-0001-P
        |--------------------------------------------------------------------------
        */
        $query = (clone $baseQuery)
            ->whereIn('status', $this->purchaseStatuses)
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            });

        if ($request->filled('search')) {
            $search = trim($request->search);

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

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $purchaseRequests->getCollection()->transform(function ($purchaseRequest) {
            return $this->prepareRequestForDisplay($purchaseRequest);
        });

        /*
        |--------------------------------------------------------------------------
        | Issued Purchase History
        |--------------------------------------------------------------------------
        | Show only original PR.
        | Hide copied "-P" PR records.
        |--------------------------------------------------------------------------
        */
        $issuedRequests = (clone $baseQuery)
            ->where('status', 'Issued')
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->latest()
            ->paginate(5, ['*'], 'history_page')
            ->withQueryString();

        $issuedRequests->getCollection()->transform(function ($purchaseRequest) {
            return $this->prepareRequestForDisplay($purchaseRequest);
        });

        /*
        |--------------------------------------------------------------------------
        | Summary Counts
        |--------------------------------------------------------------------------
        | Counts should also exclude "-P" to avoid duplicate counting.
        |--------------------------------------------------------------------------
        */
        $totalRequests = (clone $baseQuery)
            ->whereIn('status', $this->purchaseStatuses)
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $forPurchase = (clone $baseQuery)
            ->where('status', 'For Purchase')
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $ordered = (clone $baseQuery)
            ->where('status', 'Ordered')
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $forPickup = (clone $baseQuery)
            ->where('status', 'For Pick-up')
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $forDelivery = (clone $baseQuery)
            ->where('status', 'For Delivery')
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $delivered = (clone $baseQuery)
            ->whereIn('status', ['Delivered', 'Picked Up'])
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $pickedUp = (clone $baseQuery)
            ->where('status', 'Picked Up')
            ->where(function ($q) {
                $q->where('pr_no', 'not like', '%-P%')
                    ->orWhereNull('pr_no');
            })
            ->count();

        $statuses = $this->purchaseStatuses;

        return view('Purchase.maintenance-requests', compact(
            'purchaseRequests',
            'issuedRequests',
            'totalRequests',
            'forPurchase',
            'ordered',
            'forPickup',
            'forDelivery',
            'delivered',
            'pickedUp',
            'statuses'
        ));
    }

    public function createPo(MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        if ($maintenanceRequest->status !== 'For Purchase') {
            return redirect()
                ->back()
                ->with('error', 'Only For Purchase requests can create a purchase order.');
        }

        if (PurchaseOrder::where('purchase_request_id', $maintenanceRequest->id)->exists()) {
            return redirect()
                ->back()
                ->with('error', 'A purchase order already exists for this maintenance request.');
        }

        return redirect()
            ->route('purchase-orders', [
                'create_from_pr' => $maintenanceRequest->id,
            ])
            ->with('open_po_modal', true);
    }

    private function prepareRequestForDisplay(MaintenanceRequest $purchaseRequest): MaintenanceRequest
    {
        $parts = $this->parseParts($purchaseRequest->item);

        $purchaseRequest->parts_breakdown = $parts;
        $purchaseRequest->first_item_display = $parts[0]['name'] ?? $purchaseRequest->item ?? '—';

        $firstQuantity = $parts[0]['quantity'] ?? null;
        $firstUnit = $parts[0]['unit'] ?? null;

        if ($firstQuantity && $firstUnit && $firstUnit !== '—') {
            $purchaseRequest->first_quantity_display = $firstQuantity . ' ' . $firstUnit;
        } elseif ($firstQuantity) {
            $purchaseRequest->first_quantity_display = $firstQuantity;
        } else {
            $purchaseRequest->first_quantity_display = $purchaseRequest->quantity ?? '—';
        }

        return $purchaseRequest;
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

                /*
                |--------------------------------------------------------------------------
                | Format: Engine Oil - Qty: 2 liter
                |--------------------------------------------------------------------------
                */
                if (str_contains(strtolower($part), ' - qty:')) {
                    [$name, $quantityWithUnit] = preg_split('/ - qty:/i', $part, 2);

                    $parsed = $this->splitQuantityAndUnit($quantityWithUnit);

                    return [
                        'name' => trim($name),
                        'quantity' => $parsed['quantity'],
                        'unit' => $parsed['unit'],
                        'quantity_display' => trim($parsed['quantity'] . ' ' . $parsed['unit']),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Format: Engine Oil (2 liter)
                |--------------------------------------------------------------------------
                */
                if (preg_match('/^(.*?)\s*\((\d+\s*[^)]*)\)$/', $part, $matches)) {
                    $parsed = $this->splitQuantityAndUnit($matches[2] ?? '1');

                    return [
                        'name' => trim($matches[1] ?? $part),
                        'quantity' => $parsed['quantity'],
                        'unit' => $parsed['unit'],
                        'quantity_display' => trim($parsed['quantity'] . ' ' . $parsed['unit']),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Format: Engine Oil 2 liter
                |--------------------------------------------------------------------------
                */
                if (preg_match('/^(.*?)\s+(\d+)\s*(liter|liters|litre|litres|ltr|ltrs|pcs|pc|piece|pieces|set|sets|bottle|bottles|box|boxes|pack|packs|pair|pairs|gallon|gallons|kg|meter|meters)$/i', $part, $matches)) {
                    return [
                        'name' => trim($matches[1] ?? $part),
                        'quantity' => trim($matches[2] ?? '1'),
                        'unit' => $this->normalizeUnit($matches[3] ?? ''),
                        'quantity_display' => trim(($matches[2] ?? '1') . ' ' . $this->normalizeUnit($matches[3] ?? '')),
                    ];
                }

                return [
                    'name' => $part,
                    'quantity' => '1',
                    'unit' => '—',
                    'quantity_display' => '1',
                ];
            })
            ->filter(fn ($part) => is_array($part) && ! empty($part['name']))
            ->values()
            ->toArray();
    }

    private function splitQuantityAndUnit(?string $value): array
    {
        $value = trim($value ?? '');

        if ($value === '') {
            return [
                'quantity' => '1',
                'unit' => '—',
            ];
        }

        if (preg_match('/^(\d+)\s*(.*)$/', $value, $matches)) {
            return [
                'quantity' => trim($matches[1] ?? '1'),
                'unit' => $this->normalizeUnit($matches[2] ?? ''),
            ];
        }

        return [
            'quantity' => $value,
            'unit' => '—',
        ];
    }

    private function normalizeUnit(?string $unit): string
    {
        $unit = strtolower(trim($unit ?? ''));

        return match ($unit) {
            'liter', 'liters', 'litre', 'litres', 'ltr', 'ltrs', 'l' => 'liter',
            'piece', 'pieces', 'pc', 'pcs' => 'pcs',
            'set', 'sets' => 'set',
            'bottle', 'bottles' => 'bottle',
            'box', 'boxes' => 'box',
            'pack', 'packs' => 'pack',
            'pair', 'pairs' => 'pair',
            'gallon', 'gallons', 'gal' => 'gallon',
            'kg', 'kilogram', 'kilograms' => 'kg',
            'meter', 'meters', 'm' => 'meter',
            default => $unit !== '' ? $unit : '—',
        };
    }
}