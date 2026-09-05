<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdo = DB::connection()->getPdo();

$pdo->query('USE gct_system');

$files = [];
foreach ($pdo->query('SHOW BINARY LOGS')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $files[] = $r['Log_name'];
}

foreach ($files as $f) {
    try {
        $events = $pdo->query("SHOW BINLOG EVENTS IN '$f'")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "[$f] ERROR: ".$e->getMessage().PHP_EOL;
        continue;
    }
    $interesting = array_filter($events, function ($e) {
        $info = $e['Info'] ?? '';
        if ($e['Event_type'] === 'Query') {
            $lower = strtolower($info);
            return strpos($lower, 'use `gct_system`') !== false
                || strpos($lower, 'drop table') !== false
                || strpos($lower, 'create table') !== false
                || strpos($lower, 'delete') !== false
                || strpos($lower, 'truncate') !== false;
        }
        return false;
    });
    if (count($interesting)) {
        echo "== $f ==".PHP_EOL;
        foreach ($interesting as $e) {
            echo "  {$e['Pos']} {$e['Event_type']} ts? {$e['Info']}".PHP_EOL;
        }
    }
}

echo "== server uptime ==".PHP_EOL;
echo (DB::selectOne('SHOW GLOBAL STATUS LIKE "Uptime"')->Value)." seconds".PHP_EOL;
echo "now: ".date('Y-m-d H:i:s').PHP_EOL;