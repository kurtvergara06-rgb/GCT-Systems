<?php

use App\Http\Controllers\Operation\DailyDriverReportController;
use App\Http\Controllers\Operation\IncidentController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.connections.mysql.database') !== 'testing') {
    fwrite(STDERR, "Refusing concurrency worker outside testing database.\n");
    exit(2);
}

config(['broadcasting.default' => 'null']);

[$script, $mode, $index, $batch, $startAt, $userId, $driverId, $busId] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

Auth::loginUsingId((int) $userId);

try {
    if ($mode === 'ddr' || $mode === 'ddr-duplicate') {
        $ticket = $mode === 'ddr-duplicate'
            ? "CONC-DUP-{$batch}"
            : "CONC-DDR-{$batch}-{$index}";
        $request = Request::create('/daily-driver-reports', 'POST', [
            'report_date' => '2026-10-01',
            'driver_id' => $driverId,
            'bus_id' => (int) $busId,
            'trip_ticket' => $ticket,
            'from_location' => 'Concurrency Origin',
            'to_location' => 'Concurrency Destination',
            'departure_time' => '05:30',
            'arrival_time' => '06:30',
            'passengers' => 10,
            'km' => 25,
        ]);
        $request->setUserResolver(fn () => Auth::user());
        $app->make(DailyDriverReportController::class)->store($request);
    } else {
        $request = Request::create('/incidents', 'POST', [
            'incident_type' => 'Traffic',
            'location' => "CONC-INC-{$batch}-{$index}",
            'description' => 'Concurrent incident validation.',
        ]);
        $request->setUserResolver(fn () => Auth::user());
        $app->make(IncidentController::class)->store($request);
    }

    echo json_encode(['ok' => true, 'index' => (int) $index]).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'index' => (int) $index,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ]).PHP_EOL);
    exit(1);
}
