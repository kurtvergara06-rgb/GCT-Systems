<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populate the GCT System with realistic SAMPLE / DEMO data.
 *
 * Every record generated here is clearly demo data meant for
 * demonstration, presentation and analytics testing only.
 * The existing system design, modules, and schema are left untouched.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        mt_srand(20260905);

        $this->runAll();
    }

    protected function runAll(): void
    {
        $this->wipeDemoTables();

            $users = $this->seedUsers();
            $buses = $this->seedBuses();
            $mechanics = $this->seedMechanics();
            $drivers = $this->seedDrivers();
            $routes = $this->seedRoutes();

            $pmsSchedules = $this->seedPmsSchedules($buses);
            $jobOrders = $this->seedJobOrders($buses, $mechanics, $pmsSchedules);

            $batchUploads = $this->seedBatchUploads($users);

            $this->seedDriverAttendance($drivers, $buses);
            $this->seedMechanicAttendance($mechanics);

            [$tripSchedules, $tripAssignments] = $this->seedTrips($drivers, $buses, $routes, $users);

            [$fuelReports, $gpsRecords] = $this->seedGpsAndFuel($maxTrip = $this->tripData());

            $inventory = $this->seedInventory();
            $this->seedStockMovements($inventory, $jobOrders, $purchaseRequests = [], $purchaseOrders = []);

            $purchaseRequests = $this->seedPurchaseRequests($jobOrders, $inventory, $users);
            $purchaseOrders = $this->seedPurchaseOrders($purchaseRequests, $users);
            $this->seedScheduledPurchases($purchaseOrders);

            $this->seedBatchProcessedAndActivities($batchUploads, $gpsRecords, $fuelReports, $inventory, $purchaseOrders, $users);

            $this->seedActivityLogs($users, $jobOrders, $purchaseRequests, $purchaseOrders, $tripSchedules, $buses, $inventory);

            $this->seedTopbarNotifications($jobOrders, $inventory, $purchaseRequests, $tripSchedules, $pmsSchedules, $users);

            $this->reportCounts();
    }

    protected function wipeDemoTables(): void
    {
        $tables = [
            'topbar_notification_reads', 'topbar_read_states', 'topbar_notifications',
            'activity_logs', 'data_activities', 'batch_processed_records',
            'fuel_reports', 'gps_trip_records', 'batch_uploads',
            'trip_assignments', 'trip_schedules', 'route_stops', 'shuttle_routes',
            'driver_attendances', 'mechanic_attendances', 'drivers', 'mechanics',
            'purchase_orders', 'purchase_requests', 'scheduled_purchases',
            'stock_movements', 'inventory_items',
            'job_orders', 'pms_schedules', 'buses', 'users',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /* ------------------------------------------------------------------ */
    /*  USERS                                                              */
    /* ------------------------------------------------------------------ */

    protected function seedUsers(): array
    {
        $rows = [
            ['System Administrator', 'superadmin@gct.test', 'Admin', 'head'],
            ['Admin Head', 'admin.head@gct.test', 'Admin', 'head'],
            ['Maintenance Head', 'maintenance.head@gct.test', 'Maintenance', 'head'],
            ['Operations Head', 'operations.head@gct.test', 'Operation', 'head'],
            ['Warehouse Head', 'warehouse.head@gct.test', 'Warehouse', 'head'],
            ['Purchasing Head', 'purchasing.head@gct.test', 'Purchase', 'head'],
            ['Maintenance Staff', 'maintenance.staff@gct.test', 'Maintenance', 'staff'],
            ['Operations Staff', 'operations.staff@gct.test', 'Operation', 'staff'],
            ['Warehouse Staff', 'warehouse.staff@gct.test', 'Warehouse', 'staff'],
            ['Purchasing Staff', 'purchasing.staff@gct.test', 'Purchase', 'staff'],
        ];

        $users = [];
        foreach ($rows as $i => [$name, $email, $department, $role]) {
            $id = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt('password123'),
                'role' => $department === 'Admin' ? 'head' : $role,
                'department' => $department,
                'status' => 'Active',
                'email_verified_at' => now(),
                'last_login_at' => Carbon::now()->subMinutes(mt_rand(30, 3000)),
                'created_at' => Carbon::now()->subMonths(5)->addDays($i),
                'updated_at' => now(),
            ]);
            $users[] = ['id' => $id, 'name' => $name, 'role' => $department === 'Admin' ? 'head' : $role, 'department' => $department];
        }

        return $users;
    }

    /* ------------------------------------------------------------------ */
    /*  BUSSES                                                             */
    /* ------------------------------------------------------------------ */

    protected function seedBuses(): array
    {
        $rows = [
            ['GCT-101', 'BGU-1234', 'Hino FC3JJSA', '2019', 45, 'SM City Cebu - Mactan Airport'], // 1
            ['GCT-102', 'BHM-8891', 'Hino RK1JST', '2020', 50, 'Fuente Osmeña - IT Park'], // 2
            ['GCT-103', 'BLE-5510', 'Yutong ZK6122H9', '2018', 55, 'Ayala Center - IT Park'], // 3
            ['GCT-104', 'BMO-7215', 'Hyundai Aero Space', '2021', 50, 'Parkmall - Mactan Newtown'], // 4
            ['GCT-105', 'BPF-3310', 'Isuzu NLR85', '2017', 45, 'Talisay - SM Seaside'], // 5
            ['GCT-106', 'BRR-9102', 'Kia Grandbird', '2019', 50, 'Lahug - Mandaue Reclamation'], // 6
            ['GCT-107', 'BSL-2847', 'Daewoo BS106', '2016', 48, 'SM City Cebu - Mactan Airport'], // 7 Under maintenance
            ['GCT-108', 'GTA-6630', 'Hino RB2JKU', '2022', 55, 'Fuente Osmeña - IT Park'], // 8
            ['GCT-109', 'GTB-8815', 'Yutong ZK6100H', '2020', 50, 'Ayala Center - IT Park'], // 9
            ['GCT-110', 'GTF-4471', 'Nissan Diesel CKA', '2017', 45, 'Parkmall - Mactan Newtown'], // 10
            ['GCT-111', 'GTG-9003', 'Mitsubishi MK306', '2018', 50, 'Talisay - SM Seaside'], // 11
            ['GCT-112', 'GTH-1529', 'Hyundai Universe', '2021', 55, 'Lahug - Mandaue Reclamation'], // 12 Under maintenance
            ['GCT-113', 'GTP-7730', 'Hino RM2PSS', '2019', 50, 'SM City Cebu - Mactan Airport'], // 13
            ['GCT-114', 'GTQ-1104', 'Kia Granbird', '2015', 48, 'Fuente Osmeña - IT Park'], // 14 Inactive
        ];

        $statusByIndex = [1 => 'Active', 7 => 'Under Maintenance', 12 => 'Under Maintenance', 14 => 'Inactive'];

        $buses = [];
        foreach ($rows as $i => [$busNo, $plate, $model, $year, $capacity, $route]) {
            $gpsKm = mt_rand(8, 48) * 1000 + mt_rand(0, 900);
            $lastPmsKm = $gpsKm - mt_rand(0, 2800);
            $status = $statusByIndex[$i + 1] ?? 'Active';

            $id = DB::table('buses')->insertGetId([
                'bus_no' => $busNo,
                'plate_no' => $plate,
                'bus_model' => $model,
                'year_model' => (string) $year,
                'capacity' => $capacity,
                'route_grouping' => $route,
                'status' => $status,
                'latest_gps_km' => $gpsKm,
                'latest_gps_at' => Carbon::now()->subHours(mt_rand(2, 200)),
                'last_pms_km' => $lastPmsKm,
                'pms_interval_km' => 5000,
                'next_pms_km' => $lastPmsKm + 5000,
                'created_at' => Carbon::create(2026, 3, mt_rand(2, 28), 9, mt_rand(0, 59)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 20)),
            ]);
            $buses[] = ['id' => $id, 'bus_no' => $busNo, 'status' => $status, 'route_grouping' => $route, 'gps_km' => $gpsKm, 'last_pms_km' => $lastPmsKm];
        }

        return $buses;
    }

    /* ------------------------------------------------------------------ */
    /*  MECHANICS AND DRIVERS                                              */
    /* ------------------------------------------------------------------ */

    protected function seedMechanics(): array
    {
        $rows = [
            ['MECH-001', 'Roy Gantuangco', 'Morning', 'Engine'],
            ['MECH-002', 'Edwin Raborar', 'Morning', 'Transmission'],
            ['MECH-003', 'Allan Tingson', 'Afternoon', 'Electrical'],
            ['MECH-004', 'Marlon Cabrera', 'Morning', 'Aircon'],
            ['MECH-005', 'Dennis Alforque', 'Afternoon', 'Suspension'],
            ['MECH-006', 'Rodel Penaso', 'Morning', 'Brakes'],
            ['MECH-007', 'Junel Omolon', 'Night', 'Lubrication'],
            ['MECH-008', 'Elmer Sayson', 'Afternoon', 'Bodyworks'],
        ];

        $mechanics = [];
        foreach ($rows as $i => [$id, $name, $shift, $specialization]) {
            $inserted = DB::table('mechanics')->insertGetId([
                'mechanic_id' => $id,
                'mechanic_name' => $name,
                'shift' => $shift,
                'specialization' => $specialization,
                'contact_number' => '09' . mt_rand(100000000, 999999999),
                'employment_status' => $i === 7 ? 'Inactive' : 'Active',
                'created_at' => Carbon::create(2026, 3, mt_rand(1, 25), 8, 0),
                'updated_at' => now(),
            ]);
            $mechanics[] = ['id' => $inserted, 'mechanic_id' => $id, 'mechanic_name' => $name, 'shift' => $shift, 'specialization' => $specialization];
        }

        return $mechanics;
    }

    protected function seedDrivers(): array
    {
        $rows = [
            ['DRV-001', 'Ramon Ybañez', 'Morning'],
            ['DRV-002', 'Jerry Magsipoc', 'Morning'],
            ['DRV-003', 'Freddie Basañez', 'Afternoon'],
            ['DRV-004', 'Tomas Ipong', 'Afternoon'],
            ['DRV-005', 'Noel Bonto', 'Night'],
            ['DRV-006', 'Rene Corpin', 'Morning'],
            ['DRV-007', 'Ariel Dela Cruz', 'Afternoon'],
            ['DRV-008', 'Fernando Egonia', 'Night'],
            ['DRV-009', 'Leonardo Galing', 'Morning'],
            ['DRV-010', 'Mario Hagedorn', 'Afternoon'],
            ['DRV-011', 'Crisanto Baguio', 'Night'],
            ['DRV-012', 'Willy Sumalpong', 'Morning'],
        ];

        $drivers = [];
        foreach ($rows as $i => [$id, $name, $shift]) {
            $inserted = DB::table('drivers')->insertGetId([
                'driver_id' => $id,
                'driver_name' => $name,
                'shift' => $shift,
                'contact_number' => '09' . mt_rand(100000000, 999999999),
                'license_number' => 'N' . mt_rand(1000000, 1999999),
                'license_expiration' => Carbon::create(2026, 11, mt_rand(1, 28))->toDateString(),
                'employment_status' => $i === 11 ? 'Inactive' : 'Active',
                'created_at' => Carbon::create(2026, 3, mt_rand(1, 25), 8, 0),
                'updated_at' => now(),
            ]);
            $drivers[] = ['id' => $inserted, 'driver_id' => $id, 'driver_name' => $name, 'shift' => $shift];
        }

        return $drivers;
    }

    /* ------------------------------------------------------------------ */
    /*  SHUTTLE ROUTES                                                     */
    /* ------------------------------------------------------------------ */

    protected function seedRoutes(): array
    {
        $rows = [
            ['RT-01', 'SM City Cebu - Mactan Airport', 'SM City Cebu', 'Mactan-Cebu International Airport', 12.5, 45, 'Active'],
            ['RT-02', 'Fuente Osmeña - IT Park', 'Fuente Osmeña Circle', 'Cebu IT Park, Lahug', 4.8, 25, 'Active'],
            ['RT-03', 'Ayala Center - IT Park', 'Ayala Center Cebu', 'Cebu IT Park, Lahug', 3.5, 20, 'Active'],
            ['RT-04', 'Parkmall - Mactan Newtown', 'Parkmall, Mandaue', 'Mactan Newtown, Lapu-Lapu', 9.3, 40, 'Active'],
            ['RT-05', 'Talisay - SM Seaside', 'Talisay City Hall', 'SM Seaside City Cebu, SRP', 11.7, 55, 'Active'],
            ['RT-06', 'Lahug - Mandaue Reclamation', 'Alsons Aboitiz, Lahug', 'Sugbu Commerce Center, Mandaue', 8.9, 35, 'Inactive'],
        ];

        $stops = [
            'RT-01' => [['S.M. City Cebu (North Wing)', 10.3108, 123.9053], ['Mabolo Public Market', 10.3211, 123.8975], ['Banilad Pardo Bridge', 10.3342, 123.8910], ['Ouano Avenue, Mandaue', 10.3392, 123.9411], ['Mactan Airport Terminal 2', 10.3092, 123.9797]],
            'RT-02' => [['Fuente Osmeña Circle', 10.3108, 123.8968], ['Capitol Site (N. Escario)', 10.3167, 123.9044], ['University of Cebu', 10.3202, 123.8980], ['IT Park - Skypark', 10.3262, 123.9057]],
            'RT-03' => [['Ayala Center Cebu', 10.3128, 123.9021], ['Cebu Business Park', 10.3151, 123.9052], ['J.Y. Square Mall', 10.3223, 123.9042], ['IT Park - Filinvest', 10.3265, 123.9072]],
            'RT-04' => [['Parkmall', 10.3342, 123.9368], ['Gaisano Mandaue', 10.3281, 123.9402], ['Mactan Bridge Northbound', 10.3012, 123.9363], ['Marina Mall', 10.3074, 123.9615], ['Mactan Newtown', 10.3058, 123.9755]],
            'RT-05' => [['Talisay City Hall', 10.2445, 123.8435], ['Talisay Public Market', 10.2451, 123.8491], ['South Road Properties (SRP)', 10.2854, 123.8831], ['SM Seaside City Cebu', 10.2919, 123.8857]],
            'RT-06' => [['Alsons Aboitiz, Lahug', 10.3239, 123.9167], ['Crossroads, Banilad', 10.3334, 123.9154], ['Mandaue City Public Market', 10.3233, 123.9421], ['Sugbu Commerce Center', 10.3185, 123.9447]],
        ];

        $routes = [];
        foreach ($rows as [$code, $name, $origin, $destination, $distance, $time, $status]) {
            $id = DB::table('shuttle_routes')->insertGetId([
                'route_code' => $code,
                'route_name' => $name,
                'origin' => $origin,
                'origin_address' => $origin,
                'origin_latitude' => $stops[$code][0][1],
                'origin_longitude' => $stops[$code][0][2],
                'origin_source' => 'manual',
                'destination' => $destination,
                'destination_address' => $destination,
                'destination_latitude' => $stops[$code][array_key_last($stops[$code])][1],
                'destination_longitude' => $stops[$code][array_key_last($stops[$code])][2],
                'destination_source' => 'manual',
                'distance_km' => $distance,
                'distance_source' => 'estimated',
                'estimated_time_minutes' => $time,
                'status' => $status,
                'created_at' => Carbon::create(2026, 4, mt_rand(1, 20), 10, 0),
                'updated_at' => now(),
            ]);

            foreach ($stops[$code] as $index => [$stopName, $lat, $lng]) {
                DB::table('route_stops')->insert([
                    'shuttle_route_id' => $id,
                    'stop_name' => $stopName,
                    'stop_order' => $index + 1,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'location_source' => 'manual',
                    'address' => $stopName,
                    'created_at' => Carbon::create(2026, 4, mt_rand(1, 20), 10, 0),
                    'updated_at' => now(),
                ]);
            }

            $routes[] = ['id' => $id, 'route_code' => $code, 'route_name' => $name, 'distance_km' => $distance, 'time_minutes' => $time, 'status' => $status];
        }

        return $routes;
    }

    /* ------------------------------------------------------------------ */
    /*  PMS SCHEDULES + JOB ORDERS                                         */
    /* ------------------------------------------------------------------ */

    protected function seedPmsSchedules(array $buses): array
    {
        $types = ['Change Oil', 'Engine Tune-Up', 'Brake Overhaul', 'Aircon Servicing', 'Suspension Check', 'Electrical Check', 'Transmission Fluid', 'Tire Rotation'];
        $intervalMap = [
            'Change Oil' => 5000, 'Engine Tune-Up' => 15000, 'Brake Overhaul' => 20000,
            'Aircon Servicing' => 10000, 'Suspension Check' => 20000, 'Electrical Check' => 15000,
            'Transmission Fluid' => 60000, 'Tire Rotation' => 10000,
        ];
        $durationMap = [
            'Change Oil' => [0.5, 'Days'], 'Engine Tune-Up' => [1, 'Days'], 'Brake Overhaul' => [2, 'Days'],
            'Aircon Servicing' => [1, 'Days'], 'Suspension Check' => [1, 'Days'], 'Electrical Check' => [8, 'Hours'],
            'Transmission Fluid' => [0.5, 'Days'], 'Tire Rotation' => [4, 'Hours'],
        ];

        $pmsSchedules = [];
        $role = 0;
        foreach ($buses as $bus) {
            if ($bus['status'] === 'Inactive') {
                continue;
            }

            $pick = array_rand(array_keys($types), $bus['status'] === 'Under Maintenance' ? 3 : 4);
            foreach ($pick as $k) {
                $type = $types[$k];
                $interval = $intervalMap[$type];
                $nextKm = $bus['gps_km'] > $bus['last_pms_km'] + $interval
                    ? $bus['last_pms_km'] + $interval
                    : $bus['gps_km'] + mt_rand(0, 4000);

                $kmRemaining = $nextKm - $bus['gps_km'];
                $daysUntilDue = (int) round(($kmRemaining / max(600, $bus['gps_km'] * 0.0001)));

                $id = DB::table('pms_schedules')->insertGetId([
                    'bus_no' => $bus['bus_no'],
                    'last_pms_km' => $bus['last_pms_km'],
                    'pms_interval_km' => $interval,
                    'next_pms_km' => $nextKm,
                    'maintenance_type' => $type,
                    'recommended_date' => Carbon::now()->addDays($daysUntilDue > 0 ? $daysUntilDue : -abs($daysUntilDue))->subDays($interval / 500)->toDateString(),
                    'created_at' => Carbon::create(2026, 5, mt_rand(2, 26), 9, 0),
                    'updated_at' => now(),
                ]);

                $pmsSchedules[] = ['id' => $id, 'bus_no' => $bus['bus_no'], 'maintenance_type' => $type, 'interval_km' => $interval, 'duration_value' => $durationMap[$type][0], 'duration_unit' => $durationMap[$type][1], 'recommended_date' => Carbon::now()->addDays($daysUntilDue)->subDays($interval / 500)->toDateString()];
                $role++;
            }
        }

        return $pmsSchedules;
    }

    protected function seedJobOrders(array $buses, array $mechanics, array $pmsSchedules): array
    {
        $activeMechanics = array_values(array_filter($mechanics, fn ($m) => $m['id'] !== 8 || $m['mechanic_id'] !== 'MECH-008'));
        $issuesByType = [
            'Change Oil' => ['Engine oil overdue for replacement; degraded lubrication and rising oil temperature.', 'Dark, metal-contaminated engine oil detected during routine check.', 'Low engine oil level with pressure warning persisting on startup.'],
            'Engine Tune-Up' => ['Rough idling and intermittent misfire; check ignition timing and injectors.', 'Reduced acceleration and high fuel consumption; perform full engine tune-up.', 'Check engine light on with hesitation during uphill climb.'],
            'Brake Overhaul' => ['Brake pedal sinking and metallic grinding noise from front axle.', 'Rear brake shoes worn to limits; brake fluid contaminated and dark.', 'Braking distance extended; vibration felt on brake pedal application.'],
            'Aircon Servicing' => ['Aircon cooling weak; refrigerant low and compressor cycling frequently.', 'Condenser coil dirty and evaporator blocked; poor cabin airflow.', 'Aircon unit emitting musty smell; system requires deep servicing and re-gas.'],
            'Suspension Check' => ['Front suspension clunking over humps; shock absorbers leaking oil.', 'Uneven tire wear; ball joint play detected on left lower control arm.', 'Vehicle bounces excessively; leaf spring center bolt sheared.'],
            'Electrical Check' => ['Headlight circuit intermittent; wiring harness chafed near bumper.', 'Starter motor clicking intermittently; battery voltage drops under load.', 'Battery discharging overnight; parasitic draw suspected in lighting circuit.'],
            'Transmission Fluid' => ['Automatic transmission shifting harsh; fluid burnt and low.', 'Gear slippage during acceleration; transmission fluid leaking from pan gasket.', 'Transmission oil overdue; torque converter shutter at low speeds.'],
            'Tire Rotation' => ['Front tires wearing faster than rear; rotate and balance all four wheels.', 'Rear tires cupped; recommend rotation and alignment check.', 'Tire pressure mismatch across axles; rotation and pressure calibration needed.'],
        ];

        $jobOrders = [];
        $dueDate = null;
        $counter = 1;
        $current = Carbon::create(2026, 4, 2, 8, 30);

        while ($counter <= 40) {
            $bus = $buses[($counter * 7) % count($buses)];
            $mechanic = $activeMechanics[($counter * 5) % count($activeMechanics)];
            $pms = null;

            foreach ($pmsSchedules as $candidate) {
                if ($candidate['bus_no'] === $bus['bus_no']) {
                    $pms = $candidate;
                    break;
                }
            }

            $type = $pms['maintenance_type'] ?? array_keys($issuesByType)[($counter * 3) % count($issuesByType)];
            $issue = $issuesByType[$type][($counter * 2) % 3];
            $durationValue = $pms['duration_value'] ?? 1;
            $durationUnit = $pms['duration_unit'] ?? 'Days';

            $createdAt = $current->copy()->addDays(mt_rand(1, 4))->addMinutes(mt_rand(0, 480));
            if ($createdAt->gt(Carbon::now()->subDay())) {
                $createdAt = Carbon::now()->copy()->subDays(mt_rand(0, 9))->setTime(mt_rand(7, 10), mt_rand(0, 59));
            }

            $statusRoll = mt_rand(1, 100);
            $status = $statusRoll <= 55 ? 'Completed' : ($statusRoll <= 80 ? 'On Going' : 'On Hold');

            $completionDate = null;
            if ($status === 'Completed') {
                $completionDate = $createdAt->copy()->addDays((float) $durationValue)->addHours(mt_rand(0, 5));
                if ($completionDate->gt(Carbon::now())) {
                    $completionDate = Carbon::now()->subDays(mt_rand(1, 3));
                }
            } elseif ($status === 'On Going' && $counter % 5 === 0) {
                $createdAt = Carbon::now()->subDays(mt_rand(4, 14));
            }

            $partStatus = match (true) {
                $status !== 'Completed' && $counter % 6 === 0 => 'Rejected',
                $status !== 'Completed' && $counter % 5 === 0 => 'For Purchase',
                $status !== 'Completed' && $counter % 4 === 0 => 'Requested',
                $status !== 'Completed' => 'Not Requested',
                $counter % 7 === 0 => 'Delivered',
                default => 'Issued',
            };

            $id = DB::table('job_orders')->insertGetId([
                'job_order_no' => 'JO-2026-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'bus_no' => $bus['bus_no'],
                'pms_schedule_id' => $pms['id'] ?? null,
                'problem_issue' => $issue,
                'maintenance_type' => $type,
                'assigned_mechanic' => $mechanic['mechanic_name'],
                'part_needed' => $this->partsForType($type),
                'estimated_duration_value' => $durationValue,
                'estimated_duration_unit' => $durationUnit,
                'start_date' => $createdAt,
                'completion_date' => $completionDate,
                'status' => $status,
                'part_status' => $partStatus,
                'created_at' => $createdAt,
                'updated_at' => $completionDate ?? $createdAt->addDay(),
            ]);

            $jobOrders[] = [
                'id' => $id,
                'job_order_no' => 'JO-2026-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'bus_no' => $bus['bus_no'],
                'maintenance_type' => $type,
                'status' => $status,
                'part_status' => $partStatus,
                'assigned_mechanic' => $mechanic['mechanic_name'],
                'created_at' => $createdAt,
            ];

            $counter++;
            $current = $createdAt;
        }

        return $jobOrders;
    }

    protected function partsForType(string $type): string
    {
        $parts = [
            'Change Oil' => 'Engine Oil 15W-40 - Qty: 6 liter, Oil Filter - Qty: 1 pc',
            'Engine Tune-Up' => 'Spark Plug - Qty: 6 pcs, Air Filter - Qty: 1 pc, Fuel Filter - Qty: 1 pc',
            'Brake Overhaul' => 'Brake Pad - Qty: 4 pcs, Brake Fluid - Qty: 1 bottle, Brake Shoe - Qty: 4 pcs',
            'Aircon Servicing' => 'AC Refrigerant R134a - Qty: 2 cans, Dryer/Receiver - Qty: 1 pc, AC Belt - Qty: 1 pc',
            'Suspension Check' => 'Shock Absorber - Qty: 2 pcs, Leaf Spring - Qty: 1 pc, Grease - Qty: 1 kg',
            'Electrical Check' => 'Starter Motor - Qty: 1 pc, Connector Terminals - Qty: 10 pcs, Electrical Tape - Qty: 2 rolls',
            'Transmission Fluid' => 'Transmission Fluid - Qty: 4 liter, Transmission Pan Gasket - Qty: 1 pc',
            'Tire Rotation' => 'Tire - Qty: 2 pcs, Valve Stem - Qty: 4 pcs, Wheel Weights - Qty: 1 set',
        ];

        return $parts[$type] ?? 'Brake Pad - Qty: 2 pcs';
    }

    /* ------------------------------------------------------------------ */
    /*  ATTENDANCE                                                         */
    /* ------------------------------------------------------------------ */

    protected function seedDriverAttendance(array $drivers, array $buses): void
    {
        $activeBuses = array_values(array_filter($buses, fn ($b) => $b['status'] === 'Active'));
        $rows = [];
        $day = Carbon::create(2026, 5, 25);

        while ($day->lte(Carbon::now()->copy()->endOfDay())) {
            if ($day->isSaturday() || $day->isSunday()) {
                $day->addDay();
                continue;
            }

            $attending = [];
            foreach ($drivers as $i => $driver) {
                $statusRoll = mt_rand(1, 100);
                $status = $statusRoll <= 82 ? 'Present' : ($statusRoll <= 90 ? 'Late' : ($statusRoll <= 97 ? 'On Duty' : 'Absent'));

                if ($status === 'Absent') {
                    continue;
                }

                $attending[] = $driver;
                $timeIn = $status === 'Late'
                    ? '07:4' . mt_rand(0, 9) . ':00'
                    : sprintf('%02d:%02d:00', mt_rand(4, 6), mt_rand(0, 59));
                $busAssignment = $activeBuses[mt_rand(0, count($activeBuses) - 1)]['bus_no'] ?? 'GCT-101';

                $rows[] = [
                    'driver_id' => $driver['driver_id'],
                    'driver_name' => $driver['driver_name'],
                    'shift' => $driver['shift'],
                    'bus_assignment' => $busAssignment,
                    'attendance_date' => $day->toDateString(),
                    'time_in' => $timeIn,
                    'time_out' => '17:0' . mt_rand(0, 5) . ':00',
                    'status' => $status,
                    'created_at' => $day->copy()->setTime(6, mt_rand(0, 59)),
                    'updated_at' => $day->copy()->setTime(18, 0),
                ];
            }

            $day->addDay();
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('driver_attendances')->insert($chunk);
        }
    }

    protected function seedMechanicAttendance(array $mechanics): void
    {
        $rows = [];
        $day = Carbon::create(2026, 5, 25);

        while ($day->lte(Carbon::now()->copy()->endOfDay())) {
            if ($day->isSaturday() || $day->isSunday()) {
                $day->addDay();
                continue;
            }

            foreach ($mechanics as $i => $mechanic) {
                $statusRoll = mt_rand(1, 100);
                $status = $statusRoll <= 86 ? 'Present' : ($statusRoll <= 93 ? 'Late' : ($statusRoll <= 98 ? 'On Duty' : 'Absent'));

                if ($status === 'Absent') {
                    continue;
                }

                $rows[] = [
                    'mechanic_id' => $mechanic['mechanic_id'],
                    'mechanic_name' => $mechanic['mechanic_name'],
                    'shift' => $mechanic['shift'],
                    'assigned_job' => $status === 'Present' ? 'JO-2026-' . str_pad((string) (($i + 1) % 40), 4, '0', STR_PAD_LEFT) : null,
                    'attendance_date' => $day->toDateString(),
                    'time_in' => sprintf('%02d:%02d:00', mt_rand(6, 8), mt_rand(0, 59)),
                    'time_out' => '17:0' . mt_rand(0, 5) . ':00',
                    'status' => $status,
                    'created_at' => $day->copy()->setTime(7, mt_rand(0, 59)),
                    'updated_at' => $day->copy()->setTime(18, 0),
                ];
            }

            $day->addDay();
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('mechanic_attendances')->insert($chunk);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  TRIPS                                                              */
    /* ------------------------------------------------------------------ */

    protected function seedTrips(array $drivers, array $buses, array $routes, array $users): array
    {
        $activeBuses = array_values(array_filter($buses, fn ($b) => $b['status'] === 'Active'));
        $activeRoutes = array_values(array_filter($routes, fn ($r) => $r['status'] === 'Active'));
        $opsHead = array_values(array_filter($users, fn ($u) => $u['role'] === 'head' && $u['department'] === 'Operation'))[0] ?? $users[0];

        $tripSchedules = [];
        $tripAssignments = [];
        $scheduleIds = [];
        $day = Carbon::create(2026, 5, 20);
        $tripCounter = 1;

        while ($day->lte(Carbon::now()->copy()->addDays(7))) {
            $isWeekend = $day->isSaturday() || $day->isSunday();
            $tripsCount = $isWeekend ? mt_rand(2, 4) : mt_rand(6, 9);
            $shifts = ['Morning', 'Morning', 'Afternoon', 'Afternoon', 'Night', 'Morning', 'Afternoon', 'Night', 'Morning'];

            for ($t = 0; $t < $tripsCount; $t++) {
                $shift = $shifts[$t % count($shifts)];
                $route = $activeRoutes[mt_rand(0, count($activeRoutes) - 1)];

                [$depTime, $arrBase] = $this->tripTimes($shift, $route['time_minutes'], $t);

                $isPast = $day->lt(Carbon::now()->startOfDay());
                $roll = mt_rand(1, 100);
                $status = $isPast
                    ? ($roll <= 86 ? 'Completed' : ($roll <= 93 ? 'Delayed' : 'Cancelled'))
                    : ($roll <= 8 ? 'Ready' : 'Scheduled');
                $assigned = ! ($status === 'Cancelled') && ($isPast ? $roll <= 94 : $roll <= 70);

                $departure = $depTime;
                $arrival = $arrBase;
                $actualDep = null;
                $actualArr = null;
                $actualDuration = null;

                if ($status === 'Delayed' && $isPast) {
                    $actualDep = Carbon::parse($departure)->addMinutes(mt_rand(8, 40))->format('H:i:s');
                    $actualArr = Carbon::parse($arrival)->addMinutes(mt_rand(12, 60))->format('H:i:s');
                } elseif ($status === 'Completed') {
                    $actualDep = $departure;
                    $delay = mt_rand(0, 12);
                    $actualArr = Carbon::parse($arrival)->addMinutes($delay)->format('H:i:s');
                }

                $code = 'TRIP-' . $day->format('Ymd') . '-' . str_pad((string) $tripCounter, 2, '0', STR_PAD_LEFT);
                $id = DB::table('trip_schedules')->insertGetId([
                    'trip_code' => $code,
                    'trip_date' => $day->toDateString(),
                    'shuttle_route_id' => $route['id'],
                    'departure_time' => $departure,
                    'estimated_arrival_time' => $arrival,
                    'shift' => $shift,
                    'assignment_status' => $assigned ? 'Assigned' : (in_array($status, ['Completed', 'Delayed'], true) ? 'Assigned' : 'Unassigned'),
                    'status' => $status,
                    'actual_departure_time' => $actualDep,
                    'actual_arrival_time' => $actualArr,
                    'notes' => $status === 'Delayed' ? 'Start line congestion and traffic delay along route.' : ($isPast ? 'Regular scheduled shuttle run.' : null),
                    'created_by' => $opsHead['id'],
                    'created_at' => $day->copy()->subDays(2)->setTime(mt_rand(9, 16), mt_rand(0, 59)),
                    'updated_at' => $isPast ? $day->copy()->setTime(22, 0) : now(),
                ]);

                $scheduleIds[] = ['id' => $id, 'trip_code' => $code, 'day' => $day->toDateString()];
                $tripSchedules[] = ['id' => $id, 'trip_code' => $code, 'trip_date' => $day->toDateString(), 'route' => $route, 'departure_time' => $departure, 'arrival_time' => $actualArr ?? $arrival, 'status' => $status, 'shift' => $shift, 'bus_no' => null, 'driver_id' => null];

                if ($assigned) {
                    $shiftDrivers = array_values(array_filter($drivers, fn ($d) => $d['shift'] === $shift || $shift === 'Morning'));
                    $driver = $shiftDrivers[mt_rand(0, count($shiftDrivers) - 1)];
                    $bus = $activeBuses[mt_rand(0, count($activeBuses) - 1)];

                    $attendanceId = DB::table('driver_attendances')->where('driver_id', $driver['driver_id'])->whereDate('attendance_date', $day->toDateString())->value('id');
                    if (! $attendanceId) {
                        $attendanceId = DB::table('driver_attendances')->insertGetId([
                            'driver_id' => $driver['driver_id'],
                            'driver_name' => $driver['driver_name'],
                            'shift' => $driver['shift'],
                            'bus_assignment' => $bus['bus_no'],
                            'attendance_date' => $day->toDateString(),
                            'time_in' => '04:3' . mt_rand(0, 9) . ':00',
                            'time_out' => '20:0' . mt_rand(0, 5) . ':00',
                            'status' => 'Present',
                            'created_at' => $day->copy()->setTime(5, mt_rand(0, 59)),
                            'updated_at' => $day->copy()->setTime(21, 0),
                        ]);
                    }

                    $actualDuration = $isPast
                        ? max(1, abs((int) Carbon::parse($arrival)->diffInMinutes(Carbon::parse($departure))) + mt_rand(0, mt_rand(0, 25)))
                        : null;

                    DB::table('trip_assignments')->insert([
                        'trip_schedule_id' => $id,
                        'driver_attendance_id' => $attendanceId,
                        'driver_id' => $driver['driver_id'],
                        'driver_name' => $driver['driver_name'],
                        'bus_id' => $bus['id'],
                        'assigned_by' => $opsHead['id'],
                        'actual_duration_minutes' => $actualDuration,
                        'created_at' => $day->copy()->subDays(1)->setTime(17, mt_rand(0, 59)),
                        'updated_at' => $day->copy()->setTime(23, mt_rand(0, 59)),
                    ]);

                    $tripSchedules[count($tripSchedules) - 1]['bus_no'] = $bus['bus_no'];
                    $tripSchedules[count($tripSchedules) - 1]['driver_id'] = $driver['driver_id'];
                    $tripAssignments[] = [
                        'trip_schedule_id' => $id,
                        'trip_code' => $code,
                        'trip_date' => $day->toDateString(),
                        'bus_no' => $bus['bus_no'],
                        'bus_id' => $bus['id'],
                        'driver_id' => $driver['driver_id'],
                        'driver_name' => $driver['driver_name'],
                        'status' => $status,
                        'route' => $route,
                        'departure' => $departure,
                        'arrival' => $actualArr ?? $arrival,
                        'actual_duration_minutes' => $actualDuration,
                    ];
                }

                $tripCounter++;
            }

            $day->addDay();
        }

        return [$tripSchedules, $tripAssignments];
    }

    protected function tripTimes(string $shift, int $routeMinutes, int $index): array
    {
        $base = [
            'Morning' => ['05:' . str_pad((string) (30 + 10 * ($index % 2)), 2, '0', STR_PAD_LEFT), '12:00'],
            'Afternoon' => ['13:' . str_pad((string) (10 + 10 * ($index % 2)), 2, '0', STR_PAD_LEFT), '19:30'],
            'Night' => ['19:' . str_pad((string) (10 + 10 * ($index % 2)), 2, '0', STR_PAD_LEFT), '23:30'],
        ][$shift];

        $departure = $base[0];
        $arrival = Carbon::parse($departure)->addMinutes($routeMinutes + mt_rand(-2, 4))->format('H:i:s');

        return [$departure . ':00', $arrival];
    }

    protected function tripData(): array
    {
        return $this->trips;
    }

    protected array $trips = [];

    /* ------------------------------------------------------------------ */
    /*  GPS TRIP RECORDS + FUEL REPORTS                                    */
    /* ------------------------------------------------------------------ */

    protected function seedGpsAndFuel(array $unused): array
    {
        $batchIds = DB::table('batch_uploads')->where('module', 'Operation')->where('data_type', 'GPS Trip Records')->pluck('id')->all();
        $gpsRecords = [];
        $fuelReports = [];
        $recordsKeep = [];

        $pastAssignments = DB::table('trip_assignments as ta')
            ->join('trip_schedules as ts', 'ts.id', '=', 'ta.trip_schedule_id')
            ->join('buses as b', 'b.id', '=', 'ta.bus_id')
            ->join('shuttle_routes as sr', 'sr.id', '=', 'ts.shuttle_route_id')
            ->whereIn('ts.status', ['Completed', 'Delayed'])
            ->select('ta.id as assignment_id', 'ts.trip_date', 'ts.trip_code', 'ts.departure_time', 'ts.actual_arrival_time', 'ts.estimated_arrival_time', 'ts.status as trip_status', 'ta.driver_name', 'ta.driver_id', 'ta.bus_id', 'ts.actual_departure_time', 'b.bus_no', 'sr.route_name', 'sr.distance_km', 'sr.estimated_time_minutes')
            ->get();

        $counter = 0;
        foreach ($pastAssignments as $pa) {
            if (mt_rand(1, 100) > 86) {
                continue;
            }

            $batchId = $batchIds[$counter % max(1, count($batchIds))];
            $beginning = Carbon::parse($pa->trip_date . ' ' . ($pa->actual_departure_time ?? $pa->departure_time));
            $ending = Carbon::parse($pa->trip_date . ' ' . ($pa->actual_arrival_time ?? $pa->estimated_arrival_time));

            $totalMinutes = max(1, (int) $beginning->diffInMinutes($ending));
            $idling = mt_rand(2, (int) max(3, $totalMinutes * 0.22));
            $inMotion = max(1, $totalMinutes - $idling);
            $distance = round(((float) $pa->distance_km) * (mt_rand(90, 108) / 100), 2);
            $severity = $pa->trip_status === 'Delayed' || $idling > 15 ? 'High' : (mt_rand(1, 100) <= 12 ? 'Medium' : 'Normal');

            $id = DB::table('gps_trip_records')->insertGetId([
                'batch_upload_id' => $batchId,
                'trip_assignment_id' => $pa->assignment_id,
                'record_no' => 'GPS-' . $pa->trip_date . '-' . str_pad((string) ($counter + 1), 3, '0', STR_PAD_LEFT),
                'bus_no' => $pa->bus_no,
                'grouping' => $pa->route_name,
                'trip_type' => 'Shuttle Service',
                'beginning_at' => $beginning,
                'initial_location' => $pa->route_name === '' || $pa->route_name === null ? null : strtok($pa->route_name, '-') . ' Terminal',
                'ending_at' => $ending,
                'final_location' => $pa->route_name === '' || $pa->route_name === null ? null : strtok($pa->route_name, '-') . ' End Point',
                'duration_minutes' => $totalMinutes,
                'total_minutes' => $totalMinutes,
                'in_motion_minutes' => $inMotion,
                'idling_minutes' => $idling,
                'mileage_km' => $distance,
                'engine_hours' => round($totalMinutes / 60, 2),
                'location' => $pa->route_name,
                'coordinates' => '',
                'description' => 'Trip ' . $pa->trip_code . ' - completed shuttle run.',
                'source_format' => 'gpx',
                'severity' => $severity,
                'raw_data' => json_encode(['trip_code' => $pa->trip_code, 'bus_no' => $pa->bus_no, 'route' => $pa->route_name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $ending,
                'updated_at' => $ending->copy()->addMinutes(5),
            ]);

            $gpsRecords[] = ['id' => $id, 'bus_no' => $pa->bus_no, 'date' => $pa->trip_date, 'distance' => $distance, 'fuel_ready' => false];
            $recordsKeep[] = $id;

            if (mt_rand(1, 100) <= 42) {
                $kmpl = mt_rand(28, 45) / 10;
                $fuel = round($distance / $kmpl, 2);

                $rfId = DB::table('fuel_reports')->insertGetId([
                    'report_date' => $pa->trip_date,
                    'bus_no' => $pa->bus_no,
                    'driver_name' => $pa->driver_name,
                    'gps_trip_record_id' => $id,
                    'distance_km' => $distance,
                    'distance_source' => 'GPS',
                    'fuel_liters' => $fuel,
                    'km_per_liter' => $kmpl,
                    'status' => 'Completed',
                    'remarks' => mt_rand(1, 100) <= 12 ? 'Below-average efficiency flagged for review.' : null,
                    'manual_distance_reason' => null,
                    'created_at' => $ending,
                    'updated_at' => $ending->copy()->addMinutes(10),
                ]);

                $lastIndex = count($gpsRecords) - 1;
                $gpsRecords[$lastIndex]['fuel_ready'] = true;
                $fuelReports[] = ['id' => $rfId, 'bus_no' => $pa->bus_no, 'date' => $pa->trip_date, 'kmpl' => $kmpl];
            }

            $counter++;
        }

        return [$fuelReports, $gpsRecords];
    }

    /* ------------------------------------------------------------------ */
    /*  INVENTORY + STOCK MOVEMENTS                                        */
    /* ------------------------------------------------------------------ */

    protected function seedInventory(): array
    {
        $suppliers = ['AutoPlus Trading Cebu', 'PhilHino Sales Corp.', 'Sumisho Global Mobility', 'Tri-City Auto Supply', 'Northwest Auto Parts', 'Master Auto Depot Cebu'];

        $catalog = [
            ['OIL-ENG-15W40', 'Engine Oil 15W-40', 'Lubricants & Fluids', 480, 'liter', 300, 'PhilHino Sales Corp.'],
            ['OIL-DEO-15W40', 'Diesel Engine Oil 15W-40', 'Lubricants & Fluids', 320, 'liter', 200, 'PhilHino Sales Corp.'],
            ['OIL-DEX-III', 'ATF Dexron III', 'Lubricants & Fluids', 96, 'liter', 60, 'AutoPlus Trading Cebu'],
            ['BRK-FLUID-1L', 'Brake Fluid DOT-4 1L', 'Lubricants & Fluids', 40, 'bottle', 30, 'AutoPlus Trading Cebu'],
            ['PWR-FLUID-1L', 'Power Steering Fluid 1L', 'Lubricants & Fluids', 26, 'bottle', 24, 'AutoPlus Trading Cebu'],
            ['GRS-MULTI-1KG', 'Multi-Purpose Grease', 'Lubricants & Fluids', 18, 'kg', 20, 'Sumisho Global Mobility'],
            ['CLG-ANTIFREEZE', 'Coolant / Antifreeze 1L', 'Lubricants & Fluids', 55, 'bottle', 40, 'Tri-City Auto Supply'],
            ['TRS-FLUID-4L', 'Transmission Fluid 4L', 'Lubricants & Fluids', 12, 'can', 15, 'AutoPlus Trading Cebu'],
            ['FLT-OIL', 'Oil Filter', 'Filters', 210, 'pc', 150, 'PhilHino Sales Corp.'],
            ['FLT-AIR', 'Air Filter', 'Filters', 130, 'pc', 100, 'PhilHino Sales Corp.'],
            ['FLT-FUEL', 'Fuel Filter', 'Filters', 84, 'pc', 70, 'Northwest Auto Parts'],
            ['FLT-CABIN', 'Cabin Air Filter', 'Filters', 55, 'pc', 40, 'Northwest Auto Parts'],
            ['FLT-HYD', 'Hydraulic Filter', 'Filters', 18, 'pc', 20, 'Sumisho Global Mobility'],
            ['FLT-SEP', 'Fuel-Water Separator', 'Filters', 15, 'pc', 15, 'Sumisho Global Mobility'],
            ['BRK-PAD-FR', 'Brake Pad (Front)', 'Brake System', 120, 'set', 90, 'Tri-City Auto Supply'],
            ['BRK-PAD-RR', 'Brake Pad (Rear)', 'Brake System', 88, 'set', 70, 'Tri-City Auto Supply'],
            ['BRK-SHOE-RR', 'Brake Shoe (Rear Drum)', 'Brake System', 42, 'set', 40, 'Tri-City Auto Supply'],
            ['BRK-MC', 'Brake Master Cylinder', 'Brake System', 6, 'pc', 8, 'Sumisho Global Mobility'],
            ['BRK-CALIPER', 'Brake Caliper Assembly', 'Brake System', 4, 'pc', 5, 'Sumisho Global Mobility'],
            ['BRK-AIR-VLV', 'Air Brake Relay Valve', 'Brake System', 9, 'pc', 10, 'Sumisho Global Mobility'],
            ['BATT-12V-180', 'Battery 12V 180Ah', 'Electrical', 24, 'pc', 15, 'Sumisho Global Mobility'],
            ['ALT-GEN', 'Alternator 24V', 'Electrical', 7, 'pc', 8, 'Northwest Auto Parts'],
            ['STARTER-24V', 'Starter Motor 24V', 'Electrical', 5, 'pc', 8, 'Northwest Auto Parts'],
            ['LMP-HL-CLR', 'Headlight Bulb (Clear)', 'Electrical', 96, 'pc', 60, 'Master Auto Depot Cebu'],
            ['LMP-TL-RR', 'Tail Light Bulb', 'Electrical', 140, 'pc', 100, 'Master Auto Depot Cebu'],
            ['WIR-HARN', 'Wiring Harness Connector Kit', 'Electrical', 30, 'set', 25, 'Master Auto Depot Cebu'],
            ['BAT-TERMINAL', 'Battery Terminal Set', 'Electrical', 64, 'set', 40, 'AutoPlus Trading Cebu'],
            ['TIR-11R22-5', 'Tire 11R22.5', 'Tires & Wheels', 28, 'pc', 30, 'Sumisho Global Mobility'],
            ['TIR-295-80', 'Tire 295/80R22.5', 'Tires & Wheels', 36, 'pc', 30, 'Sumisho Global Mobility'],
            ['RIM-STEEL', 'Steel Rim 22.5"', 'Tires & Wheels', 14, 'pc', 15, 'Tri-City Auto Supply'],
            ['VLV-STEM', 'Valve Stem', 'Tires & Wheels', 200, 'pc', 150, 'Master Auto Depot Cebu'],
            ['SHOCK-FR', 'Shock Absorber (Front)', 'Suspension & Chassis', 18, 'pc', 15, 'Sumisho Global Mobility'],
            ['SHOCK-RR', 'Shock Absorber (Rear)', 'Suspension & Chassis', 12, 'pc', 15, 'Sumisho Global Mobility'],
            ['LEAF-SPRING', 'Leaf Spring Assembly', 'Suspension & Chassis', 8, 'pc', 10, 'Tri-City Auto Supply'],
            ['BALL-JOINT', 'Ball Joint', 'Suspension & Chassis', 26, 'pc', 20, 'AutoPlus Trading Cebu'],
            ['TIE-ROD-END', 'Tie Rod End', 'Suspension & Chassis', 24, 'pc', 20, 'AutoPlus Trading Cebu'],
            ['PNT-YELLOW', 'Yellow Paint (Spray)', 'Body & Trim', 40, 'can', 25, 'Master Auto Depot Cebu'],
            ['PNT-WHITE', 'White Paint (Spray)', 'Body & Trim', 36, 'can', 25, 'Master Auto Depot Cebu'],
            ['MIRR-REAR', 'Rear View Mirror', 'Body & Trim', 22, 'pc', 15, 'Tri-City Auto Supply'],
            ['SEAT-BELT', 'Seat Belt Assembly', 'Body & Trim', 60, 'pc', 45, 'Northwest Auto Parts'],
            ['AC-REFRIG', 'AC Refrigerant R134a', 'Aircon & Cooling', 30, 'can', 20, 'Sumisho Global Mobility'],
            ['AC-DRYER', 'AC Receiver Dryer', 'Aircon & Cooling', 6, 'pc', 8, 'Sumisho Global Mobility'],
            ['AC-COMP', 'AC Compressor', 'Aircon & Cooling', 3, 'pc', 4, 'Sumisho Global Mobility'],
            ['AC-BELT', 'AC Drive Belt', 'Aircon & Cooling', 48, 'pc', 30, 'Northwest Auto Parts'],
            ['SPK-PLUG', 'Spark Plug', 'Engine Parts', 100, 'pc', 80, 'Northwest Auto Parts'],
            ['GKL-SET', 'Engine Gasket Kit', 'Engine Parts', 11, 'set', 12, 'Tri-City Auto Supply'],
            ['PST-BELT', 'Fan / Alternator Belt', 'Engine Parts', 42, 'pc', 30, 'PhilHino Sales Corp.'],
            ['CLT-PLATE', 'Clutch Plate', 'Engine Parts', 5, 'pc', 6, 'PhilHino Sales Corp.'],
            ['CLR-PISTON', 'Piston Ring Set', 'Engine Parts', 6, 'set', 8, 'AutoPlus Trading Cebu'],
            ['TAP-ELEC', 'Electrical Tape', 'Consumables', 240, 'roll', 150, 'Master Auto Depot Cebu'],
            ['TIE-WIRE', 'Tie Wire / Zip Ties', 'Consumables', 160, 'pack', 100, 'Master Auto Depot Cebu'],
            ['RAG-COTTON', 'Cotton Rags', 'Consumables', 120, 'kg', 80, 'Master Auto Depot Cebu'],
            ['NIT-GLOVE', 'Nitrile Gloves (Box)', 'Consumables', 70, 'box', 50, 'Master Auto Depot Cebu'],
        ];

        $inventory = [];
        foreach ($catalog as $i => [$code, $name, $category, $onHand, $unit, $reorder, $supplier]) {
            $roll = mt_rand(1, 100);
            $maxStock = max($reorder + 1, intval($onHand * 1.6));
            $qty = $roll <= 8 ? 0 : ($roll <= 22 ? mt_rand(1, $reorder) : mt_rand($reorder + 1, $maxStock));

            $status = $qty <= 0 ? 'Out of Stock' : ($qty <= $reorder ? 'Low Stock' : 'In Stock');

            $id = DB::table('inventory_items')->insertGetId([
                'item_code' => $code,
                'parts_name' => $name,
                'item_name' => $name,
                'category' => $category,
                'on_hand' => $qty,
                'quantity_available' => $qty,
                'unit' => $unit,
                'unit_of_measurement' => $unit,
                'reorder_level' => $reorder,
                'status' => $status,
                'supplier' => $supplier,
                'location' => 'Warehouse Bay ' . str_pad((string) (($i % 4) + 1), 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) (($i % 10) + 1), 2, '0', STR_PAD_LEFT),
                'storage_location' => 'Bay ' . (($i % 4) + 1),
                'created_at' => Carbon::create(2026, 3, mt_rand(1, 28), mt_rand(8, 16), mt_rand(0, 59)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 30)),
            ]);

            $inventory[] = ['id' => $id, 'item_code' => $code, 'item_name' => $name, 'unit' => $unit, 'status' => $status, 'on_hand' => $qty, 'reorder_level' => $reorder];
        }

        return $inventory;
    }

    protected function seedStockMovements(array $inventory, array $jobOrders, array $purchaseRequests, array $purchaseOrders): void
    {
        $rows = [];
        $month = Carbon::create(2026, 4, 1);

        while ($month->lte(Carbon::now()->copy()->endOfMonth())) {
            foreach ($inventory as $item) {
                $eventRoll = mt_rand(1, 100);
                if ($eventRoll > 30) {
                    continue;
                }

                $change = mt_rand(2, 30);
                $previous = $item['on_hand'];
                $new = max(0, $previous + (mt_rand(0, 1) ? $change : -$change));

                $type = $new >= $previous ? 'Stock In' : 'Stock Out';
                $reference = $new >= $previous
                    ? 'PO-2026-' . str_pad((string) mt_rand(1, 14), 4, '0', STR_PAD_LEFT)
                    : 'PR-2026-' . str_pad((string) mt_rand(1, 32), 4, '0', STR_PAD_LEFT);

                $rows[] = [
                    'inventory_item_id' => $item['id'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'reference_no' => $reference,
                    'movement_type' => $type,
                    'quantity_change' => $new - $previous,
                    'previous_stock' => $previous,
                    'new_stock' => $new,
                    'unit' => $item['unit'],
                    'remarks' => $type === 'Stock In' ? 'Received from Purchase Order.' : 'Issued through Warehouse Part Request.',
                    'created_by' => DB::table('users')->where('department', 'Warehouse')->value('id'),
                    'created_at' => $month->copy()->addDays(mt_rand(0, 25))->setTime(mt_rand(9, 17), mt_rand(0, 59)),
                    'updated_at' => $month->copy()->addDays(mt_rand(0, 25))->setTime(mt_rand(9, 17), mt_rand(0, 59)),
                ];
            }
            $month->addMonth();
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('stock_movements')->insert($chunk);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  PURCHASING                                                         */
    /* ------------------------------------------------------------------ */

    protected function seedPurchaseRequests(array $jobOrders, array $inventory, array $users): array
    {
        $maintenanceStaff = array_values(array_filter($users, fn ($u) => $u['department'] === 'Maintenance' && $u['role'] === 'staff'))[0] ?? $users[0];
        $lowStock = array_values(array_filter($inventory, fn ($i) => in_array($i['status'], ['Low Stock', 'Out of Stock'], true)));

        $requestStatuses = ['Draft', 'Submitted', 'Approved', 'Rejected', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up', 'Issued'];
        $purchaseRequests = [];

        for ($i = 1; $i <= 32; $i++) {
            $jobOrder = $jobOrders[($i * 3) % count($jobOrders)];
            $fromInventory = ($i % 3 === 0) && $lowStock ? $lowStock[($i * 2) % count($lowStock)] : null;

            $itemName = $fromInventory ? $fromInventory['item_name'] : explode(' - ', $jobOrder['part_needed'] ?? 'Spare Part - Qty: 1 pc')[0];
            $quantity = $fromInventory ? max(1, $fromInventory['reorder_level'] - $fromInventory['on_hand'] + mt_rand(4, 20)) : mt_rand(2, 12);
            $unit = $fromInventory['unit'] ?? 'pc';

            $status = $requestStatuses[min(10, max(0, intdiv($i * 3, 8)))];

            $approvedAt = null;
            $rejectedAt = null;
            $issuedAt = null;
            $now = Carbon::now();

            if (in_array($status, ['Approved', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up', 'Issued'], true)) {
                $approvedAt = Carbon::create(2026, 5, 1)->addDays($i * 3)->setTime(10, 0);
                if ($approvedAt->gt($now)) {
                    $approvedAt = $now->subDays(mt_rand(1, 30));
                }
            }
            if ($status === 'Rejected') {
                $rejectedAt = $now->subDays(mt_rand(1, 45));
            }
            if (in_array($status, ['Issued', 'Picked Up'], true)) {
                $issuedAt = $now->subDays(mt_rand(0, 20));
            }

            $id = DB::table('purchase_requests')->insertGetId([
                'pr_no' => 'PR-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'job_order_no' => $jobOrder['job_order_no'],
                'bus_no' => $jobOrder['bus_no'],
                'item' => $itemName . ' - Qty: ' . $quantity . ' ' . $unit,
                'quantity' => $quantity,
                'status' => $status,
                'source_type' => $fromInventory ? 'low_stock' : 'job_order',
                'source_inventory_item_id' => $fromInventory ? $fromInventory['id'] : null,
                'remarks' => $status === 'Rejected' ? 'Rejected: pending budget confirmation for the month.' : ($fromInventory ? 'Auto-generated from low-stock inventory reorder point.' : 'Requested to support active job order repairs.'),
                'approved_at' => $approvedAt,
                'rejected_at' => $rejectedAt,
                'issued_at' => $issuedAt,
                'created_at' => Carbon::create(2026, 5, 1)->addDays($i * 2)->setTime(mt_rand(8, 15), mt_rand(0, 59)),
                'updated_at' => $approvedAt ?? $rejectedAt ?? $issuedAt ?? Carbon::now()->subDays(mt_rand(0, 20)),
            ]);

            $purchaseRequests[] = ['id' => $id, 'pr_no' => 'PR-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'job_order_no' => $jobOrder['job_order_no'], 'bus_no' => $jobOrder['bus_no'], 'status' => $status, 'item' => $itemName, 'quantity' => $quantity, 'unit' => $unit];
        }

        return $purchaseRequests;
    }

    protected function seedPurchaseOrders(array $purchaseRequests, array $users): array
    {
        $approvedRequests = array_values(array_filter($purchaseRequests, fn ($pr) => ! in_array($pr['status'], ['Rejected', 'Draft', 'Submitted'], true)));
        $suppliers = ['AutoPlus Trading Cebu', 'PhilHino Sales Corp.', 'Sumisho Global Mobility', 'Tri-City Auto Supply', 'Northwest Auto Parts', 'Master Auto Depot Cebu'];
        $purchaseStatuses = ['Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up'];
        $purchaseOrders = [];

        for ($i = 1; $i <= 14 && $approvedRequests; $i++) {
            $pr = $approvedRequests[($i * 5) % count($approvedRequests)];
            $supplier = $suppliers[$i % count($suppliers)];
            $status = $purchaseStatuses[min(4, intdiv($i, 3))];

            $items = [];
            $gross = 0.0;
            for ($j = 0; $j < mt_rand(1, 3); $j++) {
                $item = $approvedRequests[($i + $j) % count($approvedRequests)];
                $unitPrice = mt_rand(80, 8000) / 10;
                $lineTotal = round($unitPrice * $item['quantity'], 2);
                $gross += $lineTotal;
                $items[] = ['pr_no' => $item['pr_no'], 'item' => $item['item'], 'quantity' => $item['quantity'], 'unit' => $item['unit'], 'amount' => $lineTotal];
            }

            $deliveryFee = round(mt_rand(50, 150) / 10 * 100, 2);
            $discount = round($gross * mt_rand(0, 50) / 1000, 2);
            $vat = round(($gross - $discount) * 0.12, 2);
            $net = round($gross + $deliveryFee - $discount + $vat, 2);

            $poDate = Carbon::create(2026, 5, 1)->addDays($i * 6)->setTime(9, 0);
            if ($poDate->gt(Carbon::now())) {
                $poDate = Carbon::now()->subDays(mt_rand(1, 40));
            }

            $delivered = in_array($status, ['Delivered', 'Picked Up'], true);

            $id = DB::table('purchase_orders')->insertGetId([
                'po_no' => 'PO-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'po_date' => $poDate->toDateString(),
                'purchase_request_id' => $pr['id'],
                'supplier_name' => $supplier,
                'supplier_address_tel' => 'Cebu City, Philippines | Tel: (032) ' . mt_rand(200000, 499999),
                'terms' => '30 days',
                'terms_of_payment' => 'Net 30',
                'purpose' => 'Replenishment for active maintenance and auto-scheduling requirements.',
                'items' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'gross_amount' => $gross,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'vat' => $vat,
                'net_amount' => $net,
                'status' => $status,
                'inventory_posted_at' => $delivered ? $poDate->copy()->addDays(mt_rand(1, 6)) : null,
                'created_at' => $poDate,
                'updated_at' => $delivered ? $poDate->copy()->addDays(6) : Carbon::now(),
            ]);

            $purchaseOrders[] = ['id' => $id, 'po_no' => 'PO-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'status' => $status, 'pr_no' => $pr['pr_no'], 'supplier_name' => $supplier, 'net_amount' => $net, 'po_date' => $poDate];
        }

        return $purchaseOrders;
    }

    protected function seedScheduledPurchases(array $purchaseOrders): void
    {
        $rows = [
            ['SCH-PO-001', 'Monthly Engine Oil Resupply', 'Engine Oil 15W-40', 'PhilHino Sales Corp.', '0917-123-4567', 240, 'liter', 'Monthly', null, '2026-03-01'],
            ['SCH-PO-002', 'Biweekly Oil Filter Replenish', 'Oil Filter', 'PhilHino Sales Corp.', '0917-123-4567', 80, 'pc', 'Biweekly', null, '2026-03-05'],
            ['SCH-PO-003', 'Quarterly Tire Replacement', 'Tire 11R22.5', 'Sumisho Global Mobility', '0918-222-3344', 12, 'pc', 'Quarterly', null, '2026-02-15'],
            ['SCH-PO-004', 'Weekly Fuel Filter Stock', 'Fuel Filter', 'Northwest Auto Parts', '0916-555-8800', 20, 'pc', 'Weekly', null, '2026-04-10'],
            ['SCH-PO-005', 'Monthly Brake Parts Set', 'Brake Pad (Front)', 'Tri-City Auto Supply', '0919-777-2211', 16, 'set', 'Monthly', null, '2026-03-20'],
            ['SCH-PO-006', 'Semiannual Battery Rotation', 'Battery 12V 180Ah', 'Sumisho Global Mobility', '0918-222-3344', 6, 'pc', 'Semiannual', null, '2026-01-10'],
            ['SCH-PO-007', 'Monthly Aircon Refrigerant', 'AC Refrigerant R134a', 'Sumisho Global Mobility', '0918-222-3344', 12, 'can', 'Monthly', null, '2026-03-01'],
            ['SCH-PO-008', 'Quarterly Safety & Consumables', 'Nitrile Gloves (Box)', 'Master Auto Depot Cebu', '0915-444-9966', 30, 'box', 'Quarterly', null, '2026-02-25'],
            ['SCH-PO-009', 'Monthly Spark Plug Refill', 'Spark Plug', 'Northwest Auto Parts', '0916-555-8800', 40, 'pc', 'Monthly', null, '2026-04-01'],
            ['SCH-PO-010', 'Custom Suspension Parts Order', 'Shock Absorber (Front)', 'Sumisho Global Mobility', '0918-222-3344', 8, 'pc', 'Custom', 45, '2026-05-20'],
        ];

        $frequencies = [
            'Weekly' => 7, 'Biweekly' => 14, 'Monthly' => 30, 'Quarterly' => 90,
            'Semiannual' => 180, 'Yearly' => 365, 'Custom' => 45,
        ];

        foreach ($rows as $i => [$scheduleNo, $name, $item, $supplier, $contact, $qty, $unit, $frequency, $customDays, $start]) {
            $cost = mt_rand(15, 450) * 10;

            $nextPurchase = Carbon::parse($start)->addDays(($frequencies[$frequency] ?? 30) * (($i % 4) + 1));
            $status = $nextPurchase->lt(Carbon::now()->subDays(15)) ? 'Completed' : ($i % 6 === 3 ? 'Paused' : ($i % 7 === 5 ? 'Active' : 'Active'));

            $lastPo = $i % 2 === 0 && $purchaseOrders ? $purchaseOrders[$i % count($purchaseOrders)] : null;

            if (! $nextPurchase->gt(Carbon::now())) {
                $nextPurchase = Carbon::now()->addDays(mt_rand(-3, 45));
            }

            DB::table('scheduled_purchases')->insert([
                'schedule_no' => $scheduleNo,
                'schedule_name' => $name,
                'supplier_name' => $supplier,
                'supplier_contact' => $contact,
                'item' => $item,
                'quantity' => $qty,
                'unit' => $unit,
                'frequency' => $frequency,
                'custom_interval_days' => $customDays,
                'start_date' => $start,
                'next_purchase_date' => $nextPurchase->toDateString(),
                'estimated_cost' => $cost,
                'status' => $status,
                'notes' => 'Sample scheduled purchase for recurring supply replenishment.',
                'last_po_id' => $lastPo['id'] ?? null,
                'last_purchased_at' => $lastPo ? Carbon::now()->subDays(mt_rand(3, 30)) : null,
                'created_at' => Carbon::parse($start)->setTime(10, 0),
                'updated_at' => now(),
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  BATCH UPLOADS / DATA ACTIVITIES                                    */
    /* ------------------------------------------------------------------ */

    protected function seedBatchUploads(array $users): array
    {
        $opsUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Operation'))[0] ?? $users[0];
        $mntUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Maintenance'))[0] ?? $users[0];
        $whsUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Warehouse'))[0] ?? $users[0];
        $prchUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Purchase'))[0] ?? $users[0];

        $defs = [
            ['GPS_Trip_Records_0626.csv', 'Operation', 'GPS Trip Records', 28, 28, 0, 'Processed', $opsUser['id'], '2026-06-26'],
            ['GPS_Trip_Records_0713.csv', 'Operation', 'GPS Trip Records', 41, 41, 0, 'Processed', $opsUser['id'], '2026-07-13'],
            ['GPS_Trip_Records_0728.csv', 'Operation', 'GPS Trip Records', 52, 50, 2, 'Needs Correction', $opsUser['id'], '2026-07-28'],
            ['GPS_Trip_Records_0810.csv', 'Operation', 'GPS Trip Records', 47, 47, 0, 'Processed', $opsUser['id'], '2026-08-10'],
            ['GPS_Trip_Records_0825.csv', 'Operation', 'GPS Trip Records', 39, 39, 0, 'Processed', $opsUser['id'], '2026-08-25'],
            ['Fuel_Reports_0815.csv', 'Maintenance', 'Fuel Reports', 22, 22, 0, 'Processed', $mntUser['id'], '2026-08-15'],
            ['Inventory_Stock_0901.csv', 'Warehouse', 'Inventory Records', 40, 40, 0, 'Processed', $whsUser['id'], '2026-09-01'],
            ['Purchase_Orders_0718.csv', 'Purchase', 'Purchase Orders', 6, 6, 0, 'Processed', $prchUser['id'], '2026-07-18'],
            ['GPS_Trip_Records_0903.csv', 'Operation', 'GPS Trip Records', 0, 0, 5, 'Failed', $opsUser['id'], '2026-09-03'],
            ['Fuel_Reports_0902.csv', 'Maintenance', 'Fuel Reports', 0, 0, 8, 'Failed', $mntUser['id'], '2026-09-02'],
        ];

        $batchUploads = [];
        foreach ($defs as [$file, $module, $type, $total, $processed, $failed, $status, $user, $date]) {
            $id = DB::table('batch_uploads')->insertGetId([
                'file_name' => $file,
                'stored_name' => uniqid('stored_') . '_' . $file,
                'file_path' => storage_path('app/batch_uploads/' . $file),
                'file_type' => $type === 'GPS Trip Records' ? 'gpx' : 'csv',
                'module' => $module,
                'data_type' => $type,
                'bus_no' => null,
                'uploaded_by' => $user,
                'status' => $status,
                'total_records' => $total,
                'processed_records' => $processed,
                'failed_records' => $failed,
                'error_message' => $status === 'Failed' ? 'File format mismatch in header row.' : null,
                'created_at' => Carbon::parse($date)->setTime(mt_rand(9, 16), mt_rand(0, 59)),
                'updated_at' => Carbon::parse($date)->setTime(17, 0),
            ]);

            $batchUploads[] = ['id' => $id, 'module' => $module, 'data_type' => $type, 'status' => $status, 'total' => $total, 'failed' => $failed, 'user_id' => $user, 'date' => $date];
        }

        return $batchUploads;
    }

    protected function seedBatchProcessedAndActivities(array $batchUploads, array $gpsRecords, array $fuelReports, array $inventory, array $purchaseOrders, array $users): void
    {
        $processedBatches = array_values(array_filter($batchUploads, fn ($b) => $b['status'] === 'Processed'));

        $map = [];
        foreach ($gpsRecords as $i => $g) {
            $batch = $processedBatches[$i % max(1, count($processedBatches))];
            $stamp = Carbon::parse($g['date'])->setTime(15, mt_rand(0, 59));
            $map[] = [
                'batch_upload_id' => $batch['id'],
                'payload' => json_encode(['bus_no' => $g['bus_no'], 'date' => $g['date'], 'distance' => $g['distance']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'raw_data' => json_encode(['source' => 'gpx'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'In Review',
                'destination_type' => 'gps_trip_record',
                'destination_id' => $g['id'],
                'created_at' => $stamp,
                'updated_at' => $stamp->copy()->addMinutes(3),
            ];
        }

        foreach (array_filter($fuelReports, fn ($f) => isset($f)) as $i => $f) {
            $batch = $processedBatches[$i % max(1, count($processedBatches))];
            $stamp = Carbon::parse($f['date'])->setTime(16, mt_rand(0, 59));
            $map[] = [
                'batch_upload_id' => $batch['id'],
                'payload' => json_encode(['bus_no' => $f['bus_no'], 'date' => $f['date'], 'kmpl' => $f['kmpl']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'raw_data' => json_encode(['source' => 'csv'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'Processed',
                'destination_type' => 'fuel_report',
                'destination_id' => $f['id'],
                'created_at' => $stamp,
                'updated_at' => $stamp->copy()->addMinutes(3),
            ];
        }

        foreach (array_chunk($map, 200) as $chunk) {
            DB::table('batch_processed_records')->insert($chunk);
        }

        $activityDefs = [];
        foreach ($batchUploads as $b) {
            $activityDefs[] = [
                'activity_type' => 'Import',
                'module' => $b['module'],
                'data_type' => $b['data_type'],
                'file_name' => null,
                'source' => 'Structured File Import',
                'status' => $b['status'] === 'Failed' ? 'Failed' : ($b['status'] === 'Needs Correction' ? 'Needs Correction' : 'Completed'),
                'total_records' => $b['total'],
                'successful_records' => max(0, $b['total'] - $b['failed']),
                'failed_records' => $b['failed'],
                'skipped_records' => 0,
                'processed_by' => $b['user_id'],
                'reference_type' => 'batch_upload',
                'reference_id' => $b['id'],
                'details' => json_encode(['validation_errors' => $b['failed'] > 0 ? ['Header row mismatch (file #' . $b['id'] . ')'] : [], 'staged_payloads' => max(0, $b['total'] - $b['failed'])], JSON_UNESCAPED_UNICODE),
                'error_message' => $b['failed'] > 0 ? 'Some rows failed validation.' : null,
                'completed_at' => Carbon::parse($b['date'])->setTime(17, 0),
                'created_at' => Carbon::parse($b['date'])->setTime(14, mt_rand(0, 59)),
                'updated_at' => Carbon::parse($b['date'])->setTime(17, 0),
            ];
        }

        foreach ($activityDefs as $row) {
            DB::table('data_activities')->insert($row);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  ACTIVITY LOGS                                                      */
    /* ------------------------------------------------------------------ */

    protected function seedActivityLogs(array $users, array $jobOrders, array $purchaseRequests, array $purchaseOrders, array $tripSchedules, array $buses, array $inventory): void
    {
        $rows = [];

        foreach ($users as $user) {
            $module = match (strtolower($user['department'])) {
                'operation' => 'Operation',
                'warehouse' => 'Warehouse',
                'purchase' => 'Purchase',
                'admin' => 'Admin',
                default => 'Maintenance',
            };

            $creds = [];
            foreach (range(0, 25) as $k) {
                $date = Carbon::now()->subDays(mt_rand(0, 150))->setTime(mt_rand(6, 21), mt_rand(0, 59));
                $creds[] = [
                    'user_id' => $user['id'],
                    'user_name' => $user['name'],
                    'user_role' => $user['role'],
                    'department' => $user['department'],
                    'activity' => 'Successful login',
                    'module' => 'Auth',
                    'reference' => null,
                    'event_type' => 'login',
                    'details' => null,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Demo Session (Sample Data)',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $rows = array_merge($rows, $creds);
        }

        $actions = [
            ['Maintenance', 'create', 'Created job order repair record', 'JobOrder'],
            ['Maintenance', 'update', 'Updated job order status', 'JobOrder'],
            ['Maintenance', 'assign', 'Assigned mechanic to job order', 'JobOrder'],
            ['Maintenance', 'complete', 'Marked job order as completed', 'JobOrder'],
            ['Maintenance', 'import', 'Imported fuel report records', 'FuelReport'],
            ['Maintenance', 'create', 'Created PMS schedule for bus', 'PmsSchedule'],
            ['Operation', 'create', 'Scheduled new trip', 'TripSchedule'],
            ['Operation', 'assign', 'Assigned driver and bus to trip', 'TripAssignment'],
            ['Operation', 'update', 'Completed trip record', 'TripSchedule'],
            ['Operation', 'import', 'Imported GPS trip records', 'GpsTripRecord'],
            ['Operation', 'report', 'Generated fleet trip analytics', 'Analytics'],
            ['Operation', 'report', 'Generated fuel efficiency analytics', 'Analytics'],
            ['Warehouse', 'create', 'Created inventory item', 'InventoryItem'],
            ['Warehouse', 'update', 'Adjusting on-hand stock quantity', 'InventoryItem'],
            ['Warehouse', 'issue', 'Issued stock for part request', 'StockMovement'],
            ['Warehouse', 'update', 'Received stock from purchase order', 'StockMovement'],
            ['Warehouse', 'import', 'Imported inventory stock records', 'InventoryItem'],
            ['Purchase', 'create', 'Filed purchase request', 'PurchaseRequest'],
            ['Purchase', 'approve', 'Approved purchase request', 'PurchaseRequest'],
            ['Purchase', 'reject', 'Rejected purchase request', 'PurchaseRequest'],
            ['Purchase', 'create', 'Created purchase order', 'PurchaseOrder'],
            ['Purchase', 'update', 'Updated purchase order status', 'PurchaseOrder'],
            ['Purchase', 'create', 'Set up scheduled purchase', 'ScheduledPurchase'],
            ['Admin', 'update', 'Updated role permissions', 'RolePermission'],
            ['Maintenance', 'view', 'Viewed predictive maintenance analytics', 'Analytics'],
        ];

        $samples = [
            'JobOrder' => fn () => $jobOrders[mt_rand(0, count($jobOrders) - 1)]['job_order_no'],
            'TripSchedule' => fn () => $tripSchedules[mt_rand(0, count($tripSchedules) - 1)]['trip_code'],
            'TripAssignment' => fn () => $buses[mt_rand(0, count($buses) - 1)]['bus_no'],
            'PmsSchedule' => fn () => $buses[mt_rand(0, count($buses) - 1)]['bus_no'],
            'GpsTripRecord' => fn () => 'GPS-' . Carbon::now()->subDays(mt_rand(1, 30))->format('Y-m-d'),
            'FuelReport' => fn () => $buses[mt_rand(0, count($buses) - 1)]['bus_no'],
            'InventoryItem' => fn () => $inventory[mt_rand(0, count($inventory) - 1)]['item_code'],
            'StockMovement' => fn () => $inventory[mt_rand(0, count($inventory) - 1)]['item_code'],
            'PurchaseRequest' => fn () => 'PR-2026-' . str_pad((string) mt_rand(1, 32), 4, '0', STR_PAD_LEFT),
            'PurchaseOrder' => fn () => 'PO-2026-' . str_pad((string) mt_rand(1, 14), 4, '0', STR_PAD_LEFT),
            'ScheduledPurchase' => fn () => 'SCH-PO-00' . mt_rand(1, 9),
            'RolePermission' => fn () => 'role_permissions',
            'Analytics' => null,
        ];

        $userPool = $users;
        foreach ($actions as $i => [$module, $eventType, $activity, $entity]) {
            $count = $module === 'Auth' ? 6 : mt_rand(4, 9);
            for ($k = 0; $k < $count; $k++) {
                $user = $userPool[mt_rand(0, count($userPool) - 1)];
                $ref = $samples[$entity] ? ($samples[$entity])() : null;
                $date = Carbon::now()->subDays(mt_rand(0, 150))->setTime(mt_rand(7, 18), mt_rand(0, 59));

                $rows[] = [
                    'user_id' => $user['id'],
                    'user_name' => $user['name'],
                    'user_role' => $user['role'],
                    'department' => $user['department'],
                    'activity' => $activity,
                    'module' => $module,
                    'reference' => $ref,
                    'event_type' => $eventType,
                    'details' => 'Sample/demo activity log entry.',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Demo Session (Sample Data)',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('activity_logs')->insert($chunk);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  TOPBAR NOTIFICATIONS                                              */
    /* ------------------------------------------------------------------ */

    protected function seedTopbarNotifications(array $jobOrders, array $inventory, array $purchaseRequests, array $tripSchedules, array $pmsSchedules, array $users): void
    {
        $maintenanceUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Maintenance'))[0] ?? $users[0];
        $opsUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Operation'))[0] ?? $users[0];
        $warehouseUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Warehouse'))[0] ?? $users[0];
        $purchaseUser = array_values(array_filter($users, fn ($u) => $u['department'] === 'Purchase'))[0] ?? $users[0];

        $notifications = [];
        $ongoing = array_values(array_filter($jobOrders, fn ($j) => $j['status'] === 'On Going'));
        foreach (array_slice($ongoing, 0, 3) as $jo) {
            $notifications[] = ['Maintenance', 'JobOrder', 'overdue', $jo['id'], $jo['job_order_no'] . ' exceeded its estimated work duration. Please verify whether the repair is completed or needs more time.', $maintenanceUser['id']];
        }

        foreach (array_values(array_filter($inventory, fn ($i) => $i['status'] === 'Low Stock')) as $item) {
            $notifications[] = ['Warehouse', 'InventoryItem', 'low_stock', $item['id'], $item['item_name'] . ' is low on stock (' . $item['on_hand'] . ' ' . $item['unit'] . '). Please request replenishment.', $warehouseUser['id']];
        }

        foreach (array_values(array_filter($inventory, fn ($i) => $i['status'] === 'Out of Stock')) as $item) {
            $notifications[] = ['Warehouse', 'InventoryItem', 'out_of_stock', $item['id'], $item['item_name'] . ' is out of stock. Immediate procurement is required.', $warehouseUser['id']];
        }

        foreach (array_slice(array_values(array_filter($purchaseRequests, fn ($pr) => $pr['status'] === 'Submitted' || $pr['status'] === 'Draft')), 0, 3) as $pr) {
            $notifications[] = ['Purchase', 'PurchaseRequest', 'pending_approval', $pr['id'], $pr['pr_no'] . ' is awaiting approval.', $purchaseUser['id']];
        }

        foreach (array_slice(array_values(array_filter($tripSchedules, fn ($t) => $t['status'] === 'Delayed')), 0, 2) as $trip) {
            $notifications[] = ['Operation', 'TripSchedule', 'delayed', $trip['id'], 'Trip ' . $trip['trip_code'] . ' was flagged as delayed. Please review the route performance.', $opsUser['id']];
        }

        foreach (array_slice($pmsSchedules, 0, 3) as $pms) {
            $notifications[] = ['Maintenance', 'PmsSchedule', 'due', $pms['id'], 'PMS (' . $pms['maintenance_type'] . ') is due for bus ' . $pms['bus_no'] . '.', $maintenanceUser['id']];
        }

        foreach ($notifications as [$module, $entity, $action, $recordId, $message, $createdBy]) {
            $id = DB::table('topbar_notifications')->insertGetId([
                'module' => $module,
                'entity' => $entity,
                'action' => $action,
                'record_id' => (string) $recordId,
                'message' => $message,
                'created_by' => $createdBy,
                'created_at' => Carbon::now()->subDays(mt_rand(0, 4))->setTime(mt_rand(8, 17), mt_rand(0, 59)),
                'updated_at' => now(),
            ]);

            DB::table('topbar_read_states')->insertOrIgnore([
                'user_id' => $createdBy,
                'notifications_read_at' => Carbon::now()->subHours(mt_rand(1, 6)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  VERIFICATION                                                       */
    /* ------------------------------------------------------------------ */

    protected function reportCounts(): void
    {
        $tables = [
            'users', 'buses', 'mechanics', 'drivers', 'shuttle_routes', 'route_stops',
            'pms_schedules', 'job_orders', 'mechanic_attendances', 'driver_attendances',
            'trip_schedules', 'trip_assignments', 'gps_trip_records', 'fuel_reports',
            'batch_uploads', 'batch_processed_records', 'data_activities',
            'inventory_items', 'stock_movements',
            'purchase_requests', 'purchase_orders', 'scheduled_purchases',
            'activity_logs', 'topbar_notifications', 'topbar_read_states', 'topbar_notification_reads',
        ];

        $this->command?->newLine();
        $this->command?->info('=== DEMO DATA SEEDED (sample/demo only) ===');
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $this->command?->line(sprintf('  %-30s %s', $table, $count));
        }
    }
}