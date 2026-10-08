<?php

/*
 * Sign-in by one-time code (docs/architecture/03-technical-architecture.md,
 * "الهوية"): a code by WhatsApp, SMS or email, in an academy or to find one's
 * accounts across academies. Sending is Communication's (it binds OtpSender).
 *
 * Sign-in with Google or Apple: the platform's own client for each, one
 * callback address on the platform (redirect_base) for every academy. A
 * provider is on for the web when it has a web client, for the apps when it
 * has app audiences (the apps' client or bundle ids). ID tokens are checked
 * against the provider's published keys (no SDK); the endpoints can point
 * elsewhere for a local check.
 */
return [
    'otp' => [
        'digits' => 6,
        'minutes' => 10,                 // a code works this long
        'max_attempts' => 5,             // wrong codes before the challenge dies
        'resend_seconds' => 60,          // between two codes to the same contact
        'per_contact_per_hour' => 5,
        'per_ip_per_hour' => 20,
        'phone_channels' => ['whatsapp', 'sms'],   // in order; the first is the default
        'discovery_minutes' => 15,       // a verified contact may add its accounts this long
    ],

    'social' => [
        // Where Google and Apple send people back (the platform's own address, registered with both); empty: this host.
        'redirect_base' => env('SOCIAL_REDIRECT_BASE'),
        'state_minutes' => 10,           // from leaving for the provider to coming back
        'ticket_seconds' => 120,         // from the platform's callback to the academy's own host
        'leeway_seconds' => 60,          // clock difference allowed on a token's times
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'app_audiences' => env('GOOGLE_APP_CLIENT_IDS'),   // comma-separated: the Android, iOS (and web) clients the app signs in with
            'authorize_url' => env('GOOGLE_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'token_url' => env('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
            'jwks_url' => env('GOOGLE_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs'),
            'issuers' => env('GOOGLE_ISSUERS', 'https://accounts.google.com,accounts.google.com'),
        ],
        'apple' => [
            'client_id' => env('APPLE_SERVICES_ID'),          // the Services ID used on the web
            'app_audiences' => env('APPLE_APP_IDS'),          // comma-separated bundle ids
            'authorize_url' => env('APPLE_AUTHORIZE_URL', 'https://appleid.apple.com/auth/authorize'),
            'jwks_url' => env('APPLE_JWKS_URL', 'https://appleid.apple.com/auth/keys'),
            'issuers' => env('APPLE_ISSUERS', 'https://appleid.apple.com'),
        ],
    ],
];
