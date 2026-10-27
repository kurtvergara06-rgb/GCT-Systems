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
        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages(['parts_issue_strategy' => 'This Job Order already has stock reservations. Review it before approving again.']);
        }
        $committed = JobOrderPartReservation::query()->select('inventory_item_id', DB::raw('SUM(quantity) AS reserved'))
            ->groupBy('inventory_item_id')->pluck('reserved', 'inventory_item_id');

        $allocations = [];
        $shortages = [];
        $parts = collect($this->parser->parsePartText((string) $jo->part_needed))->groupBy(fn ($p) => $this->key((string) $p['name'], (string) $p['unit']));
        foreach ($parts as $group) {
            $part = $group->first();
            $required = $group->sum(fn ($p) => (int) $p['quantity']);
            $matches = $items->filter(fn ($item) => $this->key((string) ($item->parts_name ?? $item->item_name ?? ''), (string) ($item->unit ?? $item->unit_of_measurement ?? ''))
                === $this->key((string) $part['name'], (string) $part['unit']))->values();
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
