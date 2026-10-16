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

    'ai' => [
        'coach_url' => env('AI_COACH_URL', 'http://127.0.0.1:8001'),
        'physio_url' => env('AI_PHYSIO_URL', 'http://127.0.0.1:8002'),
        'motion_url' => env('AI_MOTION_URL', 'http://127.0.0.1:8003'),
        'internal_token' => env('AI_INTERNAL_TOKEN'),
        // Graduation recording only. Default OFF — normal Coach is unaffected.
        'coach_demo_mode' => filter_var(env('COACH_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
        // Graduation physio demo layer. Default OFF — normal Physio AI is unaffected.
        'physio_demo_mode' => filter_var(env('PHYSIO_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
        // Graduation motion demo layer. Default OFF — normal Motion AI is unaffected.
        'motion_demo_mode' => filter_var(env('MOTION_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'intent_model' => env('OPENAI_INTENT_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini')),
        'timeout_ms' => (int) env('OPENAI_TIMEOUT_MS', 8000),
        'enabled' => filter_var(env('OPENAI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
