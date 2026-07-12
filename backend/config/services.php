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

    'egw' => [
        'client_id' => env('EGW_CLIENT_ID'),
        'client_secret' => env('EGW_CLIENT_SECRET'),
        'token_url' => 'https://cpanel.egwwritings.org/connect/token',
        'api_base' => 'https://a.egwwritings.org',
    ],

    'youversion' => [
        'app_key' => env('YOUVERSION_APP_KEY'),
        'api_base' => 'https://api.youversion.com/v1',
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'tts_model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
        'tts_timeout' => env('GEMINI_TTS_TIMEOUT', 240),
    ],

    'audio_narration' => [
        'ffmpeg_binary' => env('AUDIO_NARRATION_FFMPEG_BINARY', 'ffmpeg'),
    ],

    'revenuecat' => [
        'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET'),
    ],

    'stripe_institution_price_id_monthly' => env('STRIPE_INSTITUTION_PRICE_ID_MONTHLY'),
    'stripe_institution_price_id_annual' => env('STRIPE_INSTITUTION_PRICE_ID_ANNUAL'),
    'stripe_institution_min_seats' => env('STRIPE_INSTITUTION_MIN_SEATS', 10),

];
