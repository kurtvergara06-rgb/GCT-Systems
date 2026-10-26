<?php

namespace App\Services\Maintenance;

use App\Services\PartParser;
use Illuminate\Validation\ValidationException;

class ItemRequestPartsService
{
    public function __construct(
        private PartParser $parser,
        private JobOrderWorkflowService $workflow
    ) {}

    /** Suggested warehouse quantities, never a stock reservation. */
    public function availableParts(string $partText): array
    {
        $parsed = $this->parser->parsePartText($partText);
        if ($parsed === []) {
            return [];
        }

        $groups = collect($this->workflow->inventoryAvailability($parsed)['parts'])
            ->groupBy(fn (array $part): string => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', $part['name'])));
        $result = [];

        foreach ($groups as $parts) {
            $units = $parts->pluck('unit')->map(fn ($unit) => mb_strtolower(trim((string) $unit)))->unique();
            if ($units->count() !== 1) {
                throw ValidationException::withMessages([
                    'job_order_id' => 'This Job Order contains the same part with different units. Correct it before requesting parts.',
                ]);
            }
            $first = $parts->first();
            $needed = $parts->sum('quantity');
            $available = min($needed, max(0, (int) $first['available']));
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
