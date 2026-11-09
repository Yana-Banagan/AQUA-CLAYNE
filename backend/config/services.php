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

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'anon_key' => env('SUPABASE_ANON_KEY'),
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    ],

    // Point this at an OSRM-compatible service that you operate or contract.
    'routing' => [
        'base_url' => env('ROUTING_SERVICE_URL', 'https://router.project-osrm.org'),
    ],

    // Aqua Clayne station origin. Override these values with the verified
    // station pin in each deployment; clients cannot supply an origin.
    'station' => [
        'name' => env('STATION_NAME', 'Aqua Clayne Water Refilling Station'),
        'address' => env('STATION_ADDRESS', 'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Sta Cruz Laguna'),
        'latitude' => (float) env('STATION_LATITUDE', 14.265111),
        'longitude' => (float) env('STATION_LONGITUDE', 121.405602),
        'bounds' => [
            'min_latitude' => 14.22,
            'max_latitude' => 14.34,
            'min_longitude' => 121.37,
            'max_longitude' => 121.47,
        ],
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'api_url' => env('PAYMONGO_API_URL', 'https://api.paymongo.com/v2'),
        'success_url' => env('PAYMONGO_SUCCESS_URL'),
        'cancel_url' => env('PAYMONGO_CANCEL_URL'),
        'payment_method_types' => array_filter(
            array_map(
                'trim',
                explode(',', env('PAYMONGO_PAYMENT_METHOD_TYPES', 'card,gcash,qrph'))
            )
        ),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000') . '/api/v1/auth/social/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL', 'http://localhost:8000') . '/api/v1/auth/social/facebook/callback'),
    ],

];
