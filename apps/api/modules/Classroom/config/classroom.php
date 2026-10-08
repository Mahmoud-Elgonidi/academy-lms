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
        // Recordings (Egress): the same server unless the recorder answers elsewhere.
        'egress_url' => env('LIVEKIT_EGRESS_URL'),
        'video' => ['width' => 640, 'height' => 360, 'framerate' => 15, 'video_bitrate' => 500, 'audio_bitrate' => 48],   // about 170 MB for 45 minutes
        'audio' => ['audio_bitrate' => 32],                                                                               // about 11 MB
    ],
    // Lesson recordings (docs/architecture/03, "التسجيلات"): the disk they go to (the private disk on this
    // server, or an S3/R2 disk whose keys the recorder uploads with), and, for a disk on this server, where the
    // recorder sees that disk's folder (it writes there; null: the same path as here).
    'recordings' => [
        'disk' => env('RECORDINGS_DISK', 'local'),
        'recorder_root' => env('RECORDINGS_RECORDER_ROOT'),
        'link_minutes' => 30,          // a watching link lasts the recording's length and this much more
    ],
    // The browser client, pinned. Loaded by the class page.
    'client_js' => 'https://cdn.jsdelivr.net/npm/livekit-client@2.22.3/dist/livekit-client.umd.js',
];
