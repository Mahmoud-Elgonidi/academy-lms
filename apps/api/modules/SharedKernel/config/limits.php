<?php

return [
    // The apps' API per person a minute (the named "api" limit; before signing in, per address). A person
    // opening pages never comes near it; the end-to-end run, which drives one account at machine speed
    // through every page twice, raises it in its own environment (.github/workflows/web.yml).
    'api_per_minute' => (int) env('API_PER_MINUTE', 600),
    'api_guest_per_minute' => (int) env('API_GUEST_PER_MINUTE', 240),
];
