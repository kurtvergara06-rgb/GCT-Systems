<?php

return [
    // Disabled until the dedicated worker, credentials and memory test pass.
    'enabled' => env('GCT_GPS_NLP_ENABLED', false),
    'url' => env('NLP_API_URL', 'http://127.0.0.1:8000'),
    'token' => env('GCT_NLP_ENRICH_TOKEN', ''),
    'batch_size' => min(25, max(1, (int) env('GCT_GPS_NLP_CHUNK_SIZE', 25))),
    'timeout' => min(15, max(2, (int) env('GCT_GPS_NLP_TIMEOUT', 8))),
];
