<?php

namespace Tests\Feature;

use App\Models\Admin\GpsTripRecord;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Incident;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createMaintenanceUser(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    private function createProcessedGps(
        string $busNo,
        float $mileageKm,
        ?Carbon $beginningAt = null
    ): GpsTripRecord {
        $batchId = DB::table('batch_uploads')->insertGetId([
            'file_name' => 'gps-'.$busNo.'.xlsx',
            'stored_name' => 'gps-'.$busNo.'.xlsx',
            'file_path' => 'testing/gps-'.$busNo.'.xlsx',
            'file_type' => 'xlsx',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'data_origin' => 'genuine',
            'bus_no' => $busNo,
            'status' => 'Processed',
            'total_records' => 1,
            'processed_records' => 1,
            'failed_records' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return GpsTripRecord::create([
            'batch_upload_id' => $batchId,
            'bus_no' => $busNo,
            'beginning_at' => $beginningAt ?? now(),
            'ending_at' => ($beginningAt ?? now())->copy()->addHour(),
            'mileage_km' => $mileageKm,
            'idling_minutes' => 0,
        ]);
    }

    public function test_dashboard_renders_successfully_for_authenticated_maintenance_user(): void
    {
        $user = $this->createMaintenanceUser();

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('Maintenance.maintenance-dashboard');
        $response->assertSee('Maintenance Dashboard');
        $response->assertSee('Active Job Orders');
        $response->assertSee('Mechanic Availability');
        $response->assertSee('PMS Attention Hub');
    }

    public function test_dashboard_correctly_computes_kpis_and_active_repairs(): void
    {
        $user = $this->createMaintenanceUser();

        $bus = Bus::create([
            'bus_no' => 'BUS-101',
            'status' => 'Active',
            'latest_gps_km' => 15200.0,
        ]);

        // 1 Active Job Order (On Going)
        $joOngoing = JobOrder::create([
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Brake pad wear',
            'maintenance_type' => 'Corrective',
            'status' => 'On Going',
            'start_date' => now(),
            'estimated_duration_value' => 2,
            'estimated_duration_unit' => 'Hours',
        ]);

        // 1 Active Job Order (On Hold)
        JobOrder::create([
            'job_order_no' => 'JO-2026-0002',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Transmission slipping',
            'maintenance_type' => 'Major Repair',
            'status' => 'On Hold',
            'part_needed' => 'Clutch Disc',
            'part_status' => 'Requested',
            'start_date' => now(),
            'estimated_duration_value' => 4,
            'estimated_duration_unit' => 'Hours',
        ]);

        // 1 Completed Job Order
        JobOrder::create([
            'job_order_no' => 'JO-2026-0003',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Oil replacement',
            'maintenance_type' => 'PMS',
            'status' => 'Completed',
            'start_date' => now()->subDays(2),
            'completion_date' => now()->subDay(),
            'estimated_duration_value' => 1,
            'estimated_duration_unit' => 'Hours',
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-2026-0004',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Rejected repair must not count as completed.',
            'maintenance_type' => 'Repair',
            'status' => 'Completed',
            'review_status' => 'Rejected',
            'start_date' => now()->subDays(3),
            'completion_date' => now()->subDays(2),
            'estimated_duration_value' => 1,
            'estimated_duration_unit' => 'Hours',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalActiveJobOrders', 2);
        $response->assertViewHas('ongoingCount', 1);
        $response->assertViewHas('onHoldCount', 1);
        $response->assertViewHas('completedCount', 1);

        $response->assertSee('JO-2026-0001');
        $response->assertSee('JO-2026-0002');
        $response->assertSee('Clutch Disc');
    }

    public function test_dashboard_identifies_breakdown_repairs_and_referrals(): void
    {
        $user = $this->createMaintenanceUser();

        $bus = Bus::create([
            'bus_no' => 'BUS-202',
            'status' => 'Active',
            'latest_gps_km' => 10000.0,
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-9999',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'EDSA Crossing',
            'description' => 'Radiator burst causing coolant leak.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $user->id,
        ]);

        $referral = MaintenanceReferral::create([
            'incident_id' => $incident->id,
            'status' => 'Pending',
            'notes' => 'Urgent tow and cooling system overhaul required.',
            'referred_by' => $user->id,
        ]);

        // Active JO linked to referral
        $joBreakdown = JobOrder::create([
            'job_order_no' => 'JO-2026-0099',
            'bus_no' => $bus->bus_no,
            'maintenance_referral_id' => $referral->id,
            'incident_id' => $incident->id,
            'problem_issue' => 'Overheating radiator',
            'maintenance_type' => 'Emergency',
            'status' => 'On Going',
            'start_date' => now(),
            'estimated_duration_value' => 3,
            'estimated_duration_unit' => 'Hours',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('breakdownRepairsCount', 1);
        $response->assertViewHas('referralPendingCount', 1);
        $response->assertSee('Breakdown INC-9999');
        $response->assertDontSee('Breakdown INC-INC-9999');
        $response->assertSee('Radiator burst causing coolant leak.');
    }

    public function test_dashboard_tracks_pms_overdue_and_due_soon(): void
    {
        $user = $this->createMaintenanceUser();

        $busOverdue = Bus::create([
            'bus_no' => 'BUS-301',
            'status' => 'Active',
            'latest_gps_km' => 20500.0,
        ]);

        $busDueSoon = Bus::create([
            'bus_no' => 'BUS-302',
            'status' => 'Active',
            'latest_gps_km' => 19700.0,
        ]);

        $this->createProcessedGps(
            $busOverdue->bus_no,
            20500.0
        );

        $this->createProcessedGps(
            $busDueSoon->bus_no,
            19700.0
        );

        // Overdue schedule: processed GPS (20,500) >= next PMS (20,000)
        PmsSchedule::create([
            'bus_no' => $busOverdue->bus_no,
            'maintenance_type' => 'PMS Level 2',
            'last_pms_km' => 10000,
            'next_pms_km' => 20000,
            'pms_interval_km' => 10000,
            'recommended_date' => now()->addDays(5),
        ]);

        // Due soon schedule: next PMS (20,000) - latest GPS (19,700) = 300 km <= 500
        PmsSchedule::create([
            'bus_no' => $busDueSoon->bus_no,
            'maintenance_type' => 'PMS Level 1',
            'last_pms_km' => 15000,
            'next_pms_km' => 20000,
            'pms_interval_km' => 5000,
            'recommended_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('pmsOverdueCount', 1);
        $response->assertViewHas('pmsDueSoonCount', 1);
        $response->assertViewHas('totalPmsAttention', 2);

        $response->assertSee('BUS-301');
        $response->assertSee('BUS-302');
        $response->assertSee('Create JO');
    }

    public function test_dashboard_and_pms_scheduling_agree_when_recommended_date_is_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        try {
            $user = $this->createMaintenanceUser();

            $bus = Bus::create([
                'bus_no' => 'BUS-DATE-01',
                'status' => 'Active',
                // Intentionally stale: both modules must use processed GPS instead.
                'latest_gps_km' => 0,
            ]);

            $this->createProcessedGps(
                $bus->bus_no,
                1000,
                Carbon::parse('2026-10-04 08:00:00')
            );

            PmsSchedule::create([
                'bus_no' => $bus->bus_no,
                'maintenance_type' => 'Date Based PMS',
                'last_pms_km' => 0,
                'next_pms_km' => 10000,
                'pms_interval_km' => 10000,
                'recommended_date' => '2026-08-26',
            ]);

            $dashboard = $this
                ->actingAs($user)
                ->get(route('maintenance-dashboard'));

            $dashboard->assertOk();
            $dashboard->assertViewHas(
                'pmsAttentionList',
                function ($items): bool {
                    $item = $items->firstWhere(
                        'maintenance_type',
                        'Date Based PMS'
                    );

                    return $item !== null
                        && $item->status === 'Overdue'
                        && (float) $item->latest_gps_km === 1000.0;
                }
            );

            $scheduling = $this
                ->actingAs($user)
                ->get(route('PMS-Scheduling'));

            $scheduling->assertOk();
            $scheduling->assertViewHas(
                'rows',
                function ($rows): bool {
                    $busRow = collect($rows->items())
                        ->firstWhere('bus_no', 'BUS-DATE-01');

                    // Legacy task urgency is retained, but bus-level status requires a verified baseline.
                    if (! $busRow || $busRow->overall_status !== 'Not Configured') {
                        return false;
                    }

                    $task = $busRow->tasks
                        ->firstWhere(
                            'maintenance_type',
                            'Date Based PMS'
                        );

                    return $task !== null
                        && $task->status === 'Overdue'
                        && (float) $task->current_km === 1000.0;
                }
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_date_overdue_pms_can_create_job_order_even_before_mileage_warning_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        try {
            $user = $this->createMaintenanceUser();

            $bus = Bus::create([
                'bus_no' => 'BUS-DATE-JO',
                'status' => 'Active',
                'latest_gps_km' => 0,
            ]);

            $this->createProcessedGps(
                $bus->bus_no,
                1000,
                Carbon::parse('2026-10-04 08:00:00')
            );

            $schedule = PmsSchedule::create([
                'bus_no' => $bus->bus_no,
                'maintenance_type' => 'Date Overdue Service',
                'last_pms_km' => 0,
                'next_pms_km' => 10000,
                'pms_interval_km' => 10000,
                'recommended_date' => '2026-08-26',
            ]);

            $response = $this
                ->actingAs($user)
                ->get(
                    route(
                        'pms-schedules.create-job-order',
                        $schedule
                    )
                );

            $response->assertRedirect();

            $location = (string) $response->headers->get('Location');

            $this->assertStringContainsString(
                'create_pms=1',
                $location
            );
            $this->assertStringContainsString(
                'pms_schedule_id='.$schedule->id,
                $location
            );
            $this->assertStringContainsString(
                'Date%20Overdue%20Service',
                $location
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_calculates_mechanic_roster_availability(): void
    {
        $user = $this->createMaintenanceUser();

        $mechanic1 = Mechanic::create([
            'mechanic_id' => 'MECH-001',
            'mechanic_name' => 'Roberto Cruz',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);

        $mechanic2 = Mechanic::create([
            'mechanic_id' => 'MECH-002',
            'mechanic_name' => 'Danilo Santos',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);

        // Log attendance for both
        MechanicAttendance::create([
            'mechanic_id' => $mechanic1->mechanic_id,
            'mechanic_name' => $mechanic1->mechanic_name,
            'attendance_date' => now(config('app.business_timezone', 'Asia/Manila'))->toDateString(),
            'status' => 'Present',
        ]);

        MechanicAttendance::create([
            'mechanic_id' => $mechanic2->mechanic_id,
            'mechanic_name' => $mechanic2->mechanic_name,
            'attendance_date' => now(config('app.business_timezone', 'Asia/Manila'))->toDateString(),
            'status' => 'Present',
        ]);

        // Assign mechanic 1 to an active Job Order -> On Duty
        JobOrder::create([
            'job_order_no' => 'JO-2026-MECH1',
            'bus_no' => 'BUS-404',
            'problem_issue' => 'Alternator check',
            'maintenance_type' => 'Electrical',
            'status' => 'On Going',
            'assigned_mechanic' => $mechanic1->mechanic_name,
            'start_date' => now(),
            'estimated_duration_value' => 2,
            'estimated_duration_unit' => 'Hours',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalMechanicsCount', 2);
        $response->assertViewHas('onDutyMechanicsCount', 1);
        $response->assertViewHas('availableMechanicsCount', 1);

        $response->assertSee('Roberto Cruz');
        $response->assertSee('Danilo Santos');
        $response->assertSee('JO-2026-MECH1');
    }

    public function test_dashboard_recognizes_multiple_mechanics_from_assignment_records(): void
    {
        $user = $this->createMaintenanceUser();
        $mechanics = collect([
            ['mechanic_id' => 'MECH-MULTI-01', 'mechanic_name' => 'Assigned One'],
            ['mechanic_id' => 'MECH-MULTI-02', 'mechanic_name' => 'Assigned Two'],
            ['mechanic_id' => 'MECH-MULTI-03', 'mechanic_name' => 'Unassigned Three'],
        ])->map(function (array $data): Mechanic {
            $mechanic = Mechanic::create($data + [
                'employment_status' => 'Active', 'shift' => 'Day',
            ]);
            MechanicAttendance::create([
                'mechanic_id' => $mechanic->mechanic_id,
                'mechanic_name' => $mechanic->mechanic_name,
                'attendance_date' => now(config('app.business_timezone', 'Asia/Manila'))->toDateString(),
                'status' => 'Present',
            ]);
            return $mechanic;
        });

        $jo = JobOrder::create([
            'job_order_no' => 'JO-MULTI-001',
            'bus_no' => 'BUS-MULTI',
            'problem_issue' => 'Multi-mechanic repair',
            'maintenance_type' => 'Repair',
            'status' => 'On Going',
            'review_status' => 'Approved',
            'assigned_mechanic' => 'Assigned One, Assigned Two',
        ]);
        foreach ($mechanics->take(2) as $mechanic) {
            $jo->mechanicAssignments()->create([
                'mechanic_id' => $mechanic->id,
                'mechanic_name' => $mechanic->mechanic_name,
            ]);
        }

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));
        $response->assertOk()->assertViewHas('onDutyMechanicsCount', 2)
            ->assertViewHas('availableMechanicsCount', 1);
        $this->assertEqualsCanonicalizing(
            ['MECH-MULTI-01', 'MECH-MULTI-02'],
            $response->viewData('onDutyMechanicsList')->pluck('mechanic_id')->all()
        );
    }

    public function test_dashboard_does_not_call_absent_or_on_hold_mechanics_on_duty(): void
    {
        $user = $this->createMaintenanceUser();
        $absent = Mechanic::create([
            'mechanic_id' => 'MECH-ABSENT-01',
            'mechanic_name' => 'Absent Mechanic',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);
        $waiting = Mechanic::create([
            'mechanic_id' => 'MECH-WAIT-01',
            'mechanic_name' => 'Waiting Mechanic',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);
        $date = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        foreach ([[$absent, 'Absent'], [$waiting, 'Present']] as [$mechanic, $status]) {
            MechanicAttendance::create([
                'mechanic_id' => $mechanic->mechanic_id,
                'mechanic_name' => $mechanic->mechanic_name,
                'attendance_date' => $date,
                'status' => $status,
            ]);
        }
        JobOrder::create([
            'job_order_no' => 'JO-ABSENT-001', 'bus_no' => 'BUS-ABSENT',
            'problem_issue' => 'Absence check', 'maintenance_type' => 'Repair',
            'status' => 'On Going', 'assigned_mechanic' => 'Absent Mechanic',
        ]);
        JobOrder::create([
            'job_order_no' => 'JO-WAIT-001', 'bus_no' => 'BUS-WAIT',
            'problem_issue' => 'Waiting on approval', 'maintenance_type' => 'Repair',
            'status' => 'On Hold', 'assigned_mechanic' => 'Waiting Mechanic',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));
        $response->assertOk()->assertViewHas('onDutyMechanicsCount', 0)
            ->assertViewHas('availableMechanicsCount', 0);
        $this->assertCount(2, $response->viewData('otherMechanicsList'));
        $this->assertSame('Assigned / On Hold', $response->viewData('otherMechanicsList')
            ->firstWhere('mechanic_id', 'MECH-WAIT-01')->status);
    }

    public function test_parts_blockers_follow_current_jo_statuses_and_repair_bay_follows_bus_status(): void
    {
        $user = $this->createMaintenanceUser();

        Bus::create([
            'bus_no' => 'BUS-PMS-NOT-STARTED',
            'status' => 'Active',
        ]);
        Bus::create([
            'bus_no' => 'BUS-PARTS-WAITING',
            'status' => 'Under Maintenance',
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-PMS-NOT-STARTED',
            'bus_no' => 'BUS-PMS-NOT-STARTED',
            'problem_issue' => 'PMS approved, waiting for release',
            'maintenance_type' => 'PMS',
            'status' => 'On Hold',
            'review_status' => 'Approved',
        ]);
        JobOrder::create([
            'job_order_no' => 'JO-PARTS-SUBMITTED',
            'bus_no' => 'BUS-PARTS-WAITING',
            'problem_issue' => 'Brake repair waiting for inventory issue',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'review_status' => 'Approved',
            'part_needed' => 'Brake pads',
            'part_status' => 'Submitted',
        ]);
        JobOrder::create([
            'job_order_no' => 'JO-PARTS-FOR-PURCHASE',
            'bus_no' => 'BUS-PARTS-WAITING',
            'problem_issue' => 'Another repair waiting for purchase',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'review_status' => 'Approved',
            'part_needed' => 'Radiator',
            'part_status' => 'For Purchase',
        ]);
        JobOrder::create([
            'job_order_no' => 'JO-PARTS-ISSUED',
            'bus_no' => 'BUS-PARTS-WAITING',
            'problem_issue' => 'Parts already issued',
            'maintenance_type' => 'Repair',
            'status' => 'On Going',
            'review_status' => 'Approved',
            'part_needed' => 'Oil filter',
            'part_status' => 'Issued',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));
        $response->assertOk()->assertViewHas('busesInRepairBay', 1);
        $this->assertEqualsCanonicalizing(
            ['JO-PARTS-SUBMITTED', 'JO-PARTS-FOR-PURCHASE'],
            $response->viewData('blockedJobOrders')->pluck('job_order_no')->all()
        );
    }

    public function test_dashboard_fleet_readiness_uses_bus_master_statuses(): void
    {
        $user = $this->createMaintenanceUser();

        Bus::create([
            'bus_no' => 'BUS-READY-001',
            'status' => 'Active',
        ]);

        Bus::create([
            'bus_no' => 'BUS-READY-002',
            'status' => 'Active',
        ]);

        Bus::create([
            'bus_no' => 'BUS-MAINT-001',
            'status' => 'Under Maintenance',
        ]);

        Bus::create([
            'bus_no' => 'BUS-INACTIVE-001',
            'status' => 'Inactive',
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-READY-001',
            'bus_no' => 'BUS-READY-001',
            'problem_issue' => 'Active repair should not override bus master readiness.',
            'maintenance_type' => 'Repair',
            'status' => 'On Going',
            'start_date' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('maintenance-dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalBuses', 4);
        $response->assertViewHas('activeBusesCount', 2);
        $response->assertViewHas('underMaintenanceBusesCount', 1);
        $response->assertViewHas('inactiveBusesCount', 1);
        $response->assertViewHas('busesInRepairBay', 1);
        $response->assertViewHas('operationalRate', 50.0);
    }

    public function test_dashboard_tracks_purchase_requests_and_fuel_reports(): void
    {
        $user = $this->createMaintenanceUser();

        PurchaseRequest::create([
            'pr_no' => 'PR-2026-0001',
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => 'BUS-505',
            'status' => 'Submitted',
            'item' => 'Brake Fluid',
            'quantity' => 2,
        ]);

        FuelReport::create([
            'bus_no' => 'BUS-505',
            'fuel_liters' => 100.0,
            'distance_km' => 350.0,
            'report_date' => today(),
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertSee('350.0 km');
        $response->assertSee('100.0 L');
        $response->assertSee('3.5 km/L');
    }

    public function test_dashboard_renders_empty_state_gracefully(): void
    {
        $user = $this->createMaintenanceUser();

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertSee('No active or recent job orders');
        $response->assertSee('All PMS Schedules Up to Date');
        $response->assertSee('No breakdown referrals');
    }

    public function test_dashboard_charts_use_saved_orders_and_approved_completion_dates(): void
    {
        $user = $this->createMaintenanceUser();
        Bus::create(['bus_no' => 'CHART-ACTIVE', 'status' => 'Active']);
        Bus::create(['bus_no' => 'CHART-INACTIVE', 'status' => 'Inactive']);

        foreach ([
            ['JO-CHART-1', 'Completed', 'Approved', now()],
            ['JO-CHART-2', 'Completed', 'Rejected', now()],
            ['JO-CHART-3', 'On Hold', 'Approved', null],
        ] as [$number, $status, $reviewStatus, $completionDate]) {
            JobOrder::create([
                'job_order_no' => $number,
                'bus_no' => 'CHART-ACTIVE',
                'problem_issue' => 'Chart verification',
                'maintenance_type' => 'Repair',
                'status' => $status,
                'review_status' => $reviewStatus,
                'completion_date' => $completionDate,
            ]);
        }

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertOk();
        $this->assertCount(7, $response->viewData('workloadDailyTrend'));
        $this->assertCount(6, $response->viewData('workloadWeeklyTrend'));
        $this->assertCount(6, $response->viewData('workloadMonthlyTrend'));
        $this->assertSame(3, $response->viewData('workloadDailyTrend')[6]['created']);
        $this->assertSame(1, $response->viewData('workloadDailyTrend')[6]['completed']);
        $this->assertSame([1, 0, 1], $response->viewData('fleetHealthDistribution')['values']);
        $this->assertSame(33, $response->viewData('overallCompletionRate'));
    }
}
