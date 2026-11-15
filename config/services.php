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
    | Geoapify
    |--------------------------------------------------------------------------
    */

    'geoapify' => [
        'key' => env('GEOAPIFY_API_KEY'),
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