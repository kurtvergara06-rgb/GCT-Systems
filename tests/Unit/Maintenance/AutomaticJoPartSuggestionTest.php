<?php

namespace Tests\Unit\Maintenance;

use App\Services\Maintenance\ItemRequestPartsService;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Services\Maintenance\PurchaseRequestPartsService;
use App\Services\PartParser;
use Mockery;
use PHPUnit\Framework\TestCase;

class AutomaticJoPartSuggestionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function workflow(array $parts): JobOrderWorkflowService
    {
        $workflow = Mockery::mock(JobOrderWorkflowService::class);
        $workflow->shouldReceive('inventoryAvailability')
            ->once()
            ->andReturn(['status' => 'partial', 'parts' => $parts]);
        return $workflow;
    }

    public function test_partial_stock_is_split_between_ir_and_pr(): void
    {
        $stock = [['name'=>'Air Filter','quantity'=>20,'unit'=>'pcs',
            'available'=>8,'shortage'=>12,'category'=>'Filters']];
        $ir = new ItemRequestPartsService(new PartParser, $this->workflow($stock));
        $this->assertSame(8, $ir->availableParts('Air Filter - Qty: 20 pcs')[0]['quantity']);

        $pr = new PurchaseRequestPartsService(new PartParser, $this->workflow($stock));
        $this->assertSame(12, $pr->missingParts('Air Filter - Qty: 20 pcs')[0]['quantity']);
    }

    public function test_duplicate_jo_rows_do_not_double_count_pr_shortage(): void
    {
        $stock = [
            ['name'=>'Air Filter','quantity'=>10,'unit'=>'pcs','available'=>8,'shortage'=>12,'category'=>'Filters'],
            ['name'=>'Air Filter','quantity'=>10,'unit'=>'pcs','available'=>8,'shortage'=>12,'category'=>'Filters'],
        ];
        $pr = new PurchaseRequestPartsService(new PartParser, $this->workflow($stock));
        $parts = $pr->missingParts('Air Filter - Qty: 10 pcs, Air Filter - Qty: 10 pcs');
        $this->assertCount(1, $parts);
        $this->assertSame(12, $parts[0]['quantity']);
    }

    public function test_separate_units_are_not_combined(): void
    {
        $stock = [
            ['name'=>'Oil','quantity'=>2,'unit'=>'liter','available'=>5,'shortage'=>0,'category'=>'Fluid'],
            ['name'=>'Oil','quantity'=>1,'unit'=>'gallon','available'=>0,'shortage'=>1,'category'=>'Fluid'],
        ];
        $ir = new ItemRequestPartsService(new PartParser, $this->workflow($stock));
        $parts = $ir->availableParts('Oil - Qty: 2 liter, Oil - Qty: 1 gallon');
        $this->assertCount(1, $parts);
        $this->assertSame('liter', $parts[0]['unit']);
        $this->assertSame(2, $parts[0]['quantity']);
    }
}
