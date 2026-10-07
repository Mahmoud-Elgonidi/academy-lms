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

    // Subdomains an academy may not take.
    'reserved_slugs' => ['www', 'api', 'app', 'admin', 'console', 'static', 'cdn', 'mail', 'status', 'docs'],
];
