<?php

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.connections.mysql.database') !== 'testing') {
    fwrite(STDERR, "Refusing concurrency validation outside testing database.\n");
    exit(2);
}

config(['broadcasting.default' => 'null']);

$worker = __DIR__.'/operation_concurrency_worker.php';
$runId = date('His').'-'.bin2hex(random_bytes(3));
$email = "operation-concurrency-{$runId}@example.test";
$driverId = "CONC-DRV-{$runId}";
$busNo = "CONC-BUS-{$runId}";
$results = [];

$cleanup = static function () use ($email, $driverId, $busNo): void {
    $reportIds = DB::table('daily_driver_reports')
        ->where('trip_ticket', 'like', 'CONC-%')
        ->pluck('id');
    if ($reportIds->isNotEmpty()) {
        DB::table('daily_driver_report_ticket_claims')->whereIn('daily_driver_report_id', $reportIds)->delete();
        DB::table('daily_driver_report_trip_entries')->whereIn('daily_driver_report_id', $reportIds)->delete();
        DB::table('daily_driver_reports')->whereIn('id', $reportIds)->delete();
    }

    $incidentIds = DB::table('incidents')->where('location', 'like', 'CONC-%')->pluck('id');
    if ($incidentIds->isNotEmpty()) {
        foreach (['incident_responses', 'incident_replacements', 'maintenance_referrals'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'incident_id')) {
                DB::table($table)->whereIn('incident_id', $incidentIds)->delete();
            }
        }
        DB::table('incidents')->whereIn('id', $incidentIds)->delete();
    }

    DB::table('operation_number_sequences')->where('sequence_key', 'like', 'ddr:%')->delete();
    DB::table('operation_number_sequences')->where('sequence_key', 'incident')->delete();
    DB::table('buses')->where('bus_no', $busNo)->delete();
    DB::table('drivers')->where('driver_id', $driverId)->delete();
    DB::table('users')->where('email', $email)->delete();
};

$launch = static function (string $mode, int $count, string $batch, User $user, Driver $driver, Bus $bus) use ($worker): array {
    $startAt = microtime(true) + 5;
    $processes = [];

    for ($index = 1; $index <= $count; $index++) {
        $pipes = [];
        $process = proc_open([
            PHP_BINARY,
            $worker,
            $mode,
            (string) $index,
            $batch,
            (string) $startAt,
            (string) $user->id,
            $driver->driver_id,
            (string) $bus->id,
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 2));

        fclose($pipes[0]);
        $processes[] = [$process, $pipes[1], $pipes[2]];
    }

    $failures = [];
    foreach ($processes as [$process, $stdout, $stderr]) {
        $output = stream_get_contents($stdout);
        $error = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $failures[] = trim($error ?: $output);
        }
    }

    return $failures;
};

try {
    $cleanup();

    $user = User::factory()->create([
        'email' => $email,
        'department' => 'Operation',
        'role' => 'staff',
    ]);
    $driver = Driver::create([
        'driver_id' => $driverId,
        'driver_name' => 'Concurrency Driver',
        'shift' => 'Morning',
        'employment_status' => 'Active',
    ]);
    $bus = Bus::create([
        'bus_no' => $busNo,
        'plate_no' => 'CONC-'.substr(md5($runId), 0, 6),
        'bus_model' => 'Concurrency Test Bus',
        'capacity' => 40,
        'status' => 'Active',
    ]);

    foreach ([10, 20, 50] as $count) {
        foreach (['ddr', 'incident'] as $mode) {
            $batch = "{$runId}-{$mode}-{$count}";
            $failures = $launch($mode, $count, $batch, $user, $driver, $bus);

            if ($mode === 'ddr') {
                $parents = DB::table('daily_driver_reports')->where('trip_ticket', 'like', "CONC-DDR-{$batch}-%")->count();
                $parentIds = DB::table('daily_driver_reports')->where('trip_ticket', 'like', "CONC-DDR-{$batch}-%")->pluck('id');
                $children = DB::table('daily_driver_report_ticket_claims')->whereIn('daily_driver_report_id', $parentIds)->count();
                $uniqueNumbers = DB::table('daily_driver_reports')->whereIn('id', $parentIds)->distinct()->count('ddr_no');
            } else {
                $parents = DB::table('incidents')->where('location', 'like', "CONC-INC-{$batch}-%")->count();
                $parentIds = DB::table('incidents')->where('location', 'like', "CONC-INC-{$batch}-%")->pluck('id');
                $children = DB::table('incident_responses')->whereIn('incident_id', $parentIds)->count();
                $uniqueNumbers = DB::table('incidents')->whereIn('id', $parentIds)->distinct()->count('incident_no');
            }

            $passed = count($failures) === 0
                && $parents === $count
                && $children === $count
                && $uniqueNumbers === $count;
            $results[] = compact('mode', 'count', 'parents', 'children', 'uniqueNumbers', 'failures', 'passed');
        }
    }

    $batch = "{$runId}-duplicate";
    $failures = $launch('ddr-duplicate', 20, $batch, $user, $driver, $bus);
    $parents = DB::table('daily_driver_reports')->where('trip_ticket', "CONC-DUP-{$batch}")->count();
    $parentIds = DB::table('daily_driver_reports')->where('trip_ticket', "CONC-DUP-{$batch}")->pluck('id');
    $children = DB::table('daily_driver_report_ticket_claims')->whereIn('daily_driver_report_id', $parentIds)->count();
    $results[] = [
        'mode' => 'ddr-duplicate',
        'count' => 20,
        'parents' => $parents,
        'children' => $children,
        'uniqueNumbers' => DB::table('daily_driver_reports')->whereIn('id', $parentIds)->distinct()->count('ddr_no'),
        'failures' => $failures,
        'passed' => $parents === 1 && $children === 1,
    ];

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(collect($results)->every(fn (array $result) => $result['passed']) ? 0 : 1);
} finally {
    $cleanup();
}
