<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Non-destructive realistic simulation dataset.
 *
 * The records created here are intentionally visible in the normal frontend
 * modules (Operation, Incidents, Maintenance, Warehouse, Purchase) while
 * remaining clearly isolated from genuine production ML training:
 *
 * - Simulated delay trips use the TRIP-GCT-* prefix. The genuine Delay #3
 *   export excludes the configured TRIP-* prefix.
 * - Inventory movements are written with source="simulated". Genuine Inventory #4
 *   training reads only stock_movements.source="app".
 *
 * Re-running the seeder replaces only its own simulated fact rows. It never
 * truncates application tables and never deletes genuine operational records.
 */
class RealisticSampleDataSeeder extends Seeder
{
    private const TRIP_PREFIX = 'TRIP-GCT-';
    private const DDR_PREFIX = 'DDR-GCT-';
    private const INCIDENT_PREFIX = 'INC-GCT-';
    private const JO_PREFIX = 'JO-GCT-';
    private const PR_PREFIX = 'PR-GCT-';
    private const PO_PREFIX = 'PO-GCT-';
    private const PART_PREFIX = 'GCT-PART-';
    private const BUS_PREFIX = 'GCT-2';
    private const DRIVER_PREFIX = 'GCT-DRV-';
    private const ROUTE_PREFIX = 'GCT-RT-';

    private const LEGACY_TRIP_PREFIX = 'TRIP-DEMO-';
    private const LEGACY_DDR_PREFIX = 'DEMO-DDR-';
    private const LEGACY_INCIDENT_PREFIX = 'DEMO-INC-';
    private const LEGACY_JO_PREFIX = 'DEMO-JO-';
    private const LEGACY_PR_PREFIX = 'DEMO-PR-';
    private const LEGACY_PO_PREFIX = 'DEMO-PO-';
    private const LEGACY_PART_PREFIX = 'DEMO-PART-';
    private const LEGACY_BUS_PREFIX = 'DEMO-BUS-';
    private const LEGACY_DRIVER_PREFIX = 'DEMO-DRV-';
    private const LEGACY_ROUTE_PREFIX = 'DEMO-RT-';

    public function run(): void
    {
        mt_srand(20260924);

        DB::transaction(function (): void {
            $this->clearPreviousSimulatedFacts();
            $this->migrateLegacyMasterIdentifiers();

            $actorId = DB::table('users')->orderBy('id')->value('id');
            $buses = $this->seedBuses();
            $drivers = $this->seedDrivers();
            $routes = $this->seedRoutes();

            $this->seedDelayFrontendData($drivers, $buses, $routes, $actorId);

            $items = $this->seedInventoryItems();
            $stories = $this->seedMaintenancePurchaseStories($items, $buses);
            $this->seedInventoryMovementHistory($items, $stories, $actorId);
        });

        $this->reportCounts();
    }

