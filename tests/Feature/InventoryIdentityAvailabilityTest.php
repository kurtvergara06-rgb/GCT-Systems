<?php

namespace Tests\Feature;

use App\Models\Warehouse\InventoryItem;
use App\Models\Maintenance\JobOrderPartReservation;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Services\PartParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIdentityAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_name_requires_explicit_inventory_identity(): void
    {
        $first = InventoryItem::create([
            'item_code' => 'TEST-OIL-A',
            'parts_name' => 'Engine Oil 15W-40',
            'item_name' => 'Engine Oil 15W-40',
            'unit' => 'liter',
            'unit_of_measurement' => 'liter',
            'quantity_available' => 513,
            'on_hand' => 513,
            'category' => 'Lubricants',
        ]);
        $second = InventoryItem::create([
            'item_code' => 'TEST-OIL-B',
            'parts_name' => 'Engine Oil 15W-40',
            'item_name' => 'Engine Oil 15W-40',
            'unit' => 'liter',
            'unit_of_measurement' => 'liter',
            'quantity_available' => 135,
            'on_hand' => 135,
            'category' => 'Lubricants',
        ]);
        $service = app(JobOrderWorkflowService::class);
        $part = ['name' => 'Engine Oil 15W-40', 'unit' => 'liter', 'quantity' => 1000];

        $ambiguous = $service->inventoryAvailability([$part]);
        $this->assertSame('none', $ambiguous['status']);
        $this->assertTrue($ambiguous['parts'][0]['ambiguous']);
        $this->assertSame(0, $ambiguous['parts'][0]['available']);

        $specific = $service->inventoryAvailability([array_merge($part, ['inventory_item_id' => $second->id])]);
        $this->assertSame('partial', $specific['status']);
        $this->assertSame(135, $specific['parts'][0]['available']);
        $this->assertSame(865, $specific['parts'][0]['shortage']);

        $legacyParts = app(PartParser::class)->parsePartText('Engine Oil 15W-40 [TEST-OIL-B] - Qty: 1000 liter');
        $this->assertSame('Engine Oil 15W-40', $legacyParts[0]['name']);
        $this->assertSame('TEST-OIL-B', $legacyParts[0]['inventory_item_code']);
        $legacySpecific = $service->inventoryAvailability($legacyParts);
        $this->assertSame('partial', $legacySpecific['status']);
        $this->assertSame($second->id, $legacySpecific['parts'][0]['inventory_item_id']);
        $this->assertSame(135, $legacySpecific['parts'][0]['available']);
    }
}
