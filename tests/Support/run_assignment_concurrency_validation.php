<?php

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (app()->environment() !== 'testing'
    || config('database.connections.mysql.database') !== 'testing') {
    fwrite(STDERR, "Refusing assignment concurrency validation outside the isolated testing database.\n");
    exit(2);
}

config([
    'broadcasting.default' => 'null',
    'session.driver' => 'array',
]);

$worker = __DIR__.'/assignment_concurrency_worker.php';
$runId = date('His').'-'.bin2hex(random_bytes(3));
$prefix = 'AC'.date('His').substr(bin2hex(random_bytes(2)), 0, 2);
$results = [];

$cleanup = static function () use ($prefix): void {
    $tripIds = DB::table('trip_schedules')
        ->where('trip_code', 'like', $prefix.'%')
        ->pluck('id');

    if ($tripIds->isNotEmpty()) {
        DB::table('trip_assignments')->whereIn('trip_schedule_id', $tripIds)->delete();
        DB::table('trip_schedules')->whereIn('id', $tripIds)->delete();
    }

    DB::table('driver_attendances')->where('driver_id', $prefix)->delete();
    DB::table('drivers')->where('driver_id', $prefix)->delete();
    DB::table('buses')->where('bus_no', $prefix)->delete();
    DB::table('shuttle_routes')->where('route_code', $prefix)->delete();
    DB::table('users')->where('email', 'like', strtolower($prefix).'%')->delete();
};

$launch = static function (array $jobs) use ($worker): array {
    $startAt = microtime(true) + 3;
    $processes = [];

    foreach ($jobs as $job) {
        $pipes = [];
        $process = proc_open([
            PHP_BINARY,
            $worker,
            $job['mode'],
            (string) $job['trip_id'],
            (string) $job['attendance_id'],
            (string) $job['bus_id'],
            (string) $job['user_id'],
            (string) $startAt,
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 2));

        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2]];
    }

    $outcomes = [];
    foreach ($processes as [$process, $stdout, $stderr]) {
        $output = trim(stream_get_contents($stdout));
        $error = trim(stream_get_contents($stderr));
        fclose($stdout);
        fclose($stderr);
        $exitCode = proc_close($process);
        $decoded = json_decode($output, true);

        if ($exitCode !== 0 || ! is_array($decoded)) {
            $outcomes[] = [
                'result' => 'error',
                'exit_code' => $exitCode,
                'output' => $output,
                'error' => $error,
            ];
        } else {
            $outcomes[] = $decoded;
        }
    }

    return $outcomes;
};

$makeTrips = static function (
    ShuttleRoute $route,
    string $date,
    string $scenario,
    int $count
) use ($prefix): array {
    $trips = [];

    for ($index = 1; $index <= $count; $index++) {
        $trips[] = TripSchedule::create([
            'trip_code' => "{$prefix}{$scenario}{$index}",
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'direction' => 'Incoming',
            'required_buses' => 1,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'estimated_arrival_date' => $date,
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
        ]);
    }

    return $trips;
};

$summarize = static function (string $scenario, array $outcomes, array $tripIds): array {
    $accepted = collect($outcomes)->where('result', 'accepted')->count();
    $rejected = collect($outcomes)->where('result', 'rejected')->count();
    $errors = collect($outcomes)->where('result', 'error')->values()->all();
    $assignments = DB::table('trip_assignments')->whereIn('trip_schedule_id', $tripIds)->count();
    $readyTrips = DB::table('trip_schedules')
        ->whereIn('id', $tripIds)
        ->where('assignment_status', 'Assigned')
        ->where('status', 'Ready')
        ->count();

    return compact(
        'scenario',
        'accepted',
        'rejected',
        'assignments',
        'readyTrips',
        'errors'
    );
};

$exitCode = 1;

