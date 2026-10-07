<?php

/*
 * Sign-in by one-time code (docs/architecture/03-technical-architecture.md,
 * "الهوية"): a code by WhatsApp, SMS or email, in an academy or to find one's
 * accounts across academies. Sending is Communication's (it binds OtpSender).
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
];
