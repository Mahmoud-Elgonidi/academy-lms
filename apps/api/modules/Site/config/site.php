<?php

// The academy's page builder (docs/architecture/site-builder.md).
return [
    // Where the builder's preview opens the portal: empty on servers (the academy's own
    // host, where /admin and /ar share an origin); http://localhost:3101 on a laptop.
    'preview_url' => env('SITE_PREVIEW_URL', ''),

    // The page's files as whole addresses (APP_URL/storage/…) instead of paths on the
    // academy's host: only on a laptop, where the portal and the files are on different ports.
    'media_absolute' => (bool) env('SITE_MEDIA_ABSOLUTE', false),
];
