<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$batch = App\Models\Admin\BatchUpload::find(30);
echo 'processing_batches='.App\Models\Admin\BatchUpload::where('status', 'Processing')->count().PHP_EOL;
if (! $batch) {
    echo json_encode([
        'connection' => config('database.default'),
        'database' => config('database.connections.'.config('database.default').'.database'),
        'batch_count' => App\Models\Admin\BatchUpload::count(),
        'recent_batches' => App\Models\Admin\BatchUpload::orderByDesc('id')->limit(5)->get(['id','file_name','status'])->toArray(),
    ]).PHP_EOL;
    exit;
}
$parsed = app(App\Services\Gps\PdfGpsRuleParser::class)->extract($batch->file_path)['records'];
$source = array_column($parsed, null, 'record_no');
$counts = [];
$samples = [];
foreach ($batch->tripRecords()->cursor() as $record) {
    $item = $source[$record->record_no] ?? null;
    if (! $item) {
        $counts['missing_source'] = ($counts['missing_source'] ?? 0) + 1;
        continue;
    }
    foreach ([
        'bus_no' => $record->bus_no,
        'grouping' => $record->grouping,
        'beginning' => $record->beginning_at?->format('Y-m-d H:i:s'),
        'initial_location' => $record->initial_location,
        'ending' => $record->ending_at?->format('Y-m-d H:i:s'),
        'final_location' => $record->final_location,
    ] as $field => $value) {
        if ($value !== $item[$field]) {
            $counts[$field] = ($counts[$field] ?? 0) + 1;
            if (count($samples) < 8) $samples[] = [$record->record_no, $field, $value, $item[$field]];
        }
    }
    $raw = $record->raw_data ?? [];
    if (($raw['source_values'] ?? null) != $item['raw_data']['source_values']) {
        $counts['source_values'] = ($counts['source_values'] ?? 0) + 1;
    }
    foreach (['total_minutes', 'in_motion_minutes', 'idling_minutes', 'engine_hours', 'mileage_km'] as $field) {
        if ($item[$field] !== null && $record->{$field} === null) {
            $counts['missing_'.$field] = ($counts['missing_'.$field] ?? 0) + 1;
        }
        if ($item[$field] !== null && $record->{$field} !== null
            && abs((float) $record->{$field} - $item[$field]) > 0.01) {
            $counts['different_'.$field] = ($counts['different_'.$field] ?? 0) + 1;
            if (count($samples) < 8) $samples[] = [$record->record_no, $field, $record->{$field}, $item[$field]];
        }
    }
}
echo json_encode(['source_rows' => count($parsed), 'stored_rows' => $batch->tripRecords()->count(), 'differences' => $counts, 'samples' => $samples], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach (['NBI-5231-1.7245', 'NGA-6363-1.1', 'NGA-6363-1.3'] as $id) {
    $record = $batch->tripRecords()->where('record_no', $id)->first();
    echo json_encode(['id' => $id, 'stored' => $record?->only(['grouping','beginning_at','initial_location','ending_at','final_location','total_minutes','in_motion_minutes','idling_minutes','engine_hours','mileage_km']), 'raw_engine_hours' => $record?->getRawOriginal('engine_hours'), 'source' => $source[$id] ?? null], JSON_UNESCAPED_UNICODE).PHP_EOL;
}
echo 'columns='.json_encode(Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM gps_trip_records WHERE Field IN ('engine_hours','total_minutes')")).PHP_EOL;
