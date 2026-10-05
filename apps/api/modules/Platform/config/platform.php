<?php

return [
    // Academies get {slug}.{domain}; custom domains are verified separately.
    'domain' => env('PLATFORM_DOMAIN', 'lms.test'),

    // Local development only: the academy for requests that name none, so
    // http://localhost:8000/admin works in browsers without *.localhost.
    // Ignored outside APP_ENV=local.
    'dev_academy' => env('PLATFORM_DEV_ACADEMY'),

    // Subdomains an academy may not take.
    'reserved_slugs' => ['www', 'api', 'app', 'admin', 'console', 'static', 'cdn', 'mail', 'status', 'docs'],
];
