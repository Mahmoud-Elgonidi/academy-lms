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
            // Stripe Connect Standard: the platform's secret key and Connect client id (ca_…); an academy
            // connects its own Stripe account and the platform charges on it (direct charges).
            'platform_secret' => env('STRIPE_PLATFORM_SECRET'),
            'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
            'connect_base' => env('STRIPE_CONNECT_BASE', 'https://connect.stripe.com'),
            // The platform's Connect webhook (one for every connected account): its signing secret (whsec_…).
            'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
            // Where Stripe sends the academy back after connecting; register it in Stripe › Connect › Settings.
            'connect_redirect' => env('STRIPE_CONNECT_REDIRECT'),
            // The platform's fee on each payment through a connected account, in basis points (100 = 1%).
            'application_fee_bp' => (int) env('STRIPE_APPLICATION_FEE_BP', 0),
        ],
        'moyasar' => ['base' => env('MOYASAR_API_BASE', 'https://api.moyasar.com')],
        'tap' => ['base' => env('TAP_API_BASE', 'https://api.tap.company')],
        // Paymob Egypt; another country's Paymob has its own host (ksa.paymob.com, oman.paymob.com, …).
        'paymob' => ['base' => env('PAYMOB_API_BASE', 'https://accept.paymob.com')],
    ],
];
