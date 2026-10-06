<?php

return [
    'payments' => [
        // How long a gateway's page stays open for one go at paying (Stripe takes 30 minutes to 24 hours).
        'checkout_minutes' => (int) env('BILLING_CHECKOUT_MINUTES', 60),

        // How long the pay link in an invoice message works.
        'link_days' => (int) env('BILLING_PAY_LINK_DAYS', 60),

        // A pretend gateway with its own page, to try the whole flow without keys. Never in production.
        'demo_gateway' => env('APP_ENV', 'production') !== 'production' && (bool) env('BILLING_DEMO_GATEWAY', true),

        'timeout' => 15,

        'stripe' => [
            'base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
            // Stripe Connect (later): the platform's key, with each academy's connected account id.
            'platform_secret' => env('STRIPE_PLATFORM_SECRET'),
        ],
        'moyasar' => ['base' => env('MOYASAR_API_BASE', 'https://api.moyasar.com')],
        'tap' => ['base' => env('TAP_API_BASE', 'https://api.tap.company')],
    ],
];
