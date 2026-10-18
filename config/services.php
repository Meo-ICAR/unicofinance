<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'aml' => [
        'api_key' => env('AML_API_KEY'),
        'base_url' => env('AML_API_URL', 'https://api.sanctions.io'),
        'min_score' => env('AML_MIN_SCORE', 0.88),
        'cost' => env('AML_COST_PER_CALL', 0),
    ],

    // Import di pratiche e compensi (comandi pratiche:import-api e provvigioni:import-api).
    'mediafacile' => [
        'base_url' => env('MEDIAFACILE_BASE_URL', 'https://races.mediafacile.it/ws/hassisto.php'),
        'header_key' => env('MEDIAFACILE_HEADER_KEY'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'cost' => (float) env('ANTHROPIC_CALL_COST', 0),
    ],

    'openapi' => [
        'cost_creditscore' => (float) env('OPENAPI_COST_CREDITSCORE', 0),
        'token' => env('OPENAPI_TOKEN'),
        'company_url' => env('OPENAPI_COMPANY_URL', 'https://company.openapi.com'),
        'cost_advanced' => env('OPENAPI_COST_ADVANCED', 0.10),
        'cost_shareholders' => env('OPENAPI_COST_SHAREHOLDERS', 0.03),
    ],

    'cerved' => [
        'api_key' => env('CERVED_API_KEY'),
        'url' => env('CERVED_URL'),
        'cost' => env('CERVED_COST_PER_CALL', 0),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // UnicoBPM: indirizzo per la verifica dei token SSO (/bpm-landing) e per le attività avviabili su un record.
    'bpm' => [
        'url' => env('BPM_API_URL', 'https://unicobpm.hassisto.com'),
    ],

];
