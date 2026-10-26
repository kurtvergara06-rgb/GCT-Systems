<?php

namespace App\Services\Maintenance;

use App\Services\PartParser;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\ItemRequest;
use Illuminate\Validation\ValidationException;

class PurchaseRequestPartsService
{
    public function __construct(
        private PartParser $partParser,
        private JobOrderWorkflowService $workflow
    ) {}

    public function missingParts(string $partText, ?JobOrder $jobOrder = null): array
    {
        $requestedParts = $this->partParser->parsePartText($partText);
        if ($requestedParts === []) {
            return [];
        }

        $availability = $this->workflow->inventoryAvailability($requestedParts);
        $partKey = static fn (array $part): string =>
            mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($part['name'] ?? ''))).'|'.
            mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($part['unit'] ?? '')));
        $partsByName = collect($availability['parts'])->groupBy($partKey);
        // If a split IR was submitted first, purchase only the JO quantity
        // that is not already accounted for by that IR. This also remains
        // stable when Warehouse stock changes or the IR is physically issued.
        // Legacy full-JO IRs retain the live-shortage fallback.
        $irQuantities = collect();
        if ($jobOrder) {
            $ir = ItemRequest::query()
                ->where('job_order_id', $jobOrder->id)
                ->whereNotIn('status', ['Cancelled', 'Rejected'])
                ->latest('id')->first();
            if ($ir) {
                $irQuantities = collect($ir->requested_parts ?? [])
                    ->groupBy($partKey)
                    ->map(fn ($parts) => $parts->sum(fn ($part) => (int) ($part['quantity'] ?? 0)));
                $totalIr = $irQuantities->sum();
                $totalJo = collect($requestedParts)->sum('quantity');
                if ($totalIr >= $totalJo && $ir->status === 'Issued') {
                    return [];
                }
                if ($totalIr >= $totalJo) {
                    // Old IRs captured all JO parts, even missing stock.
                    $irQuantities = collect();
                }
            }
        }
        $missingParts = [];

        foreach ($partsByName as $key => $parts) {
            $units = $parts->pluck('unit')->map(fn ($unit) => mb_strtolower(trim((string) $unit)))->unique();
            if ($units->count() > 1) {
                throw ValidationException::withMessages([
                    'job_order_no' => 'The linked Job Order lists the same part with different units. Correct the Job Order before creating a Purchase Requisition.',
                ]);
            }

            $part = $parts->first();
            if ($parts->contains(fn (array $candidate) => (bool) ($candidate['ambiguous'] ?? false))) {
                throw ValidationException::withMessages([
                    'job_order_no' => "Inventory contains multiple matching records for {$part['name']}. Warehouse must resolve the inventory item before purchasing.",
                ]);
            }
            $shortage = $irQuantities->isNotEmpty()
                ? max(0, $parts->sum('quantity') - (int) $irQuantities->get($key, 0))
                : max(0, $parts->sum('quantity') - (int) $part['available']);
            if ($shortage < 1) {
                continue;
            }

            $missingParts[] = [
                'name' => $part['name'],
                'category' => $part['category'] ?: 'Uncategorized',
                'quantity' => $shortage,
                'unit' => $part['unit'],
                'availability' => 'Unavailable',
            ];
        }

        return $missingParts;
    }
}