    /**
     * Remove only rows owned by this seeder. Master simulated rows are kept and
     * updated in place so foreign-key ids remain stable across re-runs.
     */
    private function clearPreviousSimulatedFacts(): void
    {
        $scheduleIds = DB::table('trip_schedules')
            ->where(function ($query): void {
                $query->where('trip_code', 'like', self::TRIP_PREFIX.'%')
                    ->orWhere('trip_code', 'like', self::LEGACY_TRIP_PREFIX.'%');
            })
            ->pluck('id');

        DB::table('incidents')
            ->where(function ($query): void {
                $query->where('incident_no', 'like', self::INCIDENT_PREFIX.'%')
                    ->orWhere('incident_no', 'like', self::LEGACY_INCIDENT_PREFIX.'%');
            })
            ->delete();

        DB::table('daily_driver_reports')
            ->where(function ($query): void {
                $query->where('ddr_no', 'like', self::DDR_PREFIX.'%')
                    ->orWhere('ddr_no', 'like', self::LEGACY_DDR_PREFIX.'%')
                    ->orWhere('trip_ticket', 'like', self::TRIP_PREFIX.'%')
                    ->orWhere('trip_ticket', 'like', self::LEGACY_TRIP_PREFIX.'%');
            })
            ->delete();

        if ($scheduleIds->isNotEmpty()) {
            DB::table('trip_assignments')
                ->whereIn('trip_schedule_id', $scheduleIds)
                ->delete();
        }

        DB::table('trip_schedules')
            ->where(function ($query): void {
                $query->where('trip_code', 'like', self::TRIP_PREFIX.'%')
                    ->orWhere('trip_code', 'like', self::LEGACY_TRIP_PREFIX.'%');
            })
            ->delete();

        DB::table('driver_attendances')
            ->where(function ($query): void {
                $query->where('driver_id', 'like', self::DRIVER_PREFIX.'%')
                    ->orWhere('driver_id', 'like', self::LEGACY_DRIVER_PREFIX.'%');
            })
            ->delete();

        DB::table('purchase_orders')
            ->where(function ($query): void {
                $query->where('po_no', 'like', self::PO_PREFIX.'%')
                    ->orWhere('po_no', 'like', self::LEGACY_PO_PREFIX.'%');
            })
            ->delete();

        DB::table('purchase_requests')
            ->where(function ($query): void {
                $query->where('pr_no', 'like', self::PR_PREFIX.'%')
                    ->orWhere('pr_no', 'like', self::LEGACY_PR_PREFIX.'%');
            })
            ->delete();

        DB::table('job_orders')
            ->where(function ($query): void {
                $query->where('job_order_no', 'like', self::JO_PREFIX.'%')
                    ->orWhere('job_order_no', 'like', self::LEGACY_JO_PREFIX.'%');
            })
            ->delete();

        DB::table('stock_movements')
            ->whereIn('source', ['demo', 'simulated'])
            ->delete();
    }

