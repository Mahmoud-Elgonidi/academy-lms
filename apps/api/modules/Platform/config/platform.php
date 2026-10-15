<?php

return [
    // Academies get {slug}.{domain}; custom domains are verified separately.
    'domain' => env('PLATFORM_DOMAIN', 'lms.test'),

    // Local development only: the academy for requests that name none, so
    // http://localhost:8000/admin works in browsers without *.localhost.
    // Ignored outside APP_ENV=local.
    'dev_academy' => env('PLATFORM_DEV_ACADEMY'),

    // One academy on the platform's own host ({domain}/admin, /teach, /my),
    // for a server whose academy subdomains have no DNS yet. Any environment;
    // only for requests that name no academy (no token, header or subdomain).
    'home_academy' => env('PLATFORM_HOME_ACADEMY'),

    // The platform team's console: its own host in production (console.{domain}),
    // APP_URL/console when this is empty.
    'console_url' => env('PLATFORM_CONSOLE_URL'),
    'console_domain' => env('PLATFORM_CONSOLE_DOMAIN'),

    // The apps: the oldest version still served (an older one asks to be
    // updated before anything else) and where to get the new one.
    'app' => [
        'min_version' => env('APP_MIN_VERSION', '1.0.0'),
        'android_url' => env('APP_ANDROID_URL'),
        'ios_url' => env('APP_IOS_URL'),
    ],

    // The stack's health (docs/architecture/03, section 12): the token an
    // uptime monitor sends to GET /health (empty: no such page), whether this
    // server has the backup part (then a missing backup or WAL archive is a
    // fault) and an off-site copy set up (the offsite part: a bucket and the
    // public key), and who besides the platform team hears when something breaks.
    'health' => [
        'token' => env('HEALTH_TOKEN'),
        'backups' => (bool) env('BACKUPS_EXPECTED', false),
        'offsite' => filled(env('OFFSITE_S3_BUCKET')) && filled(env('OFFSITE_AGE_RECIPIENT')),
        'alert_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('HEALTH_ALERT_TO', ''))))),
    ],

    // The games studio's designer (docs/architecture/02, section 18): Claude,
    // with the platform's own key (empty: the studio says the designer is not
    // set up). The server names the Claude model (GAME_DESIGNER_MODEL); it falls
    // back to its default model when the requested one declines a story.
    'games' => [
        'designer' => [
            // claude, or demo (one of the platform's own games back, no key; never in production)
            'driver' => env('GAME_DESIGNER', 'claude'),
            'key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL'),
            'model' => env('GAME_DESIGNER_MODEL'),
            'effort' => env('GAME_DESIGNER_EFFORT', 'high'),
            'max_tokens' => (int) env('GAME_DESIGNER_MAX_TOKENS', 64000),
            'timeout' => (int) env('GAME_DESIGNER_TIMEOUT', 900),
        ],
    ],

    // The academies' AI helpers (docs/architecture/02, "المساعد الآلي"): drafts
    // and aids a person reads first. Words come from Claude with the
    // platform's key (ANTHROPIC_API_KEY, as the studio); recordings are heard
    // by ElevenLabs (ELEVENLABS_API_KEY). Each academy turns each helper on
    // for itself (Settings › AI); a helper whose key is missing stays off.
    'ai' => [
        // claude, demo (fixed answers, no key; never in production), or off (every helper off)
        'driver' => env('AI_DRIVER', 'claude'),
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL'),
        // best: drafts that take judgement; fast: short wording at volume.
        'models' => [
            'best' => env('AI_MODEL', 'claude-opus-5-5'),
            'fast' => env('AI_FAST_MODEL', 'claude-haiku-5-5'),
        ],
        // The server's own fallback when a model declines (not every model has one).
        'fallbacks' => ['claude-opus-5-5', 'claude-sonnet-5-5'],
        // Seconds a request waits while someone waits on the screen, and in the background.
        'timeout' => (int) env('AI_TIMEOUT', 40),
        'job_timeout' => (int) env('AI_JOB_TIMEOUT', 180),
        // Requests to the providers at once from the web (the rest are told to try again).
        'concurrent' => (int) env('AI_CONCURRENT', 3),
        // Uses an academy may make in a day (on the server's clock).
        'daily_cap' => (int) env('AI_DAILY_CAP', 500),
        'speech' => [
            'key' => env('ELEVENLABS_API_KEY'),
            'base_url' => env('ELEVENLABS_API_BASE', 'https://api.elevenlabs.io'),
            'model' => env('ELEVENLABS_STT_MODEL', 'scribe_v2'),
            // Ask ElevenLabs to keep nothing (their zero-retention option, on contracts that include it).
            'zero_retention' => (bool) env('ELEVENLABS_ZERO_RETENTION', false),
            'timeout' => (int) env('ELEVENLABS_TIMEOUT', 25),
            // The longest recording sent, in seconds.
            'max_seconds' => (int) env('AI_MAX_AUDIO_SECONDS', 600),
        ],
    ],

    // Subdomains an academy may not take.
    'reserved_slugs' => ['www', 'api', 'app', 'admin', 'console', 'static', 'cdn', 'mail', 'status', 'docs'],
];
