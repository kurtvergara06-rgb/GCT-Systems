<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderPartReservation;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\InventoryIssuance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemRequestPartialIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_jo_creates_reserved_ir_and_separate_shortage_pr_without_double_requesting(): void
    {
        $staff = User::factory()->create([
            'department' => 'Maintenance', 'role' => 'staff', 'status' => 'Active',
        ]);
        $stock = InventoryItem::create([
            'item_code' => 'SPLIT-FILTER', 'parts_name' => 'Oil Filter',
            'item_name' => 'Oil Filter', 'category' => 'Filters',
            'on_hand' => 6, 'quantity_available' => 6,
            'unit' => 'pcs', 'unit_of_measurement' => 'pcs', 'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-2026-SPLIT',
            'bus_no' => 'BUS-SPLIT', 'problem_issue' => 'Filter maintenance',
            'maintenance_type' => 'Corrective', 'assigned_mechanic' => 'Mechanic Test',
            'part_needed' => 'Oil Filter [item:'.$stock->id.'] - Qty: 10 pcs',
            'review_status' => 'Approved', 'parts_issue_strategy' => 'wait_until_complete',
            'inventory_snapshot' => ['shortages' => [], 'reservation_at_submission' => true],
            'part_status' => 'Not Requested', 'status' => 'On Hold',
        ]);
        JobOrderPartReservation::create([
            'job_order_id' => $jo->id, 'inventory_item_id' => $stock->id, 'quantity' => 6,
        ]);

        $this->actingAs($staff)->post(route('item-requests.store'), [
            'job_order_id' => $jo->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $ir = ItemRequest::where('job_order_id', $jo->id)->firstOrFail();
        $this->assertSame(10, (int) collect($ir->requested_parts)->sum('quantity')); // Full JO requirement, not only stocked units.
        $this->assertDatabaseMissing('purchase_requests', ['job_order_no' => $jo->job_order_no]);

        // IR issuance and shortage procurement are deliberately submitted as
        // separate forms so each retains its own approval and audit workflow.
        $this->actingAs($staff)->post(route('purchase-requests.store'), [
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'purpose' => 'Purchase the unreserved shortage',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $pr = \App\Models\Maintenance\PurchaseRequest::where('job_order_no', $jo->job_order_no)->firstOrFail();
        $this->assertSame(4, (int) $pr->quantity);
        $this->assertSame('Submitted', $pr->status);
        $this->assertSame(6, (int) $stock->fresh()->quantity_available);

        $this->actingAs($staff)->post(route('item-requests.store'), [
            'job_order_id' => $jo->id,
        ])->assertSessionHasErrors('job_order_id');

        $this->actingAs($staff)->post(route('purchase-requests.store'), [
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
        ])->assertSessionHas('error', 'This Job Order already has a Purchase Request.');
        $this->assertSame(1, ItemRequest::where('job_order_id', $jo->id)->count());
        $this->assertSame(1, \App\Models\Maintenance\PurchaseRequest::where('job_order_no', $jo->job_order_no)->count());
    }

    public function test_two_signed_partial_handover_batches_issue_only_the_remaining_quantities(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create([
            'department' => 'Warehouse', 'role' => 'staff', 'status' => 'Active',
        ]);
        $stock = InventoryItem::create([
            'item_code' => 'FILTER-IR-BATCH',
            'parts_name' => 'Oil Filter',
            'item_name' => 'Oil Filter',
            'category' => 'Filters',
            'on_hand' => 10,
            'quantity_available' => 10,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-2026-0998',
            'bus_no' => 'BUS-IR-98',
            'problem_issue' => 'Change oil filter',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mechanic Test',
            'part_needed' => 'Oil Filter - Qty: 10 pc',
            'review_status' => 'Approved',
            'parts_issue_strategy' => 'issue_available_only',
            'inventory_snapshot' => ['shortages' => [], 'status' => 'sufficient'],
            'part_status' => 'Not Requested',
            'status' => 'On Hold',
        ]);
        JobOrderPartReservation::create([
            'job_order_id' => $jo->id,
            'inventory_item_id' => $stock->id,
            'quantity' => 10,
        ]);
        $ir = ItemRequest::create([
            'job_order_id' => $jo->id,
            'requested_by' => $staff->id,
            'status' => 'Submitted',
            'requested_parts' => [['name' => 'Oil Filter', 'quantity' => 10, 'unit' => 'pc']],
        ]);
        $this->actingAs($staff)->post(route('warehouse.item-requests.issue', $ir), [
            'received_by' => 'Mechanic Test',
            'signed_slip' => UploadedFile::fake()->create('handover1.pdf', 100, 'application/pdf'),
            'handover_confirmed' => '1',
            'issued_quantities' => [6],
        ])->assertRedirect();
        $this->assertSame('Partially Issued', $ir->fresh()->status);
        $this->assertSame(4, (int) $stock->fresh()->quantity_available);
        $this->assertSame(4, (int) JobOrderPartReservation::where('job_order_id', $jo->id)->value('quantity'));
        $partialAudit = $jo->audits()->where('action', 'parts_partially_issued')->firstOrFail();
        $this->assertSame($staff->id, $partialAudit->user_id);
        $this->assertSame('Partial', $partialAudit->to_status);
        $this->assertSame($ir->id, data_get($partialAudit->metadata, 'item_request_id'));
        $this->assertSame(6, (int) data_get($partialAudit->metadata, 'issued_parts.0.quantity'));

        $this->actingAs($staff)->post(route('warehouse.item-requests.issue', $ir), [
            'received_by' => 'Mechanic Test',
            'signed_slip' => UploadedFile::fake()->create('handover2.pdf', 100, 'application/pdf'),
            'handover_confirmed' => '1',
            'issued_quantities' => [4],
        ])->assertRedirect();
        $this->assertSame('Issued', $ir->fresh()->status);
        $this->assertSame('Issued', $jo->fresh()->part_status);
        $this->assertSame('On Going', $jo->fresh()->status);
        $this->assertNotNull($jo->fresh()->start_date);
        $this->assertSame(0, (int) $stock->fresh()->quantity_available);
        $this->assertSame(0, JobOrderPartReservation::where('job_order_id', $jo->id)->sum('quantity'));
        $completeAudit = $jo->audits()->where('action', 'parts_fully_issued')->firstOrFail();
        $this->assertSame($staff->id, $completeAudit->user_id);
        $this->assertSame('Issued', $completeAudit->to_status);
        $this->assertSame(4, (int) data_get($completeAudit->metadata, 'issued_parts.0.quantity'));
        $this->assertSame('Mechanic Test', data_get($completeAudit->metadata, 'received_by'));
        $this->assertSame(2, $jo->audits()->whereIn('action', ['parts_partially_issued', 'parts_fully_issued'])->count());
        $this->assertDatabaseHas('job_order_audits', [
            'job_order_id' => $jo->id,
            'action' => 'work_started_after_parts_issue',
            'from_status' => 'On Hold',
            'to_status' => 'On Going',
        ]);
        $ref = 'IR-'.($ir->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT);
        $this->assertSame(2, InventoryIssuance::where('reference_no', $ref)
            ->orWhere('reference_no', 'like', $ref.'-B%')->count());
    }
    public function test_bare_legacy_jo_quantity_is_flagged_and_cannot_deduct_inventory(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create([
            'department' => 'Warehouse', 'role' => 'staff', 'status' => 'Active',
        ]);
        $stock = InventoryItem::create([
            'item_code' => 'LEGACY-BRAKE', 'parts_name' => 'Brake Pad',
            'item_name' => 'Brake Pad', 'category' => 'Brakes',
            'on_hand' => 3, 'quantity_available' => 3,
            'unit' => 'pcs', 'unit_of_measurement' => 'pcs', 'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-LEGACY-BRAKE', 'bus_no' => 'BUS-LEGACY-02',
            'problem_issue' => 'Brake repair', 'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Brake Pad', // Legacy text has no approved quantity.
            'review_status' => 'Approved',
            'parts_issue_strategy' => 'issue_available_only',
            'part_status' => 'Partial', 'status' => 'On Hold',
        ]);
        JobOrderPartReservation::create([
            'job_order_id' => $jo->id, 'inventory_item_id' => $stock->id, 'quantity' => 1,
        ]);
        $ir = ItemRequest::create([
            'job_order_id' => $jo->id, 'requested_by' => $staff->id,
            'status' => 'Partially Issued',
            'requested_parts' => [[
                'name' => 'Brake Pad', 'quantity' => 4, 'unit' => 'pcs',
                'inventory_item_id' => $stock->id,
            ]],
        ]);

        $this->actingAs($staff)->get(route('warehouse.item-requests', ['view' => 'active']))
            ->assertOk()->assertSee('Quantity review required')
            ->assertSee('JO requirement not confirmed');

        $this->actingAs($staff)->post(route('warehouse.item-requests.issue', $ir), [
            'received_by' => 'Test Mechanic',
            'signed_slip' => UploadedFile::fake()->create('unsigned-attempt.pdf', 100, 'application/pdf'),
            'handover_confirmed' => '1',
            'issued_quantities' => [1],
        ])->assertSessionHasErrors('issue');

        $this->assertSame(3, (int) $stock->fresh()->quantity_available);
        $this->assertSame(0, InventoryIssuance::query()->confirmedSigned()->count());
        $this->assertSame(4, (int) collect($ir->fresh()->requested_parts)->sum('quantity'));
    }

    public function test_legacy_issued_request_with_no_signed_handover_stays_active_and_can_prepare(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create([
            'department' => 'Warehouse', 'role' => 'staff', 'status' => 'Active',
        ]);
        $stock = InventoryItem::create([
            'item_code' => 'LEGACY-IR-FILTER',
            'parts_name' => 'Oil Filter',
            'item_name' => 'Oil Filter',
            'category' => 'Filters',
            'on_hand' => 2,
            'quantity_available' => 2,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'LEGACY-JO-001',
            'bus_no' => 'BUS-LEGACY',
            'problem_issue' => 'Replace filter',
            'maintenance_type' => 'Corrective',
            'assigned_mechanic' => 'Mechanic Test',
            'part_needed' => 'Oil Filter - Qty: 1 pcs',
            'review_status' => 'Approved',
            'parts_issue_strategy' => 'issue_available_only',
            'inventory_snapshot' => ['shortages' => [], 'status' => 'sufficient'],
            'part_status' => 'Issued', // Stale legacy/demo status, no signed SWIS.
            'status' => 'On Hold',
        ]);
        JobOrderPartReservation::create([
            'job_order_id' => $jo->id, 'inventory_item_id' => $stock->id, 'quantity' => 1,
        ]);
        $ir = ItemRequest::create([
            'job_order_id' => $jo->id,
            'requested_by' => $staff->id,
            'status' => 'Issued',
            'requested_parts' => [['name' => 'Oil Filter', 'quantity' => 1, 'unit' => 'pcs']],
        ]);
        $reference = 'IR-'.($ir->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT);

        $this->actingAs($staff)->get(route('warehouse.item-requests', ['view' => 'active']))
            ->assertOk()
            ->assertSee($reference)
            ->assertSee('Prepare Issuance Slip')
            ->assertSee('0 / 1 issued');
        $this->actingAs($staff)->get(route('warehouse.item-requests', ['view' => 'history']))
            ->assertOk()
            ->assertDontSee($reference)
            ->assertDontSee('data-ir-prepare');

        $this->actingAs($staff)->post(route('warehouse.item-requests.issue', $ir), [
            'received_by' => 'Mechanic Test',
            'signed_slip' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
            'handover_confirmed' => '1',
            'issued_quantities' => [1],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, InventoryIssuance::query()->confirmedSigned()->count());

        $this->actingAs($staff)->get(route('warehouse.item-requests', ['view' => 'history']))
            ->assertOk()
            ->assertSee($reference)
            ->assertDontSee('data-ir-prepare');
        $this->actingAs($staff)->get(route('warehouse.item-requests', ['view' => 'active']))
            ->assertOk()
            ->assertDontSee($reference);
    }

}
