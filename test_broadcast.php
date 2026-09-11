<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\SystemDataUpdated;
use Illuminate\Support\Facades\Log;

Log::info('Testing SystemDataUpdated broadcast', ['test' => true]);

event(new SystemDataUpdated('Test', 'Entity', 'test', 123, 'Test message from script'));

echo "Event fired successfully\n";