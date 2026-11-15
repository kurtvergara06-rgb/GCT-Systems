<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Non-destructive client demonstration dataset.
 *
 * The records created here are intentionally visible in the normal frontend
 * modules (Operation, Incidents, Maintenance, Warehouse, Purchase) while
 * remaining clearly isolated from genuine production ML training:
 *
 * - Delay demo trips use the TRIP-DEMO-* prefix. The genuine Delay #3 export
 *   excludes the configured TRIP-* demo prefix.
 * - Inventory movements are written with source="demo". Genuine Inventory #4
 *   training reads only stock_movements.source="app".
 *
 * Re-running the seeder replaces only its own DEMO-* fact rows. It never
 * truncates application tables and never deletes genuine operational records.
 */
class ClientDemoDataSeeder extends Seeder
{
    private const TRIP_PREFIX = 'TRIP-DEMO-';
    private const DDR_PREFIX = 'DEMO-DDR-';
    private const INCIDENT_PREFIX = 'DEMO-INC-';
    private const JO_PREFIX = 'DEMO-JO-';
    private const PR_PREFIX = 'DEMO-PR-';
    private const PO_PREFIX = 'DEMO-PO-';
    private const PART_PREFIX = 'DEMO-PART-';
    private const BUS_PREFIX = 'DEMO-BUS-';
    private const DRIVER_PREFIX = 'DEMO-DRV-';
    private const ROUTE_PREFIX = 'DEMO-RT-';

