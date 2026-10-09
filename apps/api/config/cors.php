<?php

/*
 * Who may call the API from a web page on another origin. Nothing in
 * production needs to: the portal calls the API from its server, the apps
 * are not browsers, and every page the API serves is on the academy's own
 * host. So no other origin is allowed unless CORS_ALLOWED_ORIGINS names one
 * (comma-separated); in local development, any (the app's web build).
 */
$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_origins' => $origins !== [] ? $origins : (env('APP_ENV') === 'local' ? ['*'] : []),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Academy', 'X-Timezone', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
