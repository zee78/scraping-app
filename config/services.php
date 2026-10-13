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

    'partsbox' => [
        'base_url'     => env('PARTSBOX_BASE_URL', 'https://www.partsbox.co.uk'),
        'login_path'   => env('PARTSBOX_LOGIN_PATH', '/portal/login'),
        // Set this to the catalogue landing page after the first successful login if it differs.
        'catalogue_path' => env('PARTSBOX_CATALOGUE_PATH', '/portal/inventory'),
        'email'        => env('PARTSBOX_EMAIL'),
        'password'     => env('PARTSBOX_PASSWORD'),
        'request_delay_ms' => (int) env('PARTSBOX_REQUEST_DELAY_MS', 350),
        'rate_limit_retries' => (int) env('PARTSBOX_RATE_LIMIT_RETRIES', 3),
        // PartsBox currently accepts a normal Chrome session but rate-limits cURL.
        'browser_mode' => filter_var(env('PARTSBOX_BROWSER_MODE', true), FILTER_VALIDATE_BOOL),
        'chrome_path' => env('PARTSBOX_CHROME_PATH'),
    ],

    'ozy_toolbox' => [
        'base_url' => env('OZY_TOOLBOX_BASE_URL', 'https://www.ozytoolbox.com.au'),
        'request_delay_ms' => (int) env('OZY_TOOLBOX_REQUEST_DELAY_MS', 500),
        // Ozy is protected by Cloudflare; browser mode is the normal setting.
        'browser_mode' => filter_var(env('OZY_TOOLBOX_BROWSER_MODE', true), FILTER_VALIDATE_BOOL),
        'chrome_path' => env('OZY_TOOLBOX_CHROME_PATH'),
    ],
];
