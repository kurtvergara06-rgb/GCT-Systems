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