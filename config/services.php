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

];
