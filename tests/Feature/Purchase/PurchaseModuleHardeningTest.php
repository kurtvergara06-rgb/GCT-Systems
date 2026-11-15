<?php

namespace Tests\Feature\Purchase;

use App\Events\SystemDataUpdated;
use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ScheduledPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchaseModuleHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseUser(string $role = 'staff'): User
    {
        return User::factory()->create([
            'department' => 'Purchase',
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function permissions(string $role, bool $view, bool $edit): void
    {
        $roleKey = 'purchase_'.strtolower($role);
        $record = RolePermission::query()->where('role_key', $roleKey)->firstOrFail();
        $permissions = $record->permissions;
        data_set($permissions, 'purchase.view', $view);
        data_set($permissions, 'purchase.edit', $edit);
        $record->update(['permissions' => $permissions]);
    }

    private function order(string $status = 'Ordered', ?string $postedAt = null): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_no' => 'PO-HARDEN-'.str_pad((string) (PurchaseOrder::count() + 1), 4, '0', STR_PAD_LEFT),
            'po_date' => today(),
            'supplier_name' => 'Hardening Supplier',
            'items' => [[
                'item_description' => 'Brake Pad',
                'quantity' => 1,
                'unit' => 'PC',
                'cost' => 100,
                'amount' => 100,
            ]],
            'gross_amount' => 100,
            'net_amount' => 100,
            'status' => $status,
            'inventory_posted_at' => $postedAt,
        ]);
    }

    private function request(string $number = 'PR-HARDEN-1', string $status = 'For Purchase'): MaintenanceRequest
    {
        return MaintenanceRequest::create([
            'pr_no' => $number,
            'job_order_no' => 'RESTOCK',
            'bus_no' => 'RESTOCK',
            'item' => 'Brake Pad',
            'quantity' => 1,
            'status' => $status,
            'source_type' => 'Auto Restock',
        ]);
    }

    private function orderPayload(?MaintenanceRequest $request = null, array $overrides = []): array
    {
        return array_replace_recursive([
            'purchase_request_id' => $request?->id,
            'supplier_name' => 'Safe Supplier',
            'status' => 'Ordered',
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'items' => [[
                'pr_no' => $request?->pr_no,
                'item_description' => 'Brake Pad',
                'quantity' => 1,
                'unit' => 'PC',
                'cost' => 100,
            ]],
        ], $overrides);
    }

    private function schedule(array $overrides = []): ScheduledPurchase
    {
        return ScheduledPurchase::create(array_merge([
            'schedule_no' => 'SCH-HARDEN-'.str_pad((string) (ScheduledPurchase::count() + 1), 4, '0', STR_PAD_LEFT),
            'schedule_name' => 'Monthly Brake Pads',
            'supplier_name' => 'Schedule Supplier',
            'item' => 'Brake Pad',
            'quantity' => 4,
            'unit' => 'PC',
            'frequency' => 'Monthly',
            'start_date' => today()->subMonth(),
            'next_purchase_date' => today(),
            'estimated_cost' => 400,
            'status' => 'Active',
        ], $overrides));
    }

    private function schedulePayload(ScheduledPurchase $schedule, array $overrides = []): array
    {
        return array_merge([
            'schedule_name' => $schedule->schedule_name,
            'supplier_name' => $schedule->supplier_name,
            'supplier_contact' => $schedule->supplier_contact,
            'item' => $schedule->item,
            'quantity' => $schedule->quantity,
            'unit' => $schedule->unit,
            'frequency' => $schedule->frequency,
            'custom_interval_days' => $schedule->custom_interval_days,
            'start_date' => $schedule->start_date->toDateString(),
            'next_purchase_date' => $schedule->next_purchase_date->toDateString(),
            'estimated_cost' => $schedule->estimated_cost,
            'status' => $schedule->status,
            'notes' => $schedule->notes,
        ], $overrides);
    }

    public function test_purchase_staff_and_head_require_view_permission_for_every_purchase_page(): void
    {
        foreach (['staff', 'head'] as $role) {
            $user = $this->purchaseUser($role);
            $this->permissions($role, false, true);

            foreach (['dashboard-purchase', 'maintenance-requests', 'inventory-restock', 'purchase-orders', 'scheduled-purchase'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_view_only_purchase_users_cannot_call_mutation_routes(): void
    {
        foreach (['staff', 'head'] as $role) {
            $user = $this->purchaseUser($role);
            $this->permissions($role, true, false);
            $order = $this->order();
            $schedule = $this->schedule();

            $this->actingAs($user)->get(route('purchase-orders'))->assertOk();
            $this->actingAs($user)->post(route('purchase-orders.store'), [])->assertForbidden();
            $this->actingAs($user)->put(route('purchase-orders.update', $order), [])->assertForbidden();
            $this->actingAs($user)->delete(route('purchase-orders.destroy', $order))->assertForbidden();
            $this->actingAs($user)->patch(route('purchase-orders.update-status', $order), [])->assertForbidden();
            $this->actingAs($user)->post(route('scheduled-purchase.store'), [])->assertForbidden();
            $this->actingAs($user)->put(route('scheduled-purchase.update', $schedule), [])->assertForbidden();
            $this->actingAs($user)->post(route('scheduled-purchase.create-po', $schedule))->assertForbidden();
            $this->actingAs($user)->delete(route('scheduled-purchase.destroy', $schedule))->assertForbidden();
        }
    }

    public function test_new_order_cannot_skip_directly_to_a_fulfilled_status(): void
    {
        $user = $this->purchaseUser('head');

        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->post(route('purchase-orders.store'), $this->orderPayload(null, ['status' => 'Delivered']))
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('purchase_orders', ['supplier_name' => 'Safe Supplier']);
    }

    public function test_duplicate_purchase_order_submission_for_the_same_request_is_rejected(): void
    {
        $user = $this->purchaseUser('head');
        $purchaseRequest = $this->request();
        $payload = $this->orderPayload($purchaseRequest);

        $this->actingAs($user)->post(route('purchase-orders.store'), $payload)->assertRedirect('/purchase-orders');
        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->post(route('purchase-orders.store'), $payload)
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHasErrors('purchase_request_id');

        $this->assertSame(1, PurchaseOrder::query()->where('purchase_request_id', $purchaseRequest->id)->count());
    }

    public function test_discount_cannot_make_purchase_order_total_negative(): void
    {
        $user = $this->purchaseUser('head');

        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->post(route('purchase-orders.store'), $this->orderPayload(null, ['discount' => 101]))
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHasErrors('discount');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_received_order_cannot_be_deleted(): void
    {
        $user = $this->purchaseUser('head');
        $order = $this->order('Delivered', now()->toDateTimeString());

        $this->actingAs($user)
            ->from(route('purchase-orders'))
            ->delete(route('purchase-orders.destroy', $order))
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
    }

    public function test_deleting_unreceived_order_resets_every_linked_request(): void
    {
        $user = $this->purchaseUser('head');
        $first = $this->request('PR-HARDEN-1', 'Ordered');
        $second = $this->request('PR-HARDEN-2', 'Ordered');
        $order = $this->order();
        $order->update([
            'purchase_request_id' => $first->id,
            'items' => [
                ['pr_no' => $first->pr_no, 'item_description' => 'Brake Pad', 'quantity' => 1, 'unit' => 'PC', 'cost' => 100, 'amount' => 100],
                ['pr_no' => $second->pr_no, 'item_description' => 'Oil Filter', 'quantity' => 1, 'unit' => 'PC', 'cost' => 100, 'amount' => 100],
            ],
        ]);

        $this->actingAs($user)->delete(route('purchase-orders.destroy', $order))->assertRedirect('/purchase-orders');

        $this->assertSame('For Purchase', $first->fresh()->status);
        $this->assertSame('For Purchase', $second->fresh()->status);
    }

    public function test_completed_schedule_cannot_be_resumed_by_update_or_toggle(): void
    {
        $user = $this->purchaseUser('head');
        $schedule = $this->schedule(['status' => 'Completed']);

        $this->actingAs($user)
            ->from(route('scheduled-purchase'))
            ->put(route('scheduled-purchase.update', $schedule), $this->schedulePayload($schedule, ['status' => 'Active']))
            ->assertSessionHasErrors('status');

        $this->actingAs($user)
            ->from(route('scheduled-purchase'))
            ->patch(route('scheduled-purchase.toggle-status', $schedule))
            ->assertSessionHasErrors('status');

        $this->assertSame('Completed', $schedule->fresh()->status);
    }

    public function test_schedule_cannot_create_two_purchase_orders_for_the_same_due_date(): void
    {
        $user = $this->purchaseUser('head');
        $schedule = $this->schedule();

        $this->actingAs($user)
            ->post(route('scheduled-purchase.create-po', $schedule))
            ->assertRedirect(route('purchase-orders'));

        $this->actingAs($user)
            ->from(route('scheduled-purchase'))
            ->post(route('scheduled-purchase.create-po', $schedule))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertTrue($schedule->fresh()->next_purchase_date->isFuture());
    }

    public function test_purchase_and_schedule_mutations_broadcast_realtime_events(): void
    {
        Event::fake([SystemDataUpdated::class]);
        $user = $this->purchaseUser('head');
        $schedule = $this->schedule();

        $this->actingAs($user)->patch(route('scheduled-purchase.toggle-status', $schedule));
        Event::assertDispatched(
            SystemDataUpdated::class,
            fn (SystemDataUpdated $event) => $event->module === 'Purchase'
                && $event->entity === 'ScheduledPurchase'
                && $event->action === 'status_updated'
        );

        $order = $this->order();
        $this->actingAs($user)->patch(route('purchase-orders.update-status', $order), ['status' => 'For Delivery']);
        Event::assertDispatched(
            SystemDataUpdated::class,
            fn (SystemDataUpdated $event) => $event->module === 'Purchase'
                && $event->entity === 'PurchaseOrder'
                && $event->action === 'status_updated'
        );
    }
}