try {
    $cleanup();

    $users = collect([1, 2])->map(fn (int $index) => User::factory()->create([
        'email' => strtolower("{$prefix}-user-{$index}@example.test"),
        'department' => 'Operation',
        'role' => 'head',
        'status' => 'Active',
        'must_change_password' => false,
        'onboarding_completed' => true,
    ]));
    Driver::create([
        'driver_id' => $prefix,
        'driver_name' => "Assignment Concurrency Driver {$runId}",
        'shift' => 'Morning',
        'employment_status' => 'Active',
    ]);
    $bus = Bus::create([
        'bus_no' => $prefix,
        'plate_no' => 'AC-'.substr(md5($runId), 0, 6),
        'bus_model' => 'Concurrency Test Bus',
        'capacity' => 40,
        'status' => 'Active',
    ]);
    $route = ShuttleRoute::create([
        'route_code' => $prefix,
        'route_name' => 'Assignment Concurrency Route',
        'origin' => 'Concurrency Origin',
        'origin_latitude' => 14.1,
        'origin_longitude' => 121.1,
        'destination' => 'Concurrency Destination',
        'destination_latitude' => 14.2,
        'destination_longitude' => 121.2,
        'distance_km' => 12.5,
        'estimated_time_minutes' => 60,
        'status' => 'Active',
    ]);

    $dates = [
        now(config('app.business_timezone', 'Asia/Manila'))->addDays(10)->toDateString(),
        now(config('app.business_timezone', 'Asia/Manila'))->addDays(11)->toDateString(),
        now(config('app.business_timezone', 'Asia/Manila'))->addDays(12)->toDateString(),
    ];
    $attendances = collect($dates)->map(fn (string $date) => DriverAttendance::create([
        'driver_id' => $prefix,
        'driver_name' => "Assignment Concurrency Driver {$runId}",
        'shift' => 'Morning',
        'attendance_date' => $date,
        'status' => 'Present',
    ]));

    $manualTrips = $makeTrips($route, $dates[0], 'M', 10);
    $manualJobs = collect($manualTrips)->values()->map(fn (TripSchedule $trip, int $index) => [
        'mode' => 'manual',
        'trip_id' => $trip->id,
        'attendance_id' => $attendances[0]->id,
        'bus_id' => $bus->id,
        'user_id' => $users[$index % $users->count()]->id,
    ])->all();
    $manualResult = $summarize(
        'ten-overlapping-manual-claims',
        $launch($manualJobs),
        collect($manualTrips)->pluck('id')->all()
    );
    $manualResult['passed'] = $manualResult['accepted'] === 1
        && $manualResult['rejected'] === 9
        && $manualResult['assignments'] === 1
        && $manualResult['readyTrips'] === 1
        && $manualResult['errors'] === [];
    $results[] = $manualResult;

    $duplicateTrip = $makeTrips($route, $dates[1], 'D', 1)[0];
    $duplicateJobs = collect(range(1, 10))->map(fn (int $index) => [
        'mode' => 'manual',
        'trip_id' => $duplicateTrip->id,
        'attendance_id' => $attendances[1]->id,
        'bus_id' => $bus->id,
        'user_id' => $users[$index % $users->count()]->id,
    ])->all();
    $duplicateResult = $summarize(
        'ten-duplicate-manual-submissions',
        $launch($duplicateJobs),
        [$duplicateTrip->id]
    );
    $duplicateResult['passed'] = $duplicateResult['accepted'] === 1
        && $duplicateResult['rejected'] === 9
        && $duplicateResult['assignments'] === 1
        && $duplicateResult['readyTrips'] === 1
        && $duplicateResult['errors'] === [];
    $results[] = $duplicateResult;

    $mixedTrips = $makeTrips($route, $dates[2], 'X', 2);
    $mixedJobs = [
        [
            'mode' => 'manual',
            'trip_id' => $mixedTrips[0]->id,
            'attendance_id' => $attendances[2]->id,
            'bus_id' => $bus->id,
            'user_id' => $users[0]->id,
        ],
        [
            'mode' => 'auto',
            'trip_id' => $mixedTrips[1]->id,
            'attendance_id' => $attendances[2]->id,
            'bus_id' => $bus->id,
            'user_id' => $users[1]->id,
        ],
    ];
    $mixedResult = $summarize(
        'manual-versus-auto-confirmation',
        $launch($mixedJobs),
        collect($mixedTrips)->pluck('id')->all()
    );
    $mixedResult['passed'] = $mixedResult['accepted'] === 1
        && $mixedResult['rejected'] === 1
        && $mixedResult['assignments'] === 1
        && $mixedResult['readyTrips'] === 1
        && $mixedResult['errors'] === [];
    $results[] = $mixedResult;

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    $exitCode = collect($results)->every(fn (array $result) => $result['passed']) ? 0 : 1;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'result' => 'harness-error',
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
} finally {
    $cleanup();
}

exit($exitCode);
