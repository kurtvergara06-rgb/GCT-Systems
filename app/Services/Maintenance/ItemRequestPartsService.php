<?php

namespace App\Services\Maintenance;

use App\Services\PartParser;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Validation\ValidationException;

class ItemRequestPartsService
{
    public function __construct(
        private PartParser $parser,
        private JobOrderWorkflowService $workflow
    ) {}

    /** Suggested warehouse quantities, never a stock reservation. */
    public function availableParts(string $partText, ?JobOrder $jobOrder = null): array
    {
        $parsed = $this->parser->parsePartText($partText);
        if ($parsed === []) {
            return [];
        }

        $partKey = static fn (array $part): string =>
            mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($part['name'] ?? ''))).'|'.
            mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($part['unit'] ?? '')));
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
                ->whereNotIn('status', ['Rejected', 'Cancelled'])
                ->latest('id')->first();
            if ($pr) {
                $purchaseQuantities = collect($this->parser->parsePartText((string) $pr->item))
                    ->groupBy($partKey)
                    ->map(fn ($items) => $items->sum('quantity'));
            }
        }
        $result = [];

        foreach ($groups as $key => $parts) {
            $units = $parts->pluck('unit')->map(fn ($unit) => mb_strtolower(trim((string) $unit)))->unique();
            if ($units->count() !== 1) {
                throw ValidationException::withMessages([
                    'job_order_id' => 'This Job Order contains the same part with different units. Correct it before requesting parts.',
                ]);
            }
            $first = $parts->first();
            $needed = $parts->sum('quantity');
            $remainingAfterPr = max(0, $needed - (int) $purchaseQuantities->get($key, 0));
            $available = min($remainingAfterPr, max(0, (int) $first['available']));
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
