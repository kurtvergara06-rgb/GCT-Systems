<?php

namespace App\Services\Maintenance;

use App\Models\Admin\ActivityLog;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\MechanicAttendance;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use App\Services\Warehouse\InventoryLedgerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LinkedJobOrderResetService
{
    public function __construct(private readonly InventoryLedgerService $ledger) {}

    /**
     * Build an evidence-backed reset plan without changing data.
     *
     * @return array<string, mixed>
     */
    public function plan(string $jobOrderNo, bool $lock = false): array
    {
        $jobOrderQuery = JobOrder::query()->where('job_order_no', $jobOrderNo);

        if ($lock) {
            $jobOrderQuery->lockForUpdate();
        }

        $jobOrder = $jobOrderQuery->first();

        if (! $jobOrder) {
            return $this->missingPlan($jobOrderNo);
        }

        $purchaseRequests = DB::table('purchase_requests')
            ->where('job_order_no', $jobOrderNo)
            ->orderBy('id')
            ->get();
        $prIds = $purchaseRequests->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $prNos = $purchaseRequests->pluck('pr_no')->map(fn ($value): string => trim((string) $value))->filter()->values();
        $normalizedPrNos = $prNos->map(fn (string $value): string => $this->normalizePrNo($value))->unique()->values();

        $purchaseOrders = PurchaseOrder::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseOrder $order): bool => $this->purchaseOrderTouchesRequests($order, $prIds, $normalizedPrNos))
            ->values();

        $poIds = $purchaseOrders->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $poNos = $purchaseOrders->pluck('po_no')->map(fn ($value): string => trim((string) $value))->filter()->values();

        $scheduledPurchases = $poIds->isEmpty()
            ? collect()
            : DB::table('scheduled_purchases')->whereIn('last_po_id', $poIds)->orderBy('id')->get();

        $issuanceReferences = collect([$jobOrderNo])->merge($prNos)->merge($poNos)->unique()->values();
        $issuances = DB::table('inventory_issuances')
            ->whereIn('reference_no', $issuanceReferences)
            ->orderBy('id')
            ->get();
        $issuanceItems = $issuances->isEmpty()
            ? collect()
            : DB::table('inventory_issuance_items')
                ->whereIn('inventory_issuance_id', $issuances->pluck('id'))
                ->orderBy('id')
                ->get();

        $movementReferences = collect([$jobOrderNo])
            ->merge($prNos)
            ->merge($poNos)
            ->merge($issuances->pluck('issue_no'))
            ->filter()
            ->unique()
            ->values();
        $movements = StockMovement::query()
            ->whereIn('reference_no', $movementReferences)
            ->orderBy('id')
            ->get();

        $pms = $jobOrder->pms_schedule_id
            ? DB::table('pms_schedules')->where('id', $jobOrder->pms_schedule_id)->first()
            : null;
        $pmsOtherJobOrders = $pms
            ? JobOrder::query()->where('pms_schedule_id', $pms->id)->whereKeyNot($jobOrder->id)->count()
            : 0;
        $referral = $jobOrder->maintenance_referral_id
            ? DB::table('maintenance_referrals')->where('id', $jobOrder->maintenance_referral_id)->first()
            : null;
        $incidentId = $jobOrder->incident_id ?: ($referral->incident_id ?? null);
        $incident = $incidentId ? DB::table('incidents')->where('id', $incidentId)->first() : null;

        $reasons = collect();

        if ($jobOrder->status === 'Completed' || $jobOrder->completion_date !== null) {
            $reasons->push('Selected Job Order is completed historical data');
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            $sharedReason = $this->sharedPurchaseOrderReason($purchaseOrder, $prIds, $normalizedPrNos);

            if ($sharedReason) {
                $reasons->push($sharedReason);
            }
        }

        $movementSafety = $this->movementSafety($purchaseOrders, $movements);
        $reasons = $reasons->merge($movementSafety['reasons'])->unique()->values();

        $action = $reasons->isNotEmpty()
            ? 'BLOCK'
            : ($movements->isNotEmpty() ? 'READY WITH INVENTORY REVERSAL' : 'READY');

        return [
            'action' => $action,
            'reason' => $reasons->isEmpty()
                ? ($movements->isEmpty() ? 'Exact linked workflow can be reset safely' : 'Inventory effects are fully traceable and reversible')
                : $reasons->implode('; '),
            'job_order_id' => (int) $jobOrder->id,
            'job_order_no' => (string) $jobOrder->job_order_no,
            'job_order_status' => (string) $jobOrder->status,
            'mechanic' => trim((string) ($jobOrder->assigned_mechanic ?? '')),
            'pms' => $pms,
            'pms_other_job_orders' => $pmsOtherJobOrders,
            'referral' => $referral,
            'incident' => $incident,
            'purchase_requests' => $purchaseRequests,
            'purchase_orders' => $purchaseOrders,
            'scheduled_purchases' => $scheduledPurchases,
            'issuances' => $issuances,
            'issuance_items' => $issuanceItems,
            'movements' => $movements,
            'inventory_effects' => $this->inventoryEffectSummary($movements),
            'notification_count' => $this->notificationQuery($jobOrder, $purchaseRequests, $purchaseOrders)->count(),
        ];
    }

    /**
     * Reset one Job Order atomically after rebuilding its plan under locks.
     *
     * @return array<string, mixed>
     */
    public function reset(string $jobOrderNo): array
    {
        return DB::transaction(function () use ($jobOrderNo): array {
            $plan = $this->plan($jobOrderNo, true);

            if ($plan['action'] === 'BLOCK') {
                throw new RuntimeException($plan['reason']);
            }

            if ($plan['job_order_id'] === null) {
                throw new RuntimeException('Job Order no longer exists.');
            }

            $jobOrder = JobOrder::query()->whereKey($plan['job_order_id'])->lockForUpdate()->firstOrFail();
            $movementIds = $plan['movements']->pluck('id')->map(fn ($id): int => (int) $id)->all();

            foreach ($movementIds as $movementId) {
                $movement = StockMovement::query()->whereKey($movementId)->lockForUpdate()->firstOrFail();
                $item = InventoryItem::query()->whereKey($movement->inventory_item_id)->lockForUpdate()->first();

                if (! $item) {
                    throw new RuntimeException("Inventory item for movement #{$movement->id} no longer exists.");
                }

                $quantity = abs((int) $movement->quantity_change);
                $reference = 'RESET-'.$jobOrder->job_order_no;
                $remarks = "Compensating reversal for stock movement #{$movement->id} ({$movement->reference_no}).";

                if ((int) $movement->quantity_change > 0) {
                    $this->ledger->stockOut($item, $quantity, $reference, $remarks);
                } else {
                    $this->ledger->stockIn($item, $quantity, $reference, $remarks);
                }
            }

            $purchaseRequestIds = $plan['purchase_requests']->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $purchaseOrderIds = $plan['purchase_orders']->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $issuanceIds = $plan['issuances']->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $this->notificationQuery($jobOrder, $plan['purchase_requests'], $plan['purchase_orders'])->delete();

            if ($issuanceIds !== []) {
                DB::table('inventory_issuance_items')->whereIn('inventory_issuance_id', $issuanceIds)->delete();
                DB::table('inventory_issuances')->whereIn('id', $issuanceIds)->delete();
            }

            if ($purchaseOrderIds !== []) {
                DB::table('scheduled_purchases')->whereIn('last_po_id', $purchaseOrderIds)->update([
                    'last_po_id' => null,
                    'last_purchased_at' => null,
                    'updated_at' => now(),
                ]);
                DB::table('purchase_orders')->whereIn('id', $purchaseOrderIds)->delete();
            }

            if ($purchaseRequestIds !== []) {
                DB::table('purchase_requests')->whereIn('id', $purchaseRequestIds)->delete();
            }

            if ($plan['referral']) {
                DB::table('maintenance_referrals')->where('id', $plan['referral']->id)->update([
                    'status' => 'Approved',
                    'updated_at' => now(),
                ]);
            }

            $mechanic = trim((string) ($jobOrder->assigned_mechanic ?? ''));
            $busNo = (string) $jobOrder->bus_no;
            $jobOrder->delete();
            $mechanicReleased = $this->releaseMechanicIfAvailable($mechanic, $jobOrderNo);
            $releasedBus = $this->releaseBusIfAvailable($busNo);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name ?? 'System Console',
                'user_role' => auth()->user()?->role ?? 'System Admin',
                'department' => 'Maintenance',
                'activity' => 'Reset linked Job Order workflow',
                'module' => 'Maintenance',
                'reference' => $jobOrderNo,
                'event_type' => 'Deleted',
                'details' => sprintf(
                    'Reset %s with %d PR(s), %d PO(s), and %d compensating inventory reversal(s). PMS/referral source records were preserved for reuse.',
                    $jobOrderNo,
                    count($purchaseRequestIds),
                    count($purchaseOrderIds),
                    count($movementIds)
                ),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return [
                'job_order_no' => $jobOrderNo,
                'purchase_requests_deleted' => count($purchaseRequestIds),
                'purchase_orders_deleted' => count($purchaseOrderIds),
                'issuances_deleted' => count($issuanceIds),
                'inventory_reversals' => count($movementIds),
                'mechanic_released' => $mechanicReleased,
                'bus_released_id' => $releasedBus?->id,
            ];
        }, 3);
    }

    /**
     * Delete a rejected Job Order workflow while preserving unrelated records
     * inside shared Purchase Orders.
     *
     * This path is intentionally stricter than a raw delete:
     * - the Job Order itself must currently be rejected;
     * - at least one Maintenance Purchase Request must be rejected;
     * - any stock movement or inventory issuance touching the JO/PR/PO chain blocks deletion;
     * - shared POs are preserved and only target PR item lines are detached;
     * - an exclusive inventory-posted PO without ledger evidence remains blocked.
     *
     * @return array<string, mixed>
     */
    public function deleteRejectedWorkflow(string $jobOrderNo): array
    {
        return DB::transaction(function () use ($jobOrderNo): array {
            $jobOrder = JobOrder::query()
                ->where('job_order_no', $jobOrderNo)
                ->lockForUpdate()
                ->first();

            if (! $jobOrder) {
                throw new RuntimeException('Job Order no longer exists.');
            }

            if ($jobOrder->part_status !== 'Rejected') {
                throw new RuntimeException('Only a Job Order with a rejected Purchase Request can use this cleanup.');
            }

            $purchaseRequests = DB::table('purchase_requests')
                ->where('job_order_no', $jobOrderNo)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($purchaseRequests->isEmpty()) {
                throw new RuntimeException('No linked Purchase Request was found.');
            }

            $hasRejectedMaintenanceRequest = $purchaseRequests->contains(function ($purchaseRequest): bool {
                $prNo = strtoupper(trim((string) ($purchaseRequest->pr_no ?? '')));
                $sourceType = trim((string) ($purchaseRequest->source_type ?? ''));

                return $purchaseRequest->status === 'Rejected'
                    && ! preg_match('/-P(?:\d+)?$/i', $prNo)
                    && ($sourceType === '' || $sourceType === 'Maintenance Request');
            });

            if (! $hasRejectedMaintenanceRequest) {
                throw new RuntimeException('No rejected Maintenance Purchase Request was found for this Job Order.');
            }

            $prIds = $purchaseRequests
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            $prNos = $purchaseRequests
                ->pluck('pr_no')
                ->map(fn ($value): string => trim((string) $value))
                ->filter()
                ->values();

            $normalizedPrNos = $prNos
                ->map(fn (string $value): string => $this->normalizePrNo($value))
                ->filter()
                ->unique()
                ->values();

            $purchaseOrders = PurchaseOrder::query()
                ->orderBy('id')
                ->get()
                ->filter(
                    fn (PurchaseOrder $order): bool => $this->purchaseOrderTouchesRequests(
                        $order,
                        $prIds,
                        $normalizedPrNos
                    )
                )
                ->values();

            $poNos = $purchaseOrders
                ->pluck('po_no')
                ->map(fn ($value): string => trim((string) $value))
                ->filter()
                ->values();

            $references = collect([$jobOrderNo])
                ->merge($prNos)
                ->merge($poNos)
                ->filter()
                ->unique()
                ->values();

            $issuances = DB::table('inventory_issuances')
                ->whereIn('reference_no', $references)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($issuances->isNotEmpty()) {
                throw new RuntimeException(
                    'Warehouse issuance records already exist for this workflow.'
                );
            }

            $movements = StockMovement::query()
                ->whereIn('reference_no', $references)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($movements->isNotEmpty()) {
                throw new RuntimeException(
                    'Inventory stock movements already exist for this workflow.'
                );
            }

            $deletedPurchaseOrderIds = [];
            $detachedPurchaseOrderIds = [];

            foreach ($purchaseOrders as $purchaseOrderSnapshot) {
                $purchaseOrder = PurchaseOrder::query()
                    ->whereKey($purchaseOrderSnapshot->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $items = collect($purchaseOrder->items ?? []);

                $remainingItems = $items
                    ->reject(function ($item) use ($normalizedPrNos): bool {
                        $itemPrNo = $this->normalizePrNo(
                            (string) ($item['pr_no'] ?? '')
                        );

                        return $itemPrNo !== ''
                            && $normalizedPrNos->contains($itemPrNo);
                    })
                    ->values();

                if ($remainingItems->isEmpty()) {
                    if ($purchaseOrder->inventory_posted_at) {
                        throw new RuntimeException(
                            "{$purchaseOrder->po_no} is inventory-posted but has no unrelated PO lines to preserve."
                        );
                    }

                    DB::table('scheduled_purchases')
                        ->where('last_po_id', $purchaseOrder->id)
                        ->update([
                            'last_po_id' => null,
                            'last_purchased_at' => null,
                            'updated_at' => now(),
                        ]);

                    DB::table('topbar_notifications')
                        ->where('entity', 'PurchaseOrder')
                        ->where('record_id', (string) $purchaseOrder->id)
                        ->delete();

                    $deletedPurchaseOrderIds[] = (int) $purchaseOrder->id;
                    $purchaseOrder->delete();

                    continue;
                }

                $replacementPurchaseRequestId = $purchaseOrder->purchase_request_id;

                if (
                    $replacementPurchaseRequestId
                    && $prIds->contains((int) $replacementPurchaseRequestId)
                ) {
                    $replacementPurchaseRequestId = $this
                        ->replacementPurchaseRequestId(
                            $remainingItems,
                            $prIds
                        );
                }

                $grossAmount = round(
                    $remainingItems->sum(function ($item): float {
                        if (array_key_exists('amount', $item)) {
                            return (float) $item['amount'];
                        }

                        return (float) ($item['quantity'] ?? 0)
                            * (float) ($item['cost'] ?? 0);
                    }),
                    2
                );

                $netAmount = round(
                    $grossAmount
                    + (float) $purchaseOrder->delivery_fee
                    - (float) $purchaseOrder->discount
                    + (float) $purchaseOrder->vat,
                    2
                );

                $purchaseOrder->update([
                    'purchase_request_id' => $replacementPurchaseRequestId,
                    'items' => $remainingItems->all(),
                    'gross_amount' => $grossAmount,
                    'net_amount' => $netAmount,
                ]);

                $detachedPurchaseOrderIds[] = (int) $purchaseOrder->id;
            }

            DB::table('topbar_notifications')
                ->where(function ($query) use ($jobOrder, $prIds): void {
                    $query
                        ->where(function ($jobQuery) use ($jobOrder): void {
                            $jobQuery
                                ->where('entity', 'JobOrder')
                                ->where('record_id', (string) $jobOrder->id);
                        })
                        ->orWhere(function ($requestQuery) use ($prIds): void {
                            $requestQuery
                                ->where('entity', 'PurchaseRequest')
                                ->whereIn(
                                    'record_id',
                                    $prIds->map(fn ($id): string => (string) $id)
                                );
                        });
                })
                ->delete();

            DB::table('purchase_requests')
                ->whereIn('id', $prIds->all())
                ->delete();

            if ($jobOrder->maintenance_referral_id) {
                DB::table('maintenance_referrals')
                    ->where('id', $jobOrder->maintenance_referral_id)
                    ->update([
                        'status' => 'Approved',
                        'updated_at' => now(),
                    ]);
            }

            $jobOrderId = (int) $jobOrder->id;
            $mechanic = trim((string) ($jobOrder->assigned_mechanic ?? ''));
            $busNo = (string) $jobOrder->bus_no;

            $jobOrder->delete();

            $mechanicReleased = $this->releaseMechanicIfAvailable(
                $mechanic,
                $jobOrderNo
            );
            $releasedBus = $this->releaseBusIfAvailable($busNo);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name ?? 'System Console',
                'user_role' => auth()->user()?->role ?? 'System Admin',
                'department' => 'Maintenance',
                'activity' => 'Delete rejected Job Order workflow',
                'module' => 'Maintenance',
                'reference' => $jobOrderNo,
                'event_type' => 'Deleted',
                'details' => sprintf(
                    'Deleted rejected %s with %d linked PR(s); deleted %d exclusive PO(s) and detached this workflow from %d shared PO(s). No inventory movements or warehouse issuances were removed.',
                    $jobOrderNo,
                    $prIds->count(),
                    count($deletedPurchaseOrderIds),
                    count($detachedPurchaseOrderIds)
                ),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return [
                'job_order_id' => $jobOrderId,
                'job_order_no' => $jobOrderNo,
                'purchase_requests_deleted' => $prIds->count(),
                'purchase_orders_deleted' => count($deletedPurchaseOrderIds),
                'purchase_orders_detached' => count($detachedPurchaseOrderIds),
                'mechanic_released' => $mechanicReleased,
                'bus_released_id' => $releasedBus?->id,
            ];
        }, 3);
    }

    /**
     * Pick a surviving PR as the primary PO link when a shared PO previously
     * pointed at a PR that belongs to the rejected Job Order.
     *
     * @param  Collection<int, mixed>  $remainingItems
     * @param  Collection<int, int>  $excludedPrIds
     */
    private function replacementPurchaseRequestId(
        Collection $remainingItems,
        Collection $excludedPrIds
    ): ?int {
        $remainingPrNos = $remainingItems
            ->map(
                fn ($item): string => $this->normalizePrNo(
                    (string) ($item['pr_no'] ?? '')
                )
            )
            ->filter()
            ->unique()
            ->values();

        if ($remainingPrNos->isEmpty()) {
            return null;
        }

        $candidate = DB::table('purchase_requests')
            ->whereNotIn('id', $excludedPrIds->all())
            ->orderBy('id')
            ->get(['id', 'pr_no'])
            ->first(
                fn ($purchaseRequest): bool => $remainingPrNos->contains(
                    $this->normalizePrNo((string) $purchaseRequest->pr_no)
                )
            );

        return $candidate
            ? (int) $candidate->id
            : null;
    }

    /** @return array<string, mixed> */
    private function missingPlan(string $jobOrderNo): array
    {
        return [
            'action' => 'BLOCK',
            'reason' => 'Exact Job Order was not found',
            'job_order_id' => null,
            'job_order_no' => $jobOrderNo,
            'job_order_status' => 'Missing',
            'mechanic' => '',
            'pms' => null,
            'pms_other_job_orders' => 0,
            'referral' => null,
            'incident' => null,
            'purchase_requests' => collect(),
            'purchase_orders' => collect(),
            'scheduled_purchases' => collect(),
            'issuances' => collect(),
            'issuance_items' => collect(),
            'movements' => collect(),
            'inventory_effects' => 'None',
            'notification_count' => 0,
        ];
    }

    /**
     * @param  Collection<int, int>  $prIds
     * @param  Collection<int, string>  $normalizedPrNos
     */
    private function purchaseOrderTouchesRequests(PurchaseOrder $order, Collection $prIds, Collection $normalizedPrNos): bool
    {
        if ($order->purchase_request_id && $prIds->contains((int) $order->purchase_request_id)) {
            return true;
        }

        return collect($order->items ?? [])->contains(function ($item) use ($normalizedPrNos): bool {
            $prNo = $this->normalizePrNo((string) ($item['pr_no'] ?? ''));

            return $prNo !== '' && $normalizedPrNos->contains($prNo);
        });
    }

    /**
     * @param  Collection<int, int>  $prIds
     * @param  Collection<int, string>  $normalizedPrNos
     */
    private function sharedPurchaseOrderReason(PurchaseOrder $order, Collection $prIds, Collection $normalizedPrNos): ?string
    {
        if ($order->purchase_request_id && ! $prIds->contains((int) $order->purchase_request_id)) {
            return "{$order->po_no} is shared: its primary Purchase Request is outside this Job Order";
        }

        $items = collect($order->items ?? []);
        $hasExplicitPrNumbers = $items->contains(fn ($item): bool => trim((string) ($item['pr_no'] ?? '')) !== '');

        if ($hasExplicitPrNumbers && $items->contains(function ($item) use ($normalizedPrNos): bool {
            $prNo = $this->normalizePrNo((string) ($item['pr_no'] ?? ''));

            return $prNo === '' || ! $normalizedPrNos->contains($prNo);
        })) {
            return "{$order->po_no} contains line items for unrelated Purchase Requests";
        }

        return null;
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     * @param  Collection<int, StockMovement>  $movements
     * @return array{reasons: Collection<int, string>}
     */
    private function movementSafety(Collection $purchaseOrders, Collection $movements): array
    {
        $reasons = collect();

        foreach ($movements as $movement) {
            $change = (int) $movement->quantity_change;

            if (! $movement->inventory_item_id || ! $movement->inventoryItem) {
                $reasons->push("Stock movement #{$movement->id} has no existing inventory item");

                continue;
            }

            if ($change === 0 || (int) $movement->new_stock !== (int) $movement->previous_stock + $change) {
                $reasons->push("Stock movement #{$movement->id} fails the ledger invariant");

                continue;
            }

            $current = (int) ($movement->inventoryItem->quantity_available ?? $movement->inventoryItem->on_hand ?? 0);

            if ($change > 0 && $current < $change) {
                $reasons->push("Cannot reverse stock movement #{$movement->id}: current stock {$current} is below {$change}");
            }
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            if (! $purchaseOrder->inventory_posted_at) {
                continue;
            }

            $poMovements = $movements
                ->where('reference_no', $purchaseOrder->po_no)
                ->filter(fn (StockMovement $movement): bool => (int) $movement->quantity_change > 0)
                ->values();

            if ($poMovements->isEmpty()) {
                $reasons->push("{$purchaseOrder->po_no} is inventory-posted but has no matching Stock In ledger records");

                continue;
            }

            $expected = $this->expectedPurchaseOrderInventory($purchaseOrder);
            $actual = $poMovements
                ->groupBy(fn (StockMovement $movement): string => $this->normalizeItemName($movement->item_name))
                ->map(fn (Collection $group): int => $group->sum(fn (StockMovement $movement): int => (int) $movement->quantity_change))
                ->sortKeys();

            if ($expected->all() !== $actual->all()) {
                $reasons->push("{$purchaseOrder->po_no} inventory movements do not exactly match its item lines");
            }
        }

        return ['reasons' => $reasons->unique()->values()];
    }

    /** @return Collection<string, int> */
    private function expectedPurchaseOrderInventory(PurchaseOrder $purchaseOrder): Collection
    {
        $expected = collect();

        foreach ($purchaseOrder->items ?? [] as $item) {
            $description = trim((string) ($item['item_description'] ?? $item['item'] ?? ''));
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            foreach (explode(',', $description) as $name) {
                $normalized = $this->normalizeItemName($name);

                if ($normalized !== '') {
                    $expected[$normalized] = (int) ($expected[$normalized] ?? 0) + $quantity;
                }
            }
        }

        return $expected->sortKeys();
    }

    private function inventoryEffectSummary(Collection $movements): string
    {
        if ($movements->isEmpty()) {
            return 'None';
        }

        return $movements->map(function (StockMovement $movement): string {
            $change = (int) $movement->quantity_change;
            $signed = $change > 0 ? '+'.$change : (string) $change;

            return "{$signed} {$movement->item_name} [{$movement->reference_no}] => reverse ".(-$change);
        })->implode('; ');
    }

    private function notificationQuery(JobOrder $jobOrder, Collection $purchaseRequests, Collection $purchaseOrders)
    {
        return DB::table('topbar_notifications')->where(function ($query) use ($jobOrder, $purchaseRequests, $purchaseOrders): void {
            $query->where(function ($jobQuery) use ($jobOrder): void {
                $jobQuery->where('entity', 'JobOrder')->where('record_id', (string) $jobOrder->id);
            });

            if ($purchaseRequests->isNotEmpty()) {
                $query->orWhere(function ($requestQuery) use ($purchaseRequests): void {
                    $requestQuery->where('entity', 'PurchaseRequest')->whereIn('record_id', $purchaseRequests->pluck('id')->map(fn ($id): string => (string) $id));
                });
            }

            if ($purchaseOrders->isNotEmpty()) {
                $query->orWhere(function ($orderQuery) use ($purchaseOrders): void {
                    $orderQuery->where('entity', 'PurchaseOrder')->whereIn('record_id', $purchaseOrders->pluck('id')->map(fn ($id): string => (string) $id));
                });
            }
        });
    }

    private function releaseMechanicIfAvailable(string $mechanicName, string $jobOrderNo): bool
    {
        if ($mechanicName === '') {
            return false;
        }

        $stillAssigned = JobOrder::query()
            ->where('assigned_mechanic', $mechanicName)
            ->where('status', '!=', 'Completed')
            ->exists();

        if ($stillAssigned) {
            return false;
        }

        $attendance = MechanicAttendance::query()
            ->where('mechanic_name', $mechanicName)
            ->whereDate('attendance_date', today())
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $attendance || $attendance->status !== 'On Duty') {
            return false;
        }

        $updates = ['status' => 'Present'];

        if (trim((string) $attendance->assigned_job) === $jobOrderNo) {
            $updates['assigned_job'] = null;
        }

        $attendance->update($updates);

        return true;
    }

    private function releaseBusIfAvailable(string $busNo): ?Bus
    {
        $bus = Bus::query()
            ->where('bus_no', $busNo)
            ->lockForUpdate()
            ->first();

        if (! $bus || $bus->status !== 'Under Maintenance') {
            return null;
        }

        $hasActiveJobOrder = JobOrder::query()
            ->where('bus_no', $busNo)
            ->where('status', '!=', 'Completed')
            ->exists();

        if ($hasActiveJobOrder) {
            return null;
        }

        $bus->update(['status' => 'Active']);

        return $bus->fresh();
    }

    private function normalizePrNo(string $prNo): string
    {
        return strtoupper((string) preg_replace('/-P(?:\d+)?$/i', '', trim($prNo)));
    }

    private function normalizeItemName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replaceMatches('/\s+/', ' ')->toString();
    }
}
