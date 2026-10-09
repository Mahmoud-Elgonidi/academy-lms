<?php

return [
    // Academies get {slug}.{domain}; custom domains are verified separately.
    'domain' => env('PLATFORM_DOMAIN', 'lms.test'),

    // Local development only: the academy for requests that name none, so
    // http://localhost:8000/admin works in browsers without *.localhost.
    // Ignored outside APP_ENV=local.
    'dev_academy' => env('PLATFORM_DEV_ACADEMY'),

    // One academy on the platform's own host ({domain}/admin, /teach, /my),
    // for a server whose academy subdomains have no DNS yet. Any environment;
    // only for requests that name no academy (no token, header or subdomain).
    'home_academy' => env('PLATFORM_HOME_ACADEMY'),

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

    // The stack's health (docs/architecture/03, section 12): the token an
    // uptime monitor sends to GET /health (empty: no such page), whether this
    // server has the backup part (then a missing backup or WAL archive is a
    // fault), and who besides the platform team hears when something breaks.
    'health' => [
        'token' => env('HEALTH_TOKEN'),
        'backups' => (bool) env('BACKUPS_EXPECTED', false),
        'alert_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('HEALTH_ALERT_TO', ''))))),
    ],

    // Subdomains an academy may not take.
    'reserved_slugs' => ['www', 'api', 'app', 'admin', 'console', 'static', 'cdn', 'mail', 'status', 'docs'],
];
