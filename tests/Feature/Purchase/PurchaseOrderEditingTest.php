<?php

namespace Tests\Feature\Purchase;

use App\Models\Admin\User;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderEditingTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseUser(): User
    {
        return User::factory()->create([
            'department' => 'Purchase',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function order(string $status = 'Ordered'): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_no' => 'PO-EDIT-0001',
            'po_date' => now()->toDateString(),
            'supplier_name' => 'Original Supplier',
            'items' => [[
                'pr_no' => null,
                'item_description' => 'Brake Pad',
                'quantity' => 2,
                'unit' => 'PC',
                'cost' => 500,
                'amount' => 1000,
            ]],
            'gross_amount' => 1000,
            'net_amount' => 1000,
            'status' => $status,
        ]);
    }

    private function payload(PurchaseOrder $order, string $supplier): array
    {
        return [
            'po_no' => $order->po_no,
            'po_date' => $order->po_date->toDateString(),
            'supplier_name' => $supplier,
            'status' => $order->status,
            'items' => [[
                'pr_no' => null,
                'item_description' => 'Brake Pad',
                'quantity' => 3,
                'unit' => 'PC',
                'cost' => 550,
            ]],
        ];
    }

    public function test_ordered_purchase_order_can_be_edited(): void
    {
        $user = $this->purchaseUser();
        $order = $this->order('Ordered');

        $this->actingAs($user)
            ->put(route('purchase-orders.update', $order), $this->payload($order, 'Updated Supplier'))
            ->assertRedirect('/purchase-orders');

        $this->assertSame('Updated Supplier', $order->fresh()->supplier_name);
        $this->assertSame(1650.0, (float) $order->fresh()->net_amount);
    }

    public function test_purchase_order_cannot_be_edited_after_fulfillment_starts(): void
    {
        $user = $this->purchaseUser();
        $order = $this->order('For Delivery');

        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->put(route('purchase-orders.update', $order), $this->payload($order, 'Should Not Save'))
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHas('error');

        $this->assertSame('Original Supplier', $order->fresh()->supplier_name);
        $this->assertSame('For Delivery', $order->fresh()->status);
    }
}
