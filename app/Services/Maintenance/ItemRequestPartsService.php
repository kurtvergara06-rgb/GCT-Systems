<?php

namespace App\Services\Maintenance;

use App\Services\PartParser;
use App\Models\Maintenance\JobOrderPartReservation;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Validation\ValidationException;

class ItemRequestPartsService
{
    public function __construct(
        private PartParser $parser,
        private JobOrderWorkflowService $workflow
    ) {}

    /** Preview of allocatable quantities. The controller reserves before storing IR. */
    public function availableParts(string $partText, ?JobOrder $jobOrder = null): array
    {
        $parsed = $this->parser->parsePartText($partText);
        if ($parsed === []) {
            return [];
        }

        $partKey = fn (array $part): string =>
            mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($part['name'] ?? ''))).'|'.
            $this->parser->normalizeUnit((string) ($part['unit'] ?? ''));
        $groups = collect($this->workflow->inventoryAvailability($parsed)['parts'])->groupBy($partKey);
        // When a PR was submitted first, its quantities must not be
        // suggested again in a later IR even if Warehouse stock changes.
        $purchaseQuantities = collect();
        if ($jobOrder) {
            $pr = PurchaseRequest::query()
                ->where('job_order_no', $jobOrder->job_order_no)
                ->where('pr_no', 'not like', '%-P')
                ->where(fn ($query) => $query->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request'))
                // Only outstanding procurement holds a requirement.
                // Once received/completed, the arriving stock may be requested by IR.
                ->whereNotIn('status', ['Rejected', 'Cancelled', 'Delivered', 'Picked Up', 'Issued'])
                ->latest('id')->first();
            if ($pr) {
                $purchaseQuantities = collect($this->parser->parsePartText((string) $pr->item))
                    ->groupBy($partKey)
                    ->map(fn ($items) => $items->sum('quantity'));
            }
        }
        $reservationBacked = $jobOrder && isset($jobOrder->inventory_snapshot['shortages']);
        $previousIrQuantities = collect();
        if ($reservationBacked) {
            $previousIrQuantities = \App\Models\Maintenance\ItemRequest::query()
                ->where('job_order_id', $jobOrder->id)
                ->whereNotIn('status', ['Cancelled', 'Rejected'])
                ->get()->flatMap(fn ($ir) => $ir->requested_parts ?? [])
                ->groupBy($partKey)
                ->map(fn ($rows) => $rows->sum(fn ($part) => (int) ($part['quantity'] ?? 0)));
        }
        $result = [];

        foreach ($groups as $key => $parts) {
            $units = $parts->pluck('unit')->map(fn ($unit) => $this->parser->normalizeUnit((string) $unit))->unique();
            if ($units->count() !== 1) {
                throw ValidationException::withMessages([
                    'job_order_id' => 'This Job Order contains the same part with different units. Correct it before requesting parts.',
                ]);
            }
            $first = $parts->first();
            $needed = $parts->sum('quantity');
            // A still-open PR and prior IRs both cover portions of the JO
            // requirement. Do not suggest either portion again after restock.
            $committedIr = (int) $previousIrQuantities->get($key, 0);
            $committedPr = (int) $purchaseQuantities->get($key, 0);
            $remainingAfterPr = max(0, $needed - $committedIr - $committedPr);
            $reserved = $jobOrder ? JobOrderPartReservation::query()->where('job_order_id', $jobOrder->id)
                ->where('inventory_item_id', $first['inventory_item_id'] ?? 0)->value('quantity') : null;
            $free = max(0, (int) $first['available']);
            $available = $reservationBacked
                ? min($remainingAfterPr, max(0, (int) $reserved) + $free)
                : min($remainingAfterPr, max(0, (int) ($reserved ?? $free)));
            if ($available > 0) {
                $result[] = [
                    'name' => $first['name'],
                    'quantity' => $available,
                    'unit' => $first['unit'],
                ];
            }
        }
        return $result;
    }
}
