<?php

namespace App\Services\Warehouse;

use App\Models\Warehouse\InventoryItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InventoryThresholdCalculator
{
    /**
     * Reorder point = ceil(actual signed issuance units / 3 completed months).
     * No lead-time or safety-stock assumptions are applied.
     * Existing thresholds remain the fallback for insufficient history.
     *
     * @return array{issued_units:int,average_monthly_usage:float,reorder_point:?int,status:string}
     */
    public function calculate(InventoryItem $item, ?CarbonImmutable $asOf = null): array
    {
        $endExclusive = ($asOf ?? CarbonImmutable::now())->startOfMonth();
        $start = $endExclusive->subMonths(3);

        $issued = (int) DB::table('inventory_issuance_items as lines')
            ->join('inventory_issuances as issues', 'issues.id', '=', 'lines.inventory_issuance_id')
            ->where('lines.inventory_item_id', $item->getKey())
            ->whereNotNull('issues.handover_confirmed_at')
            ->whereNotNull('issues.signed_slip_path')
            ->where('issues.issued_at', '>=', $start)
            ->where('issues.issued_at', '<', $endExclusive)
            ->sum('lines.quantity');

        // Items added during the period have not had a full three-month observation.
        // Zero issued units is also not sufficient to justify replacing a threshold.
        $completeHistory = $this->hasEnoughHistory($item->created_at, $start, $issued);

        return [
            'issued_units' => $issued,
            'average_monthly_usage' => $issued / 3,
            'reorder_point' => $completeHistory ? $this->roundedMonthlyUsage($issued) : null,
            'status' => $completeHistory ? 'calculated' : 'insufficient_history',
        ];
    }
    public function roundedMonthlyUsage(int $issued): int
    {
        return intdiv(max(0, $issued) + 2, 3);
    }

    public function hasEnoughHistory(?\Carbon\CarbonInterface $createdAt, CarbonImmutable $periodStart, int $issued): bool
    {
        return $createdAt !== null && $createdAt->lessThanOrEqualTo($periodStart) && $issued > 0;
    }

    /** Determine the operational status without altering JO reservations. */
    public function stockStatus(int $onHand, int $reserved, ?int $reorderPoint): string
    {
        $available = max(0, $onHand - max(0, $reserved));

        if ($available === 0) {
            return 'Critical';
        }

        if ($reorderPoint !== null && $reorderPoint > 0 && $available <= $reorderPoint) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    /** Effective threshold is unknown for new items with no historic/manual level. */
    public function effectiveReorderPoint(InventoryItem $item, ?array $result = null): ?int
    {
        $result ??= $this->calculate($item);
        if ($result['reorder_point'] !== null) {
            return $result['reorder_point'];
        }

        return (int) $item->reorder_level > 0 ? (int) $item->reorder_level : null;
    }

}
