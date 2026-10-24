<?php

namespace App\Services\Maintenance;

use App\Services\PartParser;
use Illuminate\Validation\ValidationException;

class PurchaseRequestPartsService
{
    public function __construct(
        private PartParser $partParser,
        private JobOrderWorkflowService $workflow
    ) {}

    public function missingParts(string $partText): array
    {
        $requestedParts = $this->partParser->parsePartText($partText);
        if ($requestedParts === []) {
            return [];
        }

        $availability = $this->workflow->inventoryAvailability($requestedParts);
        $partsByName = collect($availability['parts'])->groupBy(
            fn (array $part): string => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', $part['name']))
        );
        $missingParts = [];

        foreach ($partsByName as $parts) {
            $units = $parts->pluck('unit')->map(fn ($unit) => mb_strtolower(trim((string) $unit)))->unique();
            if ($units->count() > 1) {
                throw ValidationException::withMessages([
                    'job_order_no' => 'The linked Job Order lists the same part with different units. Correct the Job Order before creating a Purchase Requisition.',
                ]);
            }

            $part = $parts->first();
            $shortage = (int) $part['shortage'];
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
