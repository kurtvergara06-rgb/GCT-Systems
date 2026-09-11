<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$http = new \GuzzleHttp\Client();
try {
    $response = $http->post('http://127.0.0.1:8080/apps/gct-systems/events', [
        'headers' => ['Content-Type' => 'application/json'],
        'json' => ['name' => 'TestEvent', 'channel' => 'system-updates', 'data' => json_encode(['test' => true])]
    ]);
    echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
    echo 'Body: ' . $response->getBody() . PHP_EOL;
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}