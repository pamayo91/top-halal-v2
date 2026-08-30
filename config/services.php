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

    'geoplateforme' => [
        'base_url' => env('GEOPLATEFORME_BASE_URL', 'https://data.geopf.fr/geocodage'),
        'timeout' => env('GEOPLATEFORME_TIMEOUT', 5),
        'rate_sleep_us' => env('GEOPLATEFORME_RATE_SLEEP_US', 250000),
        'user_agent' => env('GEOPLATEFORME_USER_AGENT', 'TopHalalV2-GeocodingPilot/1.0 contact@top-halal.fr'),
    ],

    // Optional: official API only. The default intentionally performs no web request.
    'restaurant_web' => [
        'provider' => env('RESTAURANT_WEB_PROVIDER'),
        'google_places_key' => env('GOOGLE_PLACES_API_KEY'),
        'timeout' => env('RESTAURANT_WEB_TIMEOUT', 8),
        'rate_sleep_us' => env('RESTAURANT_WEB_RATE_SLEEP_US', 500000),
    ],

];
