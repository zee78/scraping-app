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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'envirofone' => [
        'key'    => env('ENVIROFONE_API_KEY'),
        'secret' => env('ENVIROFONE_API_SECRET'),
        'url'    => env('ENVIROFONE_FEED_URL'),
        'data_exerts_key' => env('ENVIROFONE_DATA_EXERTS_KEY'),
    ],

    'mobilesentrix' => [
        'email'    => env('MOBILESENTRIX_EMAIL'),
        'password' => env('MOBILESENTRIX_PASSWORD'),
    ],

    'utopya' => [
        'email'    => env('UTOPYA_EMAIL'),
        'password' => env('UTOPYA_PASSWORD'),
    ],
    
    'headlane' => [
        'api_key' => env('HEADLANE_API_KEY'),
        'api_url' => env('HEADLANE_API_URL'),
    ],
];
