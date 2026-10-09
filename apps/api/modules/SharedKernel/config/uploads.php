<?php

return [
    // Files people upload (homework, receipts, attachments, lesson materials, imports, logos) are checked
    // for malware before they are kept: "none" (off) or "clamav" (a clamd the server runs: the "scan" part
    // in infra/deploy/compose.yml, COMPOSE_PROFILES=scan). RUNBOOKS.md, "Scanning uploads".
    'scanner' => env('UPLOAD_SCANNER', 'none'),
    'clamav' => [
        'host' => env('CLAMAV_HOST', 'clamav'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
    ],
    // With a scanner set and not answering, refuse uploads (true) or keep them unchecked (false).
    'required' => (bool) env('UPLOAD_SCAN_REQUIRED', true),
];
