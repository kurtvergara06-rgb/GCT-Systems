<?php

// Local, read-only controller/view profiler. This does not execute HTTP middleware
// or measure browser TTFB. Never run it against production or the original DB.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;

$options = getopt('', ['samples::', 'date::']);
$samples = max(1, min(10, (int) ($options['samples'] ?? 3)));
$date = $options['date'] ?? now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
    throw new RuntimeException('Use a YYYY-MM-DD date.');
}
$connection = DB::connection();
if (!$app->environment('local') || $connection->getDriverName() !== 'mysql'
    || !in_array(config('database.connections.'.config('database.default').'.host'), ['localhost', '127.0.0.1', '::1'], true)
    || $connection->getDatabaseName() !== 'gct_system_clean'
    || config('database.connections.'.config('database.default').'.url')) {
    throw new RuntimeException('Profiler requires local MySQL gct_system_clean with no DB_URL override.');
}

// MySQL itself rejects accidental writes, including writes hidden in view helpers.
$connection->statement('SET TRANSACTION READ ONLY');
$connection->beginTransaction();
$queries = [];
DB::listen(function ($query) use (&$queries): void {
    $queries[] = ['sql' => preg_replace('/\s+/', ' ', $query->sql), 'ms' => $query->time];
});
try {
    $user = User::query()->where('department', 'Operation')->where('role', 'head')->where('status', 'Active')->firstOrFail();
    config(['session.driver' => 'array']);
    $session = app('session')->driver('array');
    $session->start();
    $routes = [
        'dashboard' => ['/operation/dashboard', 'OperationDashboardController', 'index', ['date' => $date]],
        'drivers' => ['/operation/personnel/drivers', 'PersonnelController', 'drivers', []],
        'assignments' => ['/operation/driver-bus-assignment', 'TripAssignmentController', 'index', ['trip_date' => $date]],
        'schedule' => ['/operation/trip-schedule', 'TripScheduleController', 'index', ['trip_date' => $date]],
        'auto' => ['/operation/auto-scheduling', 'AutoSchedulingController', 'index', ['schedule_date' => $date]],
        'attendance' => ['/driver-attendance', 'DriverAttendanceController', 'index', []],
        'ddr' => ['/operation/daily-driver-reports', 'DailyDriverReportController', 'index', []],
    ];
    $output = ['date' => $date, 'sapi' => PHP_SAPI, 'scope' => 'controller + rendered Blade; no HTTP middleware; read-only transaction; same-process samples', 'pages' => []];
    foreach ($routes as $name => [$path, $controller, $method, $parameters]) {
        $runs = [];
        for ($i = 0; $i < $samples; $i++) {
            $request = Request::create('http://gct_system.test'.$path, 'GET', $parameters);
            $request->setLaravelSession($session);
            $request->setUserResolver(fn () => clone $user);
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $app->instance('request', $request);
            URL::setRequest($request);
            Auth::guard()->setUser(clone $user);
            app('view')->share('errors', new ViewErrorBag());
            $queries = [];
            $started = hrtime(true);
            $view = $app->call([$app->make('App\\Http\\Controllers\\Operation\\'.$controller), $method], ['request' => $request]);
            $controllerMs = (hrtime(true) - $started) / 1e6;
            $html = $view->render();
            $elapsed = (hrtime(true) - $started) / 1e6;
            $groups = [];
            foreach ($queries as $query) {
                $key = $query['sql'];
                $groups[$key] ??= ['sql' => $key, 'count' => 0, 'ms' => 0];
                $groups[$key]['count']++;
                $groups[$key]['ms'] += $query['ms'];
            }
            usort($groups, fn ($a, $b) => $b['count'] <=> $a['count'] ?: $b['ms'] <=> $a['ms']);
            $runs[] = ['sample' => $i + 1, 'controller_ms' => round($controllerMs, 2), 'render_ms' => round($elapsed - $controllerMs, 2), 'total_ms' => round($elapsed, 2), 'queries' => count($queries), 'sql_ms' => round(array_sum(array_column($queries, 'ms')), 2), 'html_bytes' => strlen($html), 'top_queries' => array_slice($groups, 0, 8)];
        }
        $output['pages'][$name] = $runs;
    }
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} finally {
    $connection->rollBack();
}
