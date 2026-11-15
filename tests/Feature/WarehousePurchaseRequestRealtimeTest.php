<?php

namespace Tests\Feature;

use App\Events\SystemDataUpdated;
use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Maintenance\JobOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WarehousePurchaseRequestRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarding_purchase_request_dispatches_system_data_updated_event()
    {
        Event::fake([SystemDataUpdated::class]);

        $head = User::factory()->create(['department' => 'Warehouse', 'role' => 'head']);

        JobOrder::create([
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => 'BUS-001',
            'problem_issue' => 'Part A replacement',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'review_status' => 'Approved',
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => 'PR-2026-0001',
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => 'BUS-001',
            'item' => 'Part A - Qty: 1',
            'quantity' => 1,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => 'Test purchase request',
        ]);

        $response = $this
            ->actingAs($head)
            ->post(route('part-requests.send-to-purchase', $purchaseRequest));

        $response->assertRedirect();

        Event::assertDispatched(SystemDataUpdated::class, function (SystemDataUpdated $event) use ($purchaseRequest) {
            return $event->module === 'Warehouse'
                && $event->entity === 'PurchaseRequest'
                && $event->action === 'sent_to_purchase'
                && $event->record_id === $purchaseRequest->id
                && str_contains($event->message, 'forwarded an existing Maintenance PR');
        });

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'For Purchase',
        ]);
    }
}
