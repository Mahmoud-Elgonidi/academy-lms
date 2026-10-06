<?php

/*
 * The live classroom (docs/architecture/03-technical-architecture.md,
 * section 6). LiveKit by default; an outside meeting link (Zoom, Meet) per
 * teacher as the fallback an academy can switch to.
 */
return [
    'provider' => env('CLASSROOM_PROVIDER', 'livekit'),   // livekit | external
    'opens_before_min' => 10,                            // the classroom opens 10 minutes before the lesson
    'closes_after_min' => 10,                            // and stays open 10 minutes after its end
    'livekit' => [
        'url' => env('LIVEKIT_URL', 'ws://localhost:7880'),          // what browsers and apps connect to
        'api_url' => env('LIVEKIT_API_URL', 'http://localhost:7880'), // what the server calls (room API)
        'key' => env('LIVEKIT_API_KEY', 'devkey'),
        'secret' => env('LIVEKIT_API_SECRET', 'local-dev-secret-not-for-production-0001'),
        'max_participants' => 4,                                     // teacher, student, a supervisor, one spare
    ],
    // The browser client, pinned. Loaded by the class page.
    'client_js' => 'https://cdn.jsdelivr.net/npm/livekit-client@2.22.3/dist/livekit-client.umd.js',
];
