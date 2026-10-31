<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestockingPurchaseRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $department = strtolower(trim((string) $user?->department));
        $role = strtolower(trim((string) $user?->role));
        abort_unless(
            $user && $user->hasSystemPermission('warehouse', 'edit')
            && (($department === 'warehouse' && in_array($role, ['head', 'warehouse head'], true))
                || ($department === 'admin' && in_array($role, ['head', 'system admin'], true))),
            403
        );

        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:250'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'approval_notes' => ['nullable', 'string', 'max:500'],
            'signature' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $lines = collect($data['items'])->map(fn ($line) => [
            'inventory_item_id' => (int) $line['inventory_item_id'],
            'quantity' => (int) $line['quantity'],
        ])->values()->all();

        $signaturePath = null;
        try {
            // A locked inventory row serializes competing requests for the same item.
            $purchaseRequest = DB::transaction(function () use ($lines, $data, $user, $request, &$signaturePath) {
                $ids = collect($lines)->pluck('inventory_item_id')->sort()->values()->all();
                $items = InventoryItem::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $active = PurchaseRequest::query()
                    ->whereIn('source_type', ['Auto Restock', 'Inventory Restock'])
                    ->whereIn('status', ['Submitted', 'Approved', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery'])
                    ->whereNotNull('restock_items')
                    ->lockForUpdate()->get(['id', 'restock_items']);

                foreach ($lines as &$line) {
                    $item = $items->get($line['inventory_item_id']);
                    if (! $item) {
                        throw ValidationException::withMessages(['items' => 'An inventory item is unavailable.']);
                    }
                    $duplicate = $active->contains(function ($pr) use ($line) {
                        return collect(json_decode((string) $pr->restock_items, true) ?: [])
                            ->contains(fn ($existing) => (int) ($existing['inventory_item_id'] ?? 0) === $line['inventory_item_id']);
                    });
                    if ($duplicate) {
                        throw ValidationException::withMessages(['items' => 'An active restocking request already exists for one of the selected items.']);
                    }
                    $line['name'] = (string) $item->item_name;
                    $line['unit'] = (string) ($item->unit_of_measurement ?: 'PC');
                }
                unset($line);

                $signaturePath = $request->file('signature')->store('warehouse-restock-approvals', 'local');
                $temporary = 'RST-TMP-'.Str::uuid();
                $pr = PurchaseRequest::query()->create([
                    'pr_no' => $temporary,
                    'job_order_no' => 'RESTOCK',
                    'bus_no' => 'RESTOCK',
                    'item' => collect($lines)->map(fn ($line) => $line['name'].' - Qty: '.$line['quantity'].' '.$line['unit'])->implode(', '),
                    'quantity' => collect($lines)->sum('quantity'),
                    'status' => 'For Purchase',
                    'source_type' => 'Auto Restock',
                    'purpose' => $data['purpose'],
                    'remarks' => $data['remarks'] ?? null,
                    'requested_by' => $user->id,
                    'approved_at' => now(),
                    'warehouse_approved_by' => $user->id,
                    'warehouse_approved_at' => now(),
                    'warehouse_status' => 'Approved',
                    'restock_items' => json_encode($lines),
                    'restock_signature_path' => $signaturePath,
                    'restock_approval_notes' => $data['approval_notes'] ?? null,
                ]);
                $pr->update(['pr_no' => 'RST-'.now()->format('Y').'-'.str_pad((string) $pr->id, 4, '0', STR_PAD_LEFT)]);

                return $pr;
            });
        } catch (\Throwable $exception) {
            if ($signaturePath) {
                Storage::disk('local')->delete($signaturePath);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Restocking PR submitted to Purchasing.',
            'pr_no' => $purchaseRequest->pr_no,
        ], 201);
    }
}
