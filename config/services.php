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

    'google_analytics' => [
        // Op Railway: de sleutel als base64-tekst in een variabele.
        // Lokaal: het pad naar het sleutelbestand.
        'credentials' => env('GA_CREDENTIALS_BASE64')
            ? json_decode(base64_decode(env('GA_CREDENTIALS_BASE64')), true)
            : env('GA_CREDENTIALS_PATH'),
        'property_id' => env('GA_PROPERTY_ID'),
        'cache_minutes' => env('GA_CACHE_MINUTES', 180),
    ],

    'meta_ads' => [
    // true = nepdata, false = echte Meta-koppeling (komt later)
    'fake' => env('META_ADS_FAKE', true),
    'access_token' => env('META_ADS_ACCESS_TOKEN'),
    'api_version' => env('META_ADS_API_VERSION'),
    'cache_minutes' => env('META_ADS_CACHE_MINUTES', 180),
    // Welke Meta-acties tellen als conversie (voorlopig alleen leads, nog afstemmen met Stijn)
    'conversion_action_types' => ['lead'],
    ],

    'google_ads' => [
    // true = nepdata, false = echte Google Ads-koppeling (komt in stap 4)
    'fake' => env('GOOGLE_ADS_FAKE', true),
    'cache_minutes' => env('GOOGLE_ADS_CACHE_MINUTES', 180),
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
