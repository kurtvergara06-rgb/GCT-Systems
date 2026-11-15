<?php

use App\Http\Controllers\Operation\AutoSchedulingController;
use App\Http\Controllers\Operation\TripAssignmentController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (app()->environment() !== 'testing'
    || config('database.connections.mysql.database') !== 'testing') {
    fwrite(STDERR, "Refusing assignment concurrency worker outside the isolated testing database.\n");
    exit(2);
}

config([
    'broadcasting.default' => 'null',
    'session.driver' => 'array',
]);

[, $mode, $tripId, $attendanceId, $busId, $userId, $startAt] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

Auth::loginUsingId((int) $userId);

try {
    $payload = [
        'trip_schedule_id' => (int) $tripId,
        'driver_attendance_id' => (int) $attendanceId,
        'bus_id' => (int) $busId,
    ];

    if ($mode === 'auto') {
        $request = Request::create('/operation/auto-scheduling/confirm', 'POST', [
            'recommendations' => [$payload],
        ]);
        $request->setUserResolver(fn () => Auth::user());
        $app->make(AutoSchedulingController::class)->confirm($request);
    } else {
        $request = Request::create('/operation/driver-bus-assignment', 'POST', $payload);
        $request->setUserResolver(fn () => Auth::user());
        $app->make(TripAssignmentController::class)->store($request);
    }

    echo json_encode([
        'result' => 'accepted',
        'mode' => $mode,
        'trip_id' => (int) $tripId,
    ]).PHP_EOL;
} catch (ValidationException $exception) {
    echo json_encode([
        'result' => 'rejected',
        'mode' => $mode,
        'trip_id' => (int) $tripId,
        'errors' => $exception->errors(),
    ]).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'result' => 'error',
        'mode' => $mode,
        'trip_id' => (int) $tripId,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ]).PHP_EOL);
    exit(1);
}
