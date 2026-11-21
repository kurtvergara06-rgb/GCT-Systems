<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email'),
        'timeout' => (int) env('BREVO_TIMEOUT', 10),
        'connect_timeout' => (int) env('BREVO_CONNECT_TIMEOUT', 3),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Publish only after the signed or otherwise verified Windows installer is uploaded.
    // Use a direct HTTPS link to a .exe or .msi asset (for example, GitHub Releases).
    'desktop' => [
        'installer_url' => env('FROMS_DESKTOP_INSTALLER_URL', ''),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'nlp' => [
        'api_url' => env(
            'NLP_API_URL',
            'http://127.0.0.1:8000'
        ),
    ],

    'ai' => [
        // Safe by default: sample-backed models require an explicit opt-in.
        'allow_demo_models' => env('ALLOW_DEMO_ML', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delay-prediction (Model #3)
    |--------------------------------------------------------------------------
    |
    | The delay model is served by the same FastAPI engine as ETA (NLP_API_URL).
    | demo_trip_prefixes: trip codes treated as DEMO schedules and excluded from
    | the genuine training export (seeded demo rows use "TRIP-").
    |
    */

    // Genuine Inventory Model #4 forecasts are advisory and read-only.
    // Lead days are intentionally zero until Warehouse confirms an actual
    // replenishment period. No invented lead times or demo data in production.
    'inventory_ml' => [
        'enabled' => (bool) env('INVENTORY_ML_REORDER_ENABLED', true),
        'replenishment_lead_days' => (int) env('INVENTORY_REPLENISHMENT_LEAD_DAYS', 0),
        'safety_percent' => (int) env('INVENTORY_REORDER_SAFETY_PERCENT', 0),
        'max_batch_parts' => (int) env('INVENTORY_ML_MAX_BATCH_PARTS', 100),
    ],

    'delay' => [
        'demo_trip_prefixes' => explode(',', (string) env(
            'DELAY_DEMO_TRIP_PREFIXES',
            'TRIP-'
        )),
        'min_records' => (int) env('DELAY_MIN_RECORDS', 50),
        'min_routes' => (int) env('DELAY_MIN_ROUTES', 3),
        'min_buses' => (int) env('DELAY_MIN_BUSES', 5),
        'min_drivers' => (int) env('DELAY_MIN_DRIVERS', 5),
        'min_weeks' => (int) env('DELAY_MIN_WEEKS', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | OSRM
    |--------------------------------------------------------------------------
    */

    'osrm' => [
        'base_url' => env(
            'OSRM_BASE_URL',
            'https://router.project-osrm.org'
        ),

        'operational_speed_kph' => (float) env(
            'OSRM_OPERATIONAL_SPEED_KPH',
            25
        ),

        'stop_dwell_minutes' => (int) env(
            'OSRM_STOP_DWELL_MINUTES',
            1
        ),

        'fresh_cache_hours' => (int) env(
            'OSRM_FRESH_CACHE_HOURS',
            12
        ),

        'stale_cache_days' => (int) env(
            'OSRM_STALE_CACHE_DAYS',
            30
        ),
    ],

    'photon' => [
        'base_url' => env(
            'PHOTON_BASE_URL',
            'https://photon.komoot.io'
        ),
    ],

    'operation_ai' => [
        'base_url' => env(
            'OPERATION_AI_BASE_URL',
            'http://127.0.0.1:8000'
        ),

        'timeout' => (int) env(
            'OPERATION_AI_TIMEOUT',
            5
        ),
    ],

];
