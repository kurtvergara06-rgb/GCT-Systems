<?php

namespace Tests\Feature;

use App\Events\SystemDataUpdated;
use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WarehousePurchaseRequestRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_purchase_request_issue_dispatches_system_data_updated_event()
    {
        Event::fake([SystemDataUpdated::class]);

        $user = User::factory()->create();

        $inventoryItem = InventoryItem::create([
            'item_code' => 'PART-A',
            'item_name' => 'Part A',
            'category' => 'Parts',
            'quantity_available' => 10,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
            'supplier' => 'Test Supplier',
            'storage_location' => 'Warehouse 1',
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => 'PR-2026-0001',
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => 'BUS-001',
            'item' => 'Part A - Qty: 1',
            'quantity' => 1,
            'status' => 'Approved',
            'remarks' => 'Test purchase request',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('part-requests.issue', $purchaseRequest));

        $response->assertRedirect();

        Event::assertDispatched(SystemDataUpdated::class, function (SystemDataUpdated $event) use ($purchaseRequest) {
            return $event->module === 'Warehouse'
                && $event->entity === 'PurchaseRequest'
                && $event->action === 'status_updated'
                && $event->record_id === $purchaseRequest->id
                && str_contains($event->message, 'Warehouse issued a purchase request');
        });

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'Issued',
        ]);
    }
}
