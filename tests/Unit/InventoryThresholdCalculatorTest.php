<?php

namespace Tests\Unit;

use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryThresholdCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class InventoryThresholdCalculatorTest extends TestCase
{
    public function test_rounded_monthly_usage_uses_three_months_and_rounds_up(): void
    {
        $calculator = new InventoryThresholdCalculator();

        $this->assertSame(0, $calculator->roundedMonthlyUsage(0));
        $this->assertSame(1, $calculator->roundedMonthlyUsage(1));
        $this->assertSame(10, $calculator->roundedMonthlyUsage(30));
        $this->assertSame(11, $calculator->roundedMonthlyUsage(31));
    }

    public function test_requires_full_three_month_observation_and_actual_issuance(): void
    {
        $calculator = new InventoryThresholdCalculator();
        $start = CarbonImmutable::parse('2026-08-01 00:00:00');

        $this->assertFalse($calculator->hasEnoughHistory(null, $start, 12));
        $this->assertFalse($calculator->hasEnoughHistory(CarbonImmutable::parse('2026-09-01'), $start, 12));
        $this->assertFalse($calculator->hasEnoughHistory(CarbonImmutable::parse('2026-07-01'), $start, 0));
        $this->assertTrue($calculator->hasEnoughHistory(CarbonImmutable::parse('2026-08-01'), $start, 12));
    }

    public function test_stock_status_uses_free_available_without_changing_reservations(): void
    {
        $calculator = new InventoryThresholdCalculator();

        $this->assertSame('Low Stock', $calculator->stockStatus(20, 12, 10));
        $this->assertSame('Critical', $calculator->stockStatus(20, 20, 10));
        $this->assertSame('In Stock', $calculator->stockStatus(20, 12, null));
        $this->assertSame('In Stock', $calculator->stockStatus(20, 0, 10));
    }

    public function test_pending_items_have_no_effective_reorder_point(): void
    {
        $calculator = new InventoryThresholdCalculator();
        $item = new InventoryItem(['reorder_level' => 0]);

        $this->assertNull($calculator->effectiveReorderPoint($item, ['reorder_point' => null]));
        $item->reorder_level = 7;
        $this->assertSame(7, $calculator->effectiveReorderPoint($item, ['reorder_point' => null]));
        $this->assertSame(10, $calculator->effectiveReorderPoint($item, ['reorder_point' => 10]));
    }
}
