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

    'stripe' => [
        'key' => env('STRIPE_KEY'), // publishable key (pk_...)
        'secret' => env('STRIPE_SECRET'), // secret key (sk_...)
    ],

    /*
     * 11Done — fulfils certificate renewals. After a landlord pays, the booking is created in
     * 11Done (as a normal, paid 11Done booking); status and the new certificate come back.
     * Leave the URL/key empty to run without 11Done (bookings then stay in Letting World only).
     */
    'elevendone' => [
        'url' => rtrim((string) env('ELEVENDONE_URL'), '/'),
        'api_key' => env('ELEVENDONE_API_KEY'),
        'webhook_secret' => env('ELEVENDONE_WEBHOOK_SECRET'),
        'timeout' => 10,
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/api/auth/google/callback',
        // App deep links allowed to receive the login token. exp:// is Expo Go (development only).
        'app_redirect_prefixes' => array_map('trim', explode(',', env('GOOGLE_APP_REDIRECT_PREFIXES', 'lettingworld://'))),
    ],

];
