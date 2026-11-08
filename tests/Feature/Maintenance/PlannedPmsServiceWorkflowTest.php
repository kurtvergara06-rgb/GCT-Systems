<?php

namespace Tests\Feature\Maintenance;

use App\Http\Middleware\RequireSystemPermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlannedPmsServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_planned_pms_does_not_block_dispatch_but_started_pms_and_repairs_do(): void
    {
        $pmsBus = Bus::create(['bus_no' => 'BUS-PLANNED-ONLY', 'status' => 'Active']);
        $repairBus = Bus::create(['bus_no' => 'BUS-REPAIR-BLOCK', 'status' => 'Under Maintenance']);

        $pms = JobOrder::create([
            'job_order_no' => 'JO-PMS-PLANNED',
            'bus_no' => $pmsBus->bus_no,
            'problem_issue' => 'PMS due',
            'maintenance_type' => 'PMS',
            'review_status' => 'Approved',
            'status' => 'On Hold',
            'part_status' => 'No Parts Required',
        ]);
        JobOrder::create([
            'job_order_no' => 'JO-REPAIR-BLOCK',
            'bus_no' => $repairBus->bus_no,
            'problem_issue' => 'Brake fault',
            'maintenance_type' => 'Repair',
            'review_status' => 'Approved',
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
        ]);

        $this->assertFalse(JobOrder::query()->blocksDispatch()
            ->where('bus_no', $pmsBus->bus_no)->exists());
        $this->assertTrue(JobOrder::query()->blocksDispatch()
            ->where('bus_no', $repairBus->bus_no)->exists());

        $pms->update(['status' => 'On Going', 'actual_started_at' => now()]);
        $this->assertTrue(JobOrder::query()->blocksDispatch()
            ->where('bus_no', $pmsBus->bus_no)->exists());
    }

    public function test_pms_approval_keeps_bus_available_until_head_explicitly_starts_service(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['department' => 'Maintenance', 'role' => 'staff', 'status' => 'Active']);
        $head = User::factory()->create(['department' => 'Maintenance', 'role' => 'head', 'status' => 'Active']);
        $bus = Bus::create(['bus_no' => 'BUS-PMS-PLANNED', 'plate_no' => 'PMS-011', 'status' => 'Active']);
        $mechanic = Mechanic::create([
            'mechanic_id' => 'M-PMS-PLANNED',
            'mechanic_name' => 'Planned PMS Mechanic',
            'specialization' => 'Engine',
            'employment_status' => 'Active',
        ]);
        MechanicAttendance::create([
            'mechanic_id' => $mechanic->mechanic_id,
            'mechanic_name' => $mechanic->mechanic_name,
            'attendance_date' => now(config('app.business_timezone', 'Asia/Manila'))->toDateString(),
            'status' => 'Present',
        ]);
        $schedule = PmsSchedule::create([
            'bus_no' => $bus->bus_no,
            'maintenance_type' => 'PMS',
            'last_pms_km' => 0,
            'next_pms_km' => 10000,
            'pms_interval_km' => 10000,
            'recommended_date' => now()->toDateString(),
        ]);

        $this->actingAs($staff)->withoutMiddleware(RequireSystemPermission::class)
            ->postJson(route('job-orders.store'), [
                'bus_no' => $bus->bus_no,
                'problem_issue' => 'Routine PMS due',
                'maintenance_type' => 'PMS',
                'pms_schedule_id' => $schedule->id,
                'mechanic_ids' => [$mechanic->id],
                'estimated_duration_value' => 2,
                'estimated_duration_unit' => 'Hours',
                'parts' => [],
            ])->assertOk()->assertJsonPath('success', true);

        $job = JobOrder::query()->where('bus_no', $bus->bus_no)->firstOrFail();

        $this->actingAs($head)->withoutMiddleware(RequireSystemPermission::class)
            ->post(route('job-orders.approve', $job), [
                'electronic_signature' => $head->name,
                'signature_image' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('img/gct_logo.png'))),
                'duration_decision' => 'accept',
            ])->assertRedirect();

        $this->assertSame('Approved', $job->fresh()->review_status);
        $this->assertSame('On Hold', $job->fresh()->status);
        $this->assertNull($job->fresh()->actual_started_at);
        $this->assertSame('Active', $bus->fresh()->status);

        $this->actingAs($staff)->withoutMiddleware(RequireSystemPermission::class)
            ->post(route('job-orders.start-pms-service', $job))->assertStatus(403);

        $this->actingAs($head)->withoutMiddleware(RequireSystemPermission::class)
            ->post(route('job-orders.start-pms-service', $job), ['physical_release_confirmed' => '1'])->assertRedirect();

        $this->assertSame('On Going', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->actual_started_at);
        $this->assertSame('Under Maintenance', $bus->fresh()->status);
        $this->assertDatabaseHas('job_order_audits', [
            'job_order_id' => $job->id,
            'action' => 'pms_service_started',
        ]);
    }
}
