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
            // Sign-in codes: an "authentication" template with a copy-code button, approved per language.
            'otp_template' => env('WHATSAPP_META_OTP_TEMPLATE', 'sign_in_code'),
        ],
    ],
    // Texts carry sign-in codes only (a phone without WhatsApp, or "send by SMS instead").
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),               // log | twilio | none
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),                   // a number or an alphanumeric sender ID
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        ],
    ],
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),              // log | fcm
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            'credentials' => env('FCM_CREDENTIALS'),        // path to the service account JSON
            // The same file's contents, as an owner uploads it in the console (Platform settings); wins over the path.
            'credentials_json' => null,
        ],
    ],
    'retry_after_seconds' => [60, 300, 900],
];