    private function migrateLegacyMasterIdentifiers(): void
    {
        for ($index = 1; $index <= 8; $index++) {
            DB::table('buses')
                ->where('bus_no', self::LEGACY_BUS_PREFIX.(100 + $index))
                ->update(['bus_no' => 'GCT-'.(200 + $index)]);

            DB::table('drivers')
                ->where('driver_id', self::LEGACY_DRIVER_PREFIX.str_pad((string) $index, 3, '0', STR_PAD_LEFT))
                ->update(['driver_id' => self::DRIVER_PREFIX.str_pad((string) $index, 3, '0', STR_PAD_LEFT)]);
        }

        for ($index = 1; $index <= 5; $index++) {
            DB::table('shuttle_routes')
                ->where('route_code', self::LEGACY_ROUTE_PREFIX.str_pad((string) $index, 2, '0', STR_PAD_LEFT))
                ->update(['route_code' => self::ROUTE_PREFIX.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }

        for ($index = 1; $index <= 24; $index++) {
            DB::table('inventory_items')
                ->where('item_code', self::LEGACY_PART_PREFIX.str_pad((string) $index, 3, '0', STR_PAD_LEFT))
                ->update(['item_code' => self::PART_PREFIX.str_pad((string) $index, 3, '0', STR_PAD_LEFT)]);
        }
    }

    private function seedBuses(): array
    {
        $definitions = [
            ['GCT-201', 'NBG-8201', 'Hino RK1JST', '2020', 50],
            ['GCT-202', 'NBG-8202', 'Isuzu LV123', '2019', 45],
            ['GCT-203', 'NBG-8203', 'Hyundai Universe', '2021', 50],
            ['GCT-204', 'NBG-8204', 'Yutong ZK6122H9', '2020', 55],
            ['GCT-205', 'NBG-8205', 'Hino FC9JL7A', '2018', 45],
            ['GCT-206', 'NBG-8206', 'Daewoo BS106', '2019', 48],
            ['GCT-207', 'NBG-8207', 'Kia Grandbird', '2022', 50],
            ['GCT-208', 'NBG-8208', 'Mitsubishi Fuso', '2021', 50],
        ];

        $rows = [];
        foreach ($definitions as $index => [$busNo, $plate, $model, $year, $capacity]) {
            DB::table('buses')->updateOrInsert(
                ['bus_no' => $busNo],
                [
                    'plate_no' => $plate,
                    'bus_model' => $model,
                    'year_model' => $year,
                    'capacity' => $capacity,
                    'route_grouping' => 'Southern Luzon Operations',
                    'status' => 'Active',
                    'latest_gps_km' => 42000 + ($index * 3850),
                    'latest_gps_at' => Carbon::create(2026, 9, 23, 20, 0),
                    'last_pms_km' => 40000 + ($index * 3850),
                    'pms_interval_km' => 5000,
                    'next_pms_km' => 45000 + ($index * 3850),
                    'created_at' => Carbon::create(2026, 5, 1, 8, 0),
                    'updated_at' => Carbon::create(2026, 9, 23, 20, 0),
                ]
            );

            $rows[] = (array) DB::table('buses')->where('bus_no', $busNo)->first();
        }

        return $rows;
    }

    private function seedDrivers(): array
    {
        $definitions = [
            ['GCT-DRV-001', 'Ramon Santos', 'Morning'],
            ['GCT-DRV-002', 'Joel Mendoza', 'Morning'],
            ['GCT-DRV-003', 'Marco Reyes', 'Afternoon'],
            ['GCT-DRV-004', 'Dennis Cruz', 'Afternoon'],
            ['GCT-DRV-005', 'Arnel Garcia', 'Night'],
            ['GCT-DRV-006', 'Victor Ramos', 'Morning'],
            ['GCT-DRV-007', 'Paolo Flores', 'Afternoon'],
            ['GCT-DRV-008', 'Nestor Aquino', 'Night'],
        ];

        $rows = [];
        foreach ($definitions as $index => [$driverId, $name, $shift]) {
            DB::table('drivers')->updateOrInsert(
                ['driver_id' => $driverId],
                [
                    'driver_name' => $name,
                    'shift' => $shift,
                    'contact_number' => '0917'.str_pad((string) (7000000 + $index), 7, '0', STR_PAD_LEFT),
                    'license_number' => 'N02-26-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'license_expiration' => Carbon::create(2027, 12, 31)->toDateString(),
                    'employment_status' => 'Active',
                    'created_at' => Carbon::create(2026, 5, 1, 8, 0),
                    'updated_at' => Carbon::create(2026, 9, 23, 20, 0),
                ]
            );

            $rows[] = (array) DB::table('drivers')->where('driver_id', $driverId)->first();
        }

        return $rows;
    }

    private function seedRoutes(): array
    {
        $definitions = [
            ['GCT-RT-01', 'Batangas City - Lipa', 'Batangas City', 'Lipa City', 31.5, 55],
            ['GCT-RT-02', 'Lipa - Tanauan', 'Lipa City', 'Tanauan City', 23.4, 45],
            ['GCT-RT-03', 'Tanauan - Calamba', 'Tanauan City', 'Calamba City', 28.7, 50],
            ['GCT-RT-04', 'Calamba - Biñan', 'Calamba City', 'Biñan City', 26.2, 50],
            ['GCT-RT-05', 'Sto. Tomas - Alabang', 'Sto. Tomas City', 'Alabang, Muntinlupa', 43.8, 70],
        ];

        $rows = [];
        foreach ($definitions as [$code, $name, $origin, $destination, $distance, $minutes]) {
            DB::table('shuttle_routes')->updateOrInsert(
                ['route_code' => $code],
                [
                    'route_name' => $name,
                    'origin' => $origin,
                    'destination' => $destination,
                    'distance_km' => $distance,
                    'estimated_time_minutes' => $minutes,
                    'status' => 'Active',
                    'created_at' => Carbon::create(2026, 5, 1, 8, 0),
                    'updated_at' => Carbon::create(2026, 9, 23, 20, 0),
                ]
            );

            $rows[] = (array) DB::table('shuttle_routes')->where('route_code', $code)->first();
        }

        return $rows;
    }

    private function seedDelayFrontendData(array $drivers, array $buses, array $routes, ?int $actorId): void
    {
        $start = Carbon::create(2026, 6, 1)->startOfDay();
        $end = Carbon::create(2026, 9, 23)->startOfDay();
        $slots = ['06:00', '08:30', '15:30', '18:00'];
        $delayPattern = [-3, 0, 3, 6, 9, 14, 22, 5];
        $travelVariance = [-4, -1, 0, 3, 7, 12, 5];
        $tripCounter = 0;
        $incidentCounter = 0;

        for ($date = $start->copy(), $dayIndex = 0; $date->lte($end); $date->addDay(), $dayIndex++) {
            foreach ($slots as $slotIndex => $slot) {
                $tripCounter++;

                $route = $routes[($dayIndex + $slotIndex) % count($routes)];
                $driver = $drivers[(($dayIndex * 2) + $slotIndex) % count($drivers)];
                $bus = $buses[($dayIndex + ($slotIndex * 3)) % count($buses)];

                $tripDate = $date->toDateString();
                $scheduledDeparture = Carbon::createFromFormat('Y-m-d H:i', $tripDate.' '.$slot);
                $scheduledDuration = (int) $route['estimated_time_minutes'];
                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($scheduledDuration);
                $tripCode = self::TRIP_PREFIX.$date->format('ymd').'-'.str_pad((string) ($slotIndex + 1), 2, '0', STR_PAD_LEFT);

                $shift = (int) substr($slot, 0, 2) < 12
                    ? 'Morning'
                    : ((int) substr($slot, 0, 2) < 17 ? 'Afternoon' : 'Night');

                DB::table('driver_attendances')->updateOrInsert(
                    [
                        'driver_id' => $driver['driver_id'],
                        'attendance_date' => $tripDate,
                    ],
                    [
                        'driver_name' => $driver['driver_name'],
                        'shift' => $shift,
                        'bus_assignment' => $bus['bus_no'],
                        'time_in' => $scheduledDeparture->copy()->subMinutes(35)->format('H:i:s'),
                        'time_out' => $scheduledArrival->copy()->addMinutes(45)->format('H:i:s'),
                        'status' => (($dayIndex + $slotIndex) % 17 === 0) ? 'Late' : 'Present',
                        'created_at' => $date->copy()->setTime(5, 0),
                        'updated_at' => $date->copy()->setTime(21, 0),
                    ]
                );

                $attendanceId = (int) DB::table('driver_attendances')
                    ->where('driver_id', $driver['driver_id'])
                    ->whereDate('attendance_date', $tripDate)
                    ->value('id');

                $scheduleId = DB::table('trip_schedules')->insertGetId([
                    'trip_code' => $tripCode,
                    'trip_date' => $tripDate,
                    'shuttle_route_id' => $route['id'],
                    'departure_time' => $scheduledDeparture->format('H:i:s'),
                    'estimated_arrival_time' => $scheduledArrival->format('H:i:s'),
                    'shift' => $shift,
                    'assignment_status' => 'Assigned',
                    'status' => 'Completed',
                    'notes' => 'Scheduled passenger service record for operational planning.',
                    'created_by' => $actorId,
                    'created_at' => $date->copy()->subDay()->setTime(16, 0),
                    'updated_at' => $date->copy()->setTime(21, 0),
                ]);

                $assignmentId = DB::table('trip_assignments')->insertGetId([
                    'trip_schedule_id' => $scheduleId,
                    'driver_attendance_id' => $attendanceId,
                    'driver_id' => $driver['driver_id'],
                    'driver_name' => $driver['driver_name'],
                    'bus_id' => $bus['id'],
                    'original_bus_id' => null,
                    'assigned_by' => $actorId,
                    'created_at' => $date->copy()->subDay()->setTime(16, 5),
                    'updated_at' => $date->copy()->setTime(21, 0),
                ]);

                $departureDelay = $delayPattern[($dayIndex + ($slotIndex * 2)) % count($delayPattern)];
                $durationVariance = $travelVariance[(($dayIndex * 3) + $slotIndex) % count($travelVariance)];

                $hasIncident = $tripCounter % 11 === 0;
                if ($hasIncident) {
                    $durationVariance += ($tripCounter % 22 === 0) ? 24 : 14;
                }

                $actualDeparture = $scheduledDeparture->copy()->addMinutes($departureDelay);
                $actualArrival = $actualDeparture->copy()->addMinutes(max(20, $scheduledDuration + $durationVariance));

                DB::table('daily_driver_reports')->insert([
                    'ddr_no' => self::DDR_PREFIX.$date->format('ymd').'-'.str_pad((string) ($slotIndex + 1), 2, '0', STR_PAD_LEFT),
                    'report_date' => $tripDate,
                    'driver_id' => $driver['driver_id'],
                    'driver_name' => $driver['driver_name'],
                    'bus_id' => $bus['id'],
                    'trip_schedule_id' => $scheduleId,
                    'trip_assignment_id' => $assignmentId,
                    'trip_ticket' => $tripCode,
                    'from_location' => $route['origin'],
                    'to_location' => $route['destination'],
                    'departure_time' => $actualDeparture->format('H:i:s'),
                    'arrival_time' => $actualArrival->format('H:i:s'),
                    'passengers' => 14 + (($dayIndex * 3 + $slotIndex * 5) % 31),
                    'encoded_by' => $actorId,
                    'created_at' => $actualArrival->copy()->addMinutes(10),
                    'updated_at' => $actualArrival->copy()->addMinutes(10),
                ]);

                if ($hasIncident) {
                    $incidentCounter++;
                    $breakdown = $tripCounter % 22 === 0;
                    $type = $breakdown ? 'Breakdown' : (($tripCounter % 33 === 0) ? 'Accident' : 'Traffic');
                    $reportedAt = $scheduledDeparture->copy()->subMinutes(20);

                    DB::table('incidents')->insert([
                        'incident_no' => self::INCIDENT_PREFIX.str_pad((string) $incidentCounter, 4, '0', STR_PAD_LEFT),
                        'trip_schedule_id' => $scheduleId,
                        'trip_assignment_id' => $assignmentId,
                        'bus_id' => $bus['id'],
                        'driver_id' => $driver['driver_id'],
                        'driver_name' => $driver['driver_name'],
                        'incident_type' => $type,
                        'location' => $route['origin'].' corridor',
                        'description' => $type.' event recorded during scheduled service.',
                        'incident_reported_at' => $reportedAt,
                        'status' => 'Resolved',
                        'resolution_notes' => 'Incident cleared; trip continued after an operational delay.',
                        'resolved_at' => $actualArrival->copy()->addMinutes(20),
                        'reported_by' => $actorId,
                        'resolved_by' => $actorId,
                        'created_at' => $reportedAt,
                        'updated_at' => $actualArrival->copy()->addMinutes(20),
                    ]);
                }
            }
        }
    }

    private function seedInventoryItems(): array
    {
        $definitions = [
            ['Brake Pad Set', 'Brakes', 'set', 12, 'Batangas Auto Parts Center'],
            ['Oil Filter', 'Filters', 'pcs', 15, 'Batangas Auto Parts Center'],
            ['Fuel Filter', 'Filters', 'pcs', 12, 'Southern Luzon Parts Supply'],
            ['Air Filter', 'Filters', 'pcs', 10, 'Southern Luzon Parts Supply'],
            ['Fan Belt', 'Engine', 'pcs', 8, 'Fleet Parts Center'],
            ['Alternator Belt', 'Engine', 'pcs', 8, 'Fleet Parts Center'],
            ['12V Battery', 'Electrical', 'pcs', 6, 'Calabarzon Battery Center'],
            ['Headlight Bulb', 'Electrical', 'pcs', 20, 'Calabarzon Electrical Supply'],
            ['Tail Light Bulb', 'Electrical', 'pcs', 20, 'Calabarzon Electrical Supply'],
            ['Wheel Bearing', 'Suspension', 'pcs', 10, 'Fleet Parts Center'],
            ['Brake Shoe Set', 'Brakes', 'set', 8, 'Batangas Auto Parts Center'],
            ['Clutch Disc', 'Drivetrain', 'pcs', 5, 'Southern Luzon Parts Supply'],
            ['Coolant Hose', 'Cooling', 'pcs', 10, 'Fleet Parts Center'],
            ['Radiator Cap', 'Cooling', 'pcs', 12, 'Fleet Parts Center'],
            ['Wiper Blade Pair', 'Body', 'pair', 12, 'Batangas Auto Parts Center'],
            ['Engine Oil 15W-40', 'Fluids', 'liter', 80, 'Calabarzon Lubricants'],
            ['Gear Oil', 'Fluids', 'liter', 40, 'Calabarzon Lubricants'],
            ['Engine Coolant', 'Fluids', 'liter', 50, 'Calabarzon Lubricants'],
            ['Grease Cartridge', 'Fluids', 'pcs', 20, 'Calabarzon Lubricants'],
            ['Bus Tire 10R22.5', 'Tires', 'pcs', 10, 'South Luzon Tire Center'],
            ['Inner Tube 10R22.5', 'Tires', 'pcs', 12, 'South Luzon Tire Center'],
            ['Air Dryer Cartridge', 'Pneumatic', 'pcs', 8, 'Fleet Parts Center'],
            ['Fuel Hose', 'Engine', 'meter', 15, 'Southern Luzon Parts Supply'],
            ['Fuse Assortment', 'Electrical', 'box', 8, 'Calabarzon Electrical Supply'],
        ];

        $items = [];
        foreach ($definitions as $index => [$name, $category, $unit, $reorder, $supplier]) {
            $code = self::PART_PREFIX.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            DB::table('inventory_items')->updateOrInsert(
                ['item_code' => $code],
                [
                    'item_name' => ''.$name,
                    'category' => $category,
                    'quantity_available' => 0,
                    'unit_of_measurement' => $unit,
                    'reorder_level' => $reorder,
                    'supplier' => ''.$supplier,
                    'storage_location' => 'Rack '.chr(65 + ($index % 6)).'-'.(($index % 4) + 1),
                    'created_at' => Carbon::create(2026, 5, 18, 8, 0),
                    'updated_at' => Carbon::create(2026, 9, 23, 18, 0),
                ]
            );

            $items[] = (array) DB::table('inventory_items')->where('item_code', $code)->first();
        }

        return $items;
    }

    private function seedMaintenancePurchaseStories(array $items, array $buses): array
    {
        $stories = [];
        $poStatuses = ['Picked Up', 'Delivered', 'Ordered', null];
        $prStatuses = ['Issued', 'Delivered', 'Ordered', 'Approved'];
        $joStatuses = ['Completed', 'On Going', 'On Going', 'On Hold'];
        $partStatuses = ['Issued', 'Delivered', 'Ordered', 'Approved'];

        for ($index = 0; $index < 12; $index++) {
            $storyNo = $index + 1;
            $item = $items[$index];
            $bus = $buses[$index % count($buses)];
            $storyDate = Carbon::create(2026, 9, 2)->addDays($index * 1 + intdiv($index, 3));
            $quantity = 2 + ($index % 5);
            $joNo = self::JO_PREFIX.str_pad((string) $storyNo, 4, '0', STR_PAD_LEFT);
            $prNo = self::PR_PREFIX.str_pad((string) $storyNo, 4, '0', STR_PAD_LEFT);
            $state = $index % 4;

            DB::table('job_orders')->insert([
                'job_order_no' => $joNo,
                'bus_no' => $bus['bus_no'],
                'problem_issue' => 'Maintenance inspection identified a need for '.$item['item_name'].'.',
                'maintenance_type' => ($index % 3 === 0) ? 'Corrective' : 'Preventive',
                'assigned_mechanic' => 'Mechanic '.str_pad((string) (($index % 4) + 1), 2, '0', STR_PAD_LEFT),
                'part_needed' => $item['item_name'].' - Qty: '.$quantity.' '.$item['unit_of_measurement'],
                'start_date' => $storyDate->copy()->setTime(8, 0),
                'completion_date' => $joStatuses[$state] === 'Completed'
                    ? $storyDate->copy()->addDays(2)->setTime(16, 30)
                    : null,
                'status' => $joStatuses[$state],
                'part_status' => $partStatuses[$state],
                'created_at' => $storyDate->copy()->setTime(7, 45),
                'updated_at' => $storyDate->copy()->addDays(2)->setTime(16, 30),
            ]);

            $prId = DB::table('purchase_requests')->insertGetId([
                'pr_no' => $prNo,
                'job_order_no' => $joNo,
                'bus_no' => $bus['bus_no'],
                'item' => $item['item_name'],
                'quantity' => $quantity,
                'remarks' => 'Parts request generated from '.$joNo.'.',
                'status' => $prStatuses[$state],
                'source_type' => 'Maintenance Request',
                'source_inventory_item_id' => $item['id'],
                'created_at' => $storyDate->copy()->addHours(2),
                'updated_at' => $storyDate->copy()->addDays(1),
            ]);

            $poNo = null;
            if ($poStatuses[$state] !== null) {
                $poNo = self::PO_PREFIX.str_pad((string) $storyNo, 4, '0', STR_PAD_LEFT);
                $cost = 450 + ($index * 175);
                $gross = $cost * $quantity;
                $delivery = 150.00;
                $vat = round($gross * 0.12, 2);
                $net = $gross + $delivery + $vat;

                DB::table('purchase_orders')->insert([
                    'po_no' => $poNo,
                    'po_date' => $storyDate->copy()->addDay()->toDateString(),
                    'purchase_request_id' => $prId,
                    'supplier_name' => $item['supplier'],
                    'supplier_address_tel' => 'CALABARZON service area',
                    'terms' => '7-day delivery',
                    'terms_of_payment' => 'Net 30',
                    'purpose' => 'Fleet maintenance replenishment linked to '.$joNo.'.',
                    'items' => json_encode([[
                        'pr_no' => $prNo,
                        'bus_no' => $bus['bus_no'],
                        'employee' => 'Mechanic '.str_pad((string) (($index % 4) + 1), 2, '0', STR_PAD_LEFT),
                        'item_description' => $item['item_name'],
                        'quantity' => $quantity,
                        'unit' => $item['unit_of_measurement'],
                        'cost' => $cost,
                    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'gross_amount' => $gross,
                    'delivery_fee' => $delivery,
                    'discount' => 0,
                    'vat' => $vat,
                    'net_amount' => $net,
                    'status' => $poStatuses[$state],
                    'created_at' => $storyDate->copy()->addDay()->setTime(9, 0),
                    'updated_at' => $storyDate->copy()->addDays(2)->setTime(9, 0),
                ]);
            }

            $stories[$item['id']] = [
                'job_order_no' => $joNo,
                'purchase_request_no' => $prNo,
                'purchase_order_no' => $poNo,
                'purchase_order_status' => $poStatuses[$state],
                'quantity' => $quantity,
                'story_date' => $storyDate,
            ];
        }

        return $stories;
    }

    private function seedInventoryMovementHistory(array $items, array $stories, ?int $actorId): void
    {
        $start = Carbon::create(2026, 5, 18)->startOfDay();
        $weeks = 19;

        foreach ($items as $itemIndex => $item) {
            $stock = 42 + (($itemIndex % 6) * 9);
            $initialStock = $stock;

            $this->insertMovement(
                $item,
                'OPENING-'.$item['item_code'],
                'Stock In',
                $initialStock,
                0,
                $stock,
                'Opening inventory balance.',
                $actorId,
                $start->copy()->subDays(2)->setTime(9, 0)
            );

            for ($week = 0; $week < $weeks; $week++) {
                $weekDate = $start->copy()->addWeeks($week);

                if ($week > 0 && $week % 4 === 0) {
                    $replenish = 20 + (($itemIndex + $week) % 4) * 5;
                    $previous = $stock;
                    $stock += $replenish;
                    $reference = 'GRN-'.$item['item_code'].'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT);

                    $this->insertMovement(
                        $item,
                        $reference,
                        'Stock In',
                        $replenish,
                        $previous,
                        $stock,
                        'Scheduled inventory replenishment.',
                        $actorId,
                        $weekDate->copy()->setTime(9, 15)
                    );
                }

                $issueQty = 1 + (($itemIndex + ($week * 2)) % 5);
                if ($stock < $issueQty + 2) {
                    $previous = $stock;
                    $topUp = 25;
                    $stock += $topUp;
                    $this->insertMovement(
                        $item,
                        'URGENT-GRN-'.$item['item_code'].'-W'.$week,
                        'Stock In',
                        $topUp,
                        $previous,
                        $stock,
                        'Emergency replenishment to keep stock history realistic.',
                        $actorId,
                        $weekDate->copy()->setTime(10, 0)
                    );
                }

                $previous = $stock;
                $stock -= $issueQty;
                $story = $stories[$item['id']] ?? null;
                $reference = ($story && $week === $weeks - 2)
                    ? $story['job_order_no']
                    : 'ISS-'.$item['item_code'].'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT).'-A';

                $this->insertMovement(
                    $item,
                    $reference,
                    'Stock Out',
                    -$issueQty,
                    $previous,
                    $stock,
                    $story && $week === $weeks - 2
                        ? 'Part issuance linked to '.$story['job_order_no'].'.'
                        : 'Routine spare-part issuance.',
                    $actorId,
                    $weekDate->copy()->addDay()->setTime(14, 0)
                );

                if (($itemIndex + $week) % 2 === 0) {
                    $secondQty = 1 + (($itemIndex + $week) % 3);
                    if ($stock < $secondQty + 1) {
                        $secondQty = max(1, $stock - 1);
                    }

                    if ($secondQty > 0) {
                        $previous = $stock;
                        $stock -= $secondQty;
                        $this->insertMovement(
                            $item,
                            'ISS-'.$item['item_code'].'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT).'-B',
                            'Stock Out',
                            -$secondQty,
                            $previous,
                            $stock,
                            'Secondary issuance during the same week.',
                            $actorId,
                            $weekDate->copy()->addDays(3)->setTime(10, 30)
                        );
                    }
                }

                if ($story && $week === $weeks - 1 && in_array($story['purchase_order_status'], ['Delivered', 'Picked Up'], true)) {
                    $received = $story['quantity'] + 8;
                    $previous = $stock;
                    $stock += $received;
                    $this->insertMovement(
                        $item,
                        $story['purchase_order_no'],
                        'Stock In',
                        $received,
                        $previous,
                        $stock,
                        'Receipt linked to '.$story['purchase_order_no'].'.',
                        $actorId,
                        $weekDate->copy()->addDays(4)->setTime(15, 0)
                    );
                }
            }

            DB::table('inventory_items')
                ->where('id', $item['id'])
                ->update([
                    'quantity_available' => $stock,
                    'updated_at' => Carbon::create(2026, 9, 23, 18, 0),
                ]);
        }
    }

    private function insertMovement(
        array $item,
        string $reference,
        string $type,
        int $quantityChange,
        int $previousStock,
        int $newStock,
        string $remarks,
        ?int $actorId,
        Carbon $createdAt
    ): void {
        DB::table('stock_movements')->insert([
            'inventory_item_id' => $item['id'],
            'item_code' => $item['item_code'],
            'item_name' => $item['item_name'],
            'reference_no' => $reference,
            'movement_type' => $type,
            'quantity_change' => $quantityChange,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'unit' => $item['unit_of_measurement'],
            'remarks' => $remarks,
            'created_by' => $actorId,
            'source' => 'simulated',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function reportCounts(): void
    {
        $counts = [
            'Simulated trips' => DB::table('trip_schedules')->where('trip_code', 'like', self::TRIP_PREFIX.'%')->count(),
            'Simulated DDR rows' => DB::table('daily_driver_reports')->where('ddr_no', 'like', self::DDR_PREFIX.'%')->count(),
            'Simulated incidents' => DB::table('incidents')->where('incident_no', 'like', self::INCIDENT_PREFIX.'%')->count(),
            'Simulated inventory parts' => DB::table('inventory_items')->where('item_code', 'like', self::PART_PREFIX.'%')->count(),
            'Simulated stock movements' => DB::table('stock_movements')->where('source', 'simulated')->count(),
            'Simulated job orders' => DB::table('job_orders')->where('job_order_no', 'like', self::JO_PREFIX.'%')->count(),
            'Simulated purchase requests' => DB::table('purchase_requests')->where('pr_no', 'like', self::PR_PREFIX.'%')->count(),
            'Simulated purchase orders' => DB::table('purchase_orders')->where('po_no', 'like', self::PO_PREFIX.'%')->count(),
        ];

        if ($this->command) {
            $this->command->info('Realistic simulated dataset seeded without truncating genuine records.');
            $this->command->table(
                ['Dataset', 'Rows'],
                collect($counts)->map(fn (int $count, string $label): array => [$label, $count])->values()->all()
            );
            $this->command->warn('All generated rows are SIMULATED. Do not present them as genuine GCT operational history.');
        }
    }
}
