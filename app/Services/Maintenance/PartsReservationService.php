<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderPartReservation;
use App\Models\Warehouse\InventoryItem;
use App\Services\PartParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartsReservationService
{
    public function __construct(private PartParser $parser) {}

    public function key(string $name, string $unit): string
    {
        return mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($name))).'|'.$this->parser->normalizeUnit($unit);
    }

    /**
     * Run within the JO approval transaction. Lock inventory in stable ID order
     * so concurrent approvals cannot allocate the same units.
     * Never deduct physical stock during approval.
     */
    public function reserveForApproval(JobOrder $jo): array
    {
        $items = InventoryItem::query()->orderBy('id')->lockForUpdate()->get();
        $existing = JobOrderPartReservation::query()->where('job_order_id', $jo->id)->lockForUpdate()->get();
        // New JOs reserve at submission, including an explicit zero-stock snapshot.
        // Approval must reuse this allocation rather than reserve twice.
        if (isset($jo->inventory_snapshot['reservation_at_submission'])) {
            $byItem = $existing->pluck('quantity', 'inventory_item_id');
            $allocations = [];
            $shortages = [];
            foreach ($this->parser->parsePartText((string) $jo->part_needed) as $part) {
                $required = (int) $part['quantity'];
                $reserved = (int) $byItem->get((int) ($part['inventory_item_id'] ?? 0), 0);
                $allocations[] = ['name' => $part['name'], 'unit' => $part['unit'], 'required' => $required, 'reserved' => min($required, $reserved)];
                if ($reserved < $required) $shortages[] = $part['name'];
            }
            return ['parts' => $allocations, 'shortages' => $shortages,
                'status' => count($shortages) === 0 ? 'sufficient' : (collect($allocations)->sum('reserved') > 0 ? 'partial' : 'none'),
                'reservation_at_submission' => true];
        }
        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages(['parts_issue_strategy' => 'This Job Order already has stock reservations. Review it before approving again.']);
        }
        $committed = JobOrderPartReservation::query()->select('inventory_item_id', DB::raw('SUM(quantity) AS reserved'))
            ->groupBy('inventory_item_id')->pluck('reserved', 'inventory_item_id');

        $allocations = [];
        $shortages = [];
        $parts = collect($this->parser->parsePartText((string) $jo->part_needed))->groupBy(fn ($p) => ! empty($p['inventory_item_id']) ? 'id:'.(int) $p['inventory_item_id'] : $this->key((string) $p['name'], (string) $p['unit']));
        foreach ($parts as $group) {
            $part = $group->first();
            $required = $group->sum(fn ($p) => (int) $p['quantity']);
            $matches = ! empty($part['inventory_item_id'])
                ? $items->where('id', (int) $part['inventory_item_id'])->values()
                : $items->filter(fn ($item) => $this->key((string) ($item->parts_name ?? $item->item_name ?? ''), (string) ($item->unit ?? $item->unit_of_measurement ?? ''))
                    === $this->key((string) $part['name'], (string) $part['unit']))->values();
            if (! empty($part['inventory_item_id']) && $matches->count() !== 1) {
                throw ValidationException::withMessages(['parts_issue_strategy' => 'The selected inventory item no longer exists. Review the Job Order parts.']);
            }
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['parts_issue_strategy' => 'Multiple inventory records match '.$part['name'].'. Resolve the item before approval.']);
            }
            $item = $matches->first();
            $free = $item ? max(0, (int) ($item->quantity_available ?? $item->on_hand ?? 0) - (int) $committed->get($item->id, 0)) : 0;
            $reserved = min($required, $free);
            if ($item && $reserved > 0) {
                JobOrderPartReservation::create(['job_order_id' => $jo->id, 'inventory_item_id' => $item->id, 'quantity' => $reserved]);
            }
            $allocations[] = ['name' => $part['name'], 'unit' => $part['unit'], 'required' => $required, 'reserved' => $reserved];
            if ($reserved < $required) $shortages[] = $part['name'];
        }

        return ['parts' => $allocations, 'shortages' => $shortages, 'status' =>
            count($shortages) === 0 ? 'sufficient' : (collect($allocations)->sum('reserved') > 0 ? 'partial' : 'none')];
    }

    /**
     * Allocate stock that arrived after initial Head approval. Call while the
     * JO row is locked in a write transaction (before a follow-up IR).
     * Prior signed issuances are excluded from outstanding JO demand.
     */
    public function topUp(JobOrder $jo): void
    {
        if ($jo->review_status !== 'Approved' || $jo->status === 'Completed'
            || ! isset($jo->inventory_snapshot['shortages'])) {
            return; // legacy JOs retain their existing workflow
        }
        $items = InventoryItem::query()->orderBy('id')->lockForUpdate()->get();
        $reserved = JobOrderPartReservation::query()->where('job_order_id', $jo->id)
            ->lockForUpdate()->get()->keyBy('inventory_item_id');
        $allReserved = JobOrderPartReservation::query()
            ->select('inventory_item_id', DB::raw('SUM(quantity) AS quantity'))
            ->groupBy('inventory_item_id')->pluck('quantity', 'inventory_item_id');

        $requests = \App\Models\Maintenance\ItemRequest::query()->where('job_order_id', $jo->id)->get();
        $referenceNos = $requests->map(fn ($ir) => 'IR-'.($ir->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT));
        $issuedByItem = [];
        if ($referenceNos->isNotEmpty()) {
            $batchQuery = \App\Models\Warehouse\InventoryIssuance::query()->where(function ($query) use ($referenceNos) {
                foreach ($referenceNos as $reference) {
                    $query->orWhere('reference_no', $reference)
                        ->orWhere('reference_no', 'like', $reference.'-B%');
                }
            });
            foreach ($batchQuery->with('items')->get() as $batch) {
                foreach ($batch->items as $line) {
                    $issuedByItem[$line->inventory_item_id] =
                        ($issuedByItem[$line->inventory_item_id] ?? 0) + (int) $line->quantity;
                }
            }
        }

        $groups = collect($this->parser->parsePartText((string) $jo->part_needed))
            ->groupBy(fn ($part) => ! empty($part['inventory_item_id']) ? 'id:'.(int) $part['inventory_item_id'] : $this->key((string) $part['name'], (string) $part['unit']));
        foreach ($groups as $parts) {
            $sample = $parts->first();
            $matches = ! empty($sample['inventory_item_id'])
                ? $items->where('id', (int) $sample['inventory_item_id'])->values()
                : $items->filter(fn ($item) => $this->key(
                    (string) ($item->parts_name ?? $item->item_name ?? ''),
                    (string) ($item->unit ?? $item->unit_of_measurement ?? '')
                ) === $this->key((string) $sample['name'], (string) $sample['unit']))->values();
            if ($matches->count() !== 1) continue;
            $item = $matches->first();
            $current = $reserved->get($item->id);
            $alreadyReserved = (int) ($current?->quantity ?? 0);
            $required = $parts->sum(fn ($part) => (int) $part['quantity']);
            $remaining = max(0, $required - (int) ($issuedByItem[$item->id] ?? 0) - $alreadyReserved);
            $free = max(0, (int) ($item->quantity_available ?? $item->on_hand ?? 0) - (int) $allReserved->get($item->id, 0));
            $extra = min($remaining, $free);
            if ($extra > 0) {
                if ($current) {
                    $current->increment('quantity', $extra);
                } else {
                    JobOrderPartReservation::create([
                        'job_order_id' => $jo->id, 'inventory_item_id' => $item->id, 'quantity' => $extra,
                    ]);
                }
            }
        }
    }

    public function reservedForJo(JobOrder $jo): array
    {
        return JobOrderPartReservation::query()->where('job_order_id', $jo->id)
            ->pluck('quantity', 'inventory_item_id')->all();
    }

    public function release(JobOrder $jo): void
    {
        JobOrderPartReservation::query()->where('job_order_id', $jo->id)->delete();
    }
}
