<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Warehouse\InventoryItem;
use App\Services\Maintenance\JobOrderDurationService;
use App\Http\Middleware\RequireSystemPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobOrderReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function mechanic(string $id, string $name, string $specialization): Mechanic
    {
        $mechanic = Mechanic::create([
            'mechanic_id' => $id,
            'mechanic_name' => $name,
            'specialization' => $specialization,
            'employment_status' => 'Active',
        ]);
        MechanicAttendance::create([
            'mechanic_id' => $id,
            'mechanic_name' => $name,
            'attendance_date' => now(config('app.business_timezone', 'Asia/Manila'))->toDateString(),
            'status' => 'Present',
        ]);

        return $mechanic;
    }

    public function test_staff_submission_is_locked_until_head_signs_and_approves(): void
    {
        $staff = $this->user('staff');
        $head = $this->user('head');
        $bus = Bus::create(['bus_no' => 'BUS-REVIEW-01', 'plate_no' => 'ABC-1001', 'status' => 'Active']);
        $engine = $this->mechanic('M-REV-1', 'Engine Specialist', 'Engine');
        $electrical = $this->mechanic('M-REV-2', 'Electrical Specialist', 'Electrical');

        $response = $this->actingAs($staff)->withoutMiddleware(RequireSystemPermission::class)->postJson(route('job-orders.store'), [
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Engine will not crank',
            'work_to_perform' => 'Diagnose the engine and electrical starting circuit.',
            'maintenance_type' => 'Repair',
            'mechanic_ids' => [$engine->id, $electrical->id],
            'estimated_duration_value' => 4,
            'estimated_duration_unit' => 'Hours',
            'parts' => [],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $jobOrder = JobOrder::query()->firstOrFail();
        $this->assertSame('For Review', $jobOrder->review_status);
        $this->assertSame('On Hold', $jobOrder->status);
        $this->assertNull($jobOrder->start_date);
        $this->assertSame('Active', $bus->fresh()->status);
        $this->assertCount(2, $jobOrder->mechanicAssignments);
        $this->assertDatabaseHas('job_order_audits', ['job_order_id' => $jobOrder->id, 'action' => 'created']);

        $this->actingAs($staff)->withoutMiddleware(RequireSystemPermission::class)
            ->put(route('job-orders.update', $jobOrder), [])->assertStatus(423);
        $this->actingAs($staff)->withoutMiddleware(RequireSystemPermission::class)
            ->post(route('job-orders.finish', $jobOrder))->assertStatus(423);

        $this->actingAs($head)->withoutMiddleware(RequireSystemPermission::class)->post(route('job-orders.approve', $jobOrder), [
            'electronic_signature' => $head->name,
            'signature_image' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('img/gct_logo.png'))),
            'duration_decision' => 'accept',
            'review_notes' => 'Scope and assignments verified.',
        ])->assertRedirect();

        $jobOrder->refresh();
        $this->assertSame('Approved', $jobOrder->review_status);
        $this->assertSame('On Going', $jobOrder->status);
        $this->assertSame($head->id, $jobOrder->approved_by);
        $this->assertNotNull($jobOrder->approved_at);
        $this->assertNotNull($jobOrder->approval_signature_hash);
        $this->assertNotNull($jobOrder->actual_started_at);
        $this->assertSame('Under Maintenance', $bus->fresh()->status);
    }

    public function test_head_can_return_revision_and_staff_update_resubmits(): void
    {
        $staff = $this->user('staff');
        $head = $this->user('head');
        $bus = Bus::create(['bus_no' => 'BUS-REVISION-01', 'status' => 'Active']);
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-REVISION-01', 'bus_no' => $bus->bus_no,
            'problem_issue' => 'Unclear complaint', 'work_to_perform' => 'Inspect',
            'maintenance_type' => 'Repair', 'status' => 'On Hold',
            'part_status' => 'No Parts Required', 'review_status' => 'For Review', 'created_by' => $staff->id,
        ]);

        $this->actingAs($head)->withoutMiddleware(RequireSystemPermission::class)->post(route('job-orders.return-for-revision', $jobOrder), [
            'review_notes' => 'Add a specific diagnostic scope.',
        ])->assertRedirect();
        $this->assertSame('Revision Required', $jobOrder->fresh()->review_status);

        $this->actingAs($staff)->withoutMiddleware(RequireSystemPermission::class)->put(route('job-orders.update', $jobOrder), [
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Starter clicks but engine does not crank',
            'work_to_perform' => 'Test battery, starter motor, relay, and wiring.',
            'maintenance_type' => 'Repair',
            'estimated_duration_value' => 2,
            'estimated_duration_unit' => 'Hours',
            'parts' => [],
        ])->assertRedirect();

        $jobOrder->refresh();
        $this->assertSame('For Review', $jobOrder->review_status);
        $this->assertSame(1, $jobOrder->revision_number);
        $this->assertDatabaseHas('job_order_audits', ['job_order_id' => $jobOrder->id, 'action' => 'resubmitted']);
    }

    public function test_duration_median_uses_only_reliable_approved_completed_history(): void
    {
        foreach ([60, 120, 180] as $index => $minutes) {
            JobOrder::create([
                'job_order_no' => 'JO-MEDIAN-'.($index + 1), 'bus_no' => 'BUS-MEDIAN-'.($index + 1),
                'problem_issue' => 'Brake repair', 'maintenance_type' => 'Repair',
                'status' => 'Completed', 'review_status' => 'Approved', 'part_status' => 'No Parts Required',
                'actual_started_at' => now()->subDays(5 - $index),
                'actual_completed_at' => now()->subDays(5 - $index)->addMinutes($minutes),
            ]);
        }
        JobOrder::create([
            'job_order_no' => 'JO-UNRELIABLE', 'bus_no' => 'BUS-UNRELIABLE',
            'problem_issue' => 'Brake repair', 'maintenance_type' => 'Repair',
            'status' => 'Completed', 'review_status' => 'Approved', 'part_status' => 'No Parts Required',
            'start_date' => now()->subYear(), 'completion_date' => now(),
        ]);

        $suggestion = app(JobOrderDurationService::class)->suggestion('Repair');
        $this->assertSame(2.0, $suggestion['value']);
        $this->assertSame('Hours', $suggestion['unit']);
        $this->assertSame(3, $suggestion['sample_size']);
        $this->assertNull(app(JobOrderDurationService::class)->suggestion('PMS'));
    }

    public function test_failed_approval_does_not_leave_an_orphaned_signature_file(): void
    {
        Storage::fake('local');
        $head = $this->user('head');
        $bus = Bus::create(['bus_no' => 'BUS-SIGNATURE-ROLLBACK', 'status' => 'Active']);
        foreach (['DUPLICATE-A', 'DUPLICATE-B'] as $code) {
            InventoryItem::create([
                'item_code' => $code,
                'item_name' => 'Oil Filter',
                'parts_name' => 'Oil Filter',
                'category' => 'Filters',
                'quantity_available' => 5,
                'on_hand' => 5,
                'unit' => 'pcs',
                'unit_of_measurement' => 'pcs',
                'reorder_level' => 1,
            ]);
        }
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-SIGNATURE-ROLLBACK',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Replace oil filter',
            'maintenance_type' => 'Repair',
            'part_needed' => 'Oil Filter - Qty: 1 pcs',
            'part_status' => 'Not Requested',
            'review_status' => 'For Review',
            'status' => 'On Hold',
            'estimated_duration_value' => 1,
            'estimated_duration_unit' => 'Hours',
        ]);

        $this->actingAs($head)
            ->withoutMiddleware(RequireSystemPermission::class)
            ->post(route('job-orders.approve', $jobOrder), [
                'electronic_signature' => $head->name,
                'signature_image' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('img/gct_logo.png'))),
                'duration_decision' => 'accept',
            ])
            ->assertSessionHasErrors('parts_issue_strategy');

        Storage::disk('local')->assertDirectoryEmpty('maintenance/job-order-signatures/'.$jobOrder->id);
        $this->assertSame('For Review', $jobOrder->fresh()->review_status);
        $this->assertDatabaseMissing('job_order_part_reservations', ['job_order_id' => $jobOrder->id]);
    }
}
