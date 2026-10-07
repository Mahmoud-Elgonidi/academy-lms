<?php

return [
    // Academies get {slug}.{domain}; custom domains are verified separately.
    'domain' => env('PLATFORM_DOMAIN', 'lms.test'),

    // Local development only: the academy for requests that name none, so
    // http://localhost:8000/admin works in browsers without *.localhost.
    // Ignored outside APP_ENV=local.
    'dev_academy' => env('PLATFORM_DEV_ACADEMY'),

    // The platform team's console: its own host in production (console.{domain}),
    // APP_URL/console when this is empty.
    'console_url' => env('PLATFORM_CONSOLE_URL'),
    'console_domain' => env('PLATFORM_CONSOLE_DOMAIN'),

    // The apps: the oldest version still served (an older one asks to be
    // updated before anything else) and where to get the new one.
    'app' => [
        'min_version' => env('APP_MIN_VERSION', '1.0.0'),
        'android_url' => env('APP_ANDROID_URL'),
        'ios_url' => env('APP_IOS_URL'),
    ],

    // Subdomains an academy may not take.
    'reserved_slugs' => ['www', 'api', 'app', 'admin', 'console', 'static', 'cdn', 'mail', 'status', 'docs'],
];
