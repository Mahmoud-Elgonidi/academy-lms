<?php

/*
 * Outside channels. Locally everything is logged (and email goes to Mailpit
 * under docker compose); real WhatsApp and push need the platform's keys.
 * Per-academy WhatsApp numbers come with P2.
 */
return [
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),          // log | meta
        'meta' => [
            'token' => env('WHATSAPP_META_TOKEN'),
            'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
            'api_version' => env('WHATSAPP_META_API_VERSION', 'v21.0'),
            // Business-initiated messages must use templates Meta approved; this one carries
            // the title and the body as its two parameters, in the recipient's language.
            'template' => env('WHATSAPP_META_TEMPLATE', 'academy_notification'),
        ],
    ],
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),              // log | fcm
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            'credentials' => env('FCM_CREDENTIALS'),        // path to the service account JSON
        ],
    ],
    'retry_after_seconds' => [60, 300, 900],
];