    public function run(): void
    {
        mt_srand(20260924);

        DB::transaction(function (): void {
            $this->clearPreviousDemoFacts();

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
     * Remove only rows owned by this seeder. Master demo rows are kept and
     * updated in place so foreign-key ids remain stable across re-runs.
     */
    private function clearPreviousDemoFacts(): void
    {
        $scheduleIds = DB::table('trip_schedules')
            ->where('trip_code', 'like', self::TRIP_PREFIX.'%')
            ->pluck('id');

        DB::table('incidents')
            ->where('incident_no', 'like', self::INCIDENT_PREFIX.'%')
            ->delete();

        DB::table('daily_driver_reports')
            ->where(function ($query): void {
                $query->where('ddr_no', 'like', self::DDR_PREFIX.'%')
                    ->orWhere('trip_ticket', 'like', self::TRIP_PREFIX.'%');
            })
            ->delete();

        if ($scheduleIds->isNotEmpty()) {
            DB::table('trip_assignments')
                ->whereIn('trip_schedule_id', $scheduleIds)
                ->delete();
        }

        DB::table('trip_schedules')
            ->where('trip_code', 'like', self::TRIP_PREFIX.'%')
            ->delete();

        DB::table('driver_attendances')
            ->where('driver_id', 'like', self::DRIVER_PREFIX.'%')
            ->delete();

        DB::table('purchase_orders')
            ->where('po_no', 'like', self::PO_PREFIX.'%')
            ->delete();

        DB::table('purchase_requests')
            ->where('pr_no', 'like', self::PR_PREFIX.'%')
            ->delete();

        DB::table('job_orders')
            ->where('job_order_no', 'like', self::JO_PREFIX.'%')
            ->delete();

        DB::table('stock_movements')
            ->where('source', 'demo')
            ->where('reference_no', 'like', 'DEMO-%')
            ->delete();
    }

    private function seedBuses(): array
    {
        $definitions = [
            ['DEMO-BUS-101', 'DMB-1101', 'Hino RK1JST', '2020', 50],
            ['DEMO-BUS-102', 'DMB-1102', 'Isuzu LV123', '2019', 45],
            ['DEMO-BUS-103', 'DMB-1103', 'Hyundai Universe', '2021', 50],
            ['DEMO-BUS-104', 'DMB-1104', 'Yutong ZK6122H9', '2020', 55],
            ['DEMO-BUS-105', 'DMB-1105', 'Hino FC9JL7A', '2018', 45],
            ['DEMO-BUS-106', 'DMB-1106', 'Daewoo BS106', '2019', 48],
            ['DEMO-BUS-107', 'DMB-1107', 'Kia Grandbird', '2022', 50],
            ['DEMO-BUS-108', 'DMB-1108', 'Mitsubishi Fuso', '2021', 50],
        ];

        $rows = [];
        foreach ($definitions as $index => [$busNo, $plate, $model, $year, $capacity]) {
            DB::table('buses')->updateOrInsert(
                ['bus_no' => $busNo],
                [
                    'plate_no' => $plate,
                    'bus_model' => '[DEMO] '.$model,
                    'year_model' => $year,
                    'capacity' => $capacity,
                    'route_grouping' => '[DEMO] Client Presentation Fleet',
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
            ['DEMO-DRV-001', 'Ramon Santos (Demo)', 'Morning'],
            ['DEMO-DRV-002', 'Joel Mendoza (Demo)', 'Morning'],
            ['DEMO-DRV-003', 'Marco Reyes (Demo)', 'Afternoon'],
            ['DEMO-DRV-004', 'Dennis Cruz (Demo)', 'Afternoon'],
            ['DEMO-DRV-005', 'Arnel Garcia (Demo)', 'Night'],
            ['DEMO-DRV-006', 'Victor Ramos (Demo)', 'Morning'],
            ['DEMO-DRV-007', 'Paolo Flores (Demo)', 'Afternoon'],
            ['DEMO-DRV-008', 'Nestor Aquino (Demo)', 'Night'],
        ];

        $rows = [];
        foreach ($definitions as $index => [$driverId, $name, $shift]) {
            DB::table('drivers')->updateOrInsert(
                ['driver_id' => $driverId],
                [
                    'driver_name' => $name,
                    'shift' => $shift,
                    'contact_number' => '0917'.str_pad((string) (7000000 + $index), 7, '0', STR_PAD_LEFT),
                    'license_number' => 'DEMO-LIC-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
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
            ['DEMO-RT-01', '[DEMO] Batangas City - Lipa', 'Batangas City', 'Lipa City', 31.5, 55],
            ['DEMO-RT-02', '[DEMO] Lipa - Tanauan', 'Lipa City', 'Tanauan City', 23.4, 45],
            ['DEMO-RT-03', '[DEMO] Tanauan - Calamba', 'Tanauan City', 'Calamba City', 28.7, 50],
            ['DEMO-RT-04', '[DEMO] Calamba - Biñan', 'Calamba City', 'Biñan City', 26.2, 50],
            ['DEMO-RT-05', '[DEMO] Sto. Tomas - Alabang', 'Sto. Tomas City', 'Alabang, Muntinlupa', 43.8, 70],
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
                    'notes' => 'DEMO / SYNTHETIC client presentation trip. Not genuine GCT history.',
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
                        'description' => 'DEMO / SYNTHETIC '.$type.' event included for the client presentation.',
                        'incident_reported_at' => $reportedAt,
                        'status' => 'Resolved',
                        'resolution_notes' => 'Demo incident cleared; trip continued after an operational delay.',
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
            ['Brake Pad Set', 'Brakes', 'set', 12, 'Batangas Auto Parts Demo'],
            ['Oil Filter', 'Filters', 'pcs', 15, 'Batangas Auto Parts Demo'],
            ['Fuel Filter', 'Filters', 'pcs', 12, 'Southern Luzon Parts Demo'],
            ['Air Filter', 'Filters', 'pcs', 10, 'Southern Luzon Parts Demo'],
            ['Fan Belt', 'Engine', 'pcs', 8, 'Fleet Parts Center Demo'],
            ['Alternator Belt', 'Engine', 'pcs', 8, 'Fleet Parts Center Demo'],
            ['12V Battery', 'Electrical', 'pcs', 6, 'Calabarzon Battery Demo'],
            ['Headlight Bulb', 'Electrical', 'pcs', 20, 'Calabarzon Electrical Demo'],
            ['Tail Light Bulb', 'Electrical', 'pcs', 20, 'Calabarzon Electrical Demo'],
            ['Wheel Bearing', 'Suspension', 'pcs', 10, 'Fleet Parts Center Demo'],
            ['Brake Shoe Set', 'Brakes', 'set', 8, 'Batangas Auto Parts Demo'],
            ['Clutch Disc', 'Drivetrain', 'pcs', 5, 'Southern Luzon Parts Demo'],
            ['Coolant Hose', 'Cooling', 'pcs', 10, 'Fleet Parts Center Demo'],
            ['Radiator Cap', 'Cooling', 'pcs', 12, 'Fleet Parts Center Demo'],
            ['Wiper Blade Pair', 'Body', 'pair', 12, 'Batangas Auto Parts Demo'],
            ['Engine Oil 15W-40', 'Fluids', 'liter', 80, 'Calabarzon Lubricants Demo'],
            ['Gear Oil', 'Fluids', 'liter', 40, 'Calabarzon Lubricants Demo'],
            ['Engine Coolant', 'Fluids', 'liter', 50, 'Calabarzon Lubricants Demo'],
            ['Grease Cartridge', 'Fluids', 'pcs', 20, 'Calabarzon Lubricants Demo'],
            ['Bus Tire 10R22.5', 'Tires', 'pcs', 10, 'South Luzon Tire Demo'],
            ['Inner Tube 10R22.5', 'Tires', 'pcs', 12, 'South Luzon Tire Demo'],
            ['Air Dryer Cartridge', 'Pneumatic', 'pcs', 8, 'Fleet Parts Center Demo'],
            ['Fuel Hose', 'Engine', 'meter', 15, 'Southern Luzon Parts Demo'],
            ['Fuse Assortment', 'Electrical', 'box', 8, 'Calabarzon Electrical Demo'],
        ];

        $items = [];
        foreach ($definitions as $index => [$name, $category, $unit, $reorder, $supplier]) {
            $code = self::PART_PREFIX.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            DB::table('inventory_items')->updateOrInsert(
                ['item_code' => $code],
                [
                    'item_name' => '[DEMO] '.$name,
                    'category' => $category,
                    'quantity_available' => 0,
                    'unit_of_measurement' => $unit,
                    'reorder_level' => $reorder,
                    'supplier' => '[DEMO] '.$supplier,
                    'storage_location' => 'DEMO Rack '.chr(65 + ($index % 6)).'-'.(($index % 4) + 1),
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
                'problem_issue' => 'DEMO / SYNTHETIC maintenance finding requiring '.$item['item_name'].'.',
                'maintenance_type' => ($index % 3 === 0) ? 'Corrective' : 'Preventive',
                'assigned_mechanic' => 'Demo Mechanic '.str_pad((string) (($index % 4) + 1), 2, '0', STR_PAD_LEFT),
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
                'remarks' => 'DEMO / SYNTHETIC request linked to '.$joNo.' for client presentation.',
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
                    'supplier_address_tel' => 'DEMO supplier record - CALABARZON',
                    'terms' => 'DEMO: 7-day delivery',
                    'terms_of_payment' => 'DEMO: Net 30',
                    'purpose' => 'DEMO / SYNTHETIC replenishment linked to '.$joNo.'.',
                    'items' => json_encode([[
                        'pr_no' => $prNo,
                        'bus_no' => $bus['bus_no'],
                        'employee' => 'Demo Mechanic '.str_pad((string) (($index % 4) + 1), 2, '0', STR_PAD_LEFT),
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
                'DEMO-INIT-'.$item['item_code'],
                'Stock In',
                $initialStock,
                0,
                $stock,
                'Initial DEMO / SYNTHETIC balance for client presentation.',
                $actorId,
                $start->copy()->subDays(2)->setTime(9, 0)
            );

            for ($week = 0; $week < $weeks; $week++) {
                $weekDate = $start->copy()->addWeeks($week);

                if ($week > 0 && $week % 4 === 0) {
                    $replenish = 20 + (($itemIndex + $week) % 4) * 5;
                    $previous = $stock;
                    $stock += $replenish;
                    $reference = 'DEMO-RESTOCK-'.$item['item_code'].'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT);

                    $this->insertMovement(
                        $item,
                        $reference,
                        'Stock In',
                        $replenish,
                        $previous,
                        $stock,
                        'Scheduled DEMO replenishment.',
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
                        'DEMO-EMERGENCY-'.$item['item_code'].'-W'.$week,
                        'Stock In',
                        $topUp,
                        $previous,
                        $stock,
                        'DEMO emergency replenishment to keep stock history realistic.',
                        $actorId,
                        $weekDate->copy()->setTime(10, 0)
                    );
                }

                $previous = $stock;
                $stock -= $issueQty;
                $story = $stories[$item['id']] ?? null;
                $reference = ($story && $week === $weeks - 2)
                    ? $story['job_order_no']
                    : 'DEMO-ISSUE-'.$item['item_code'].'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT).'-A';

                $this->insertMovement(
                    $item,
                    $reference,
                    'Stock Out',
                    -$issueQty,
                    $previous,
                    $stock,
                    $story && $week === $weeks - 2
                        ? 'DEMO part issuance linked to '.$story['job_order_no'].'.'
                        : 'Routine DEMO spare-part issuance.',
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
                            'DEMO-ISSUE-'.$item['item_code'].'-W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT).'-B',
                            'Stock Out',
                            -$secondQty,
                            $previous,
                            $stock,
                            'Secondary DEMO issuance during the same week.',
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
                        'DEMO receipt linked to '.$story['purchase_order_no'].'.',
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
            'source' => 'demo',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function reportCounts(): void
    {
        $counts = [
            'Demo trips' => DB::table('trip_schedules')->where('trip_code', 'like', self::TRIP_PREFIX.'%')->count(),
            'Demo DDR rows' => DB::table('daily_driver_reports')->where('ddr_no', 'like', self::DDR_PREFIX.'%')->count(),
            'Demo incidents' => DB::table('incidents')->where('incident_no', 'like', self::INCIDENT_PREFIX.'%')->count(),
            'Demo inventory parts' => DB::table('inventory_items')->where('item_code', 'like', self::PART_PREFIX.'%')->count(),
            'Demo stock movements' => DB::table('stock_movements')->where('source', 'demo')->where('reference_no', 'like', 'DEMO-%')->count(),
            'Demo job orders' => DB::table('job_orders')->where('job_order_no', 'like', self::JO_PREFIX.'%')->count(),
            'Demo purchase requests' => DB::table('purchase_requests')->where('pr_no', 'like', self::PR_PREFIX.'%')->count(),
            'Demo purchase orders' => DB::table('purchase_orders')->where('po_no', 'like', self::PO_PREFIX.'%')->count(),
        ];

        if ($this->command) {
            $this->command->info('Client DEMO dataset seeded without truncating genuine records.');
            $this->command->table(
                ['Dataset', 'Rows'],
                collect($counts)->map(fn (int $count, string $label): array => [$label, $count])->values()->all()
            );
            $this->command->warn('All generated rows are DEMO / SYNTHETIC. Do not present them as genuine GCT operational history.');
        }
    }
}
