<?php

// The server's .env (infra/deploy/deploy.sh, settings(); README.md, "Settings"): every setting the
// modules read is written there, or has a default fit for a server, so a new one cannot be
// forgotten; and what a server has not set up yet is off, never a laptop's default.

/** Settings the modules read whose defaults suit a server: outside services' addresses, tuning. */
const SERVER_DEFAULTS = [
    'AI_CONCURRENT', 'AI_JOB_TIMEOUT', 'AI_MAX_AUDIO_SECONDS', 'AI_TIMEOUT', 'ANTHROPIC_BASE_URL', 'APPLE_AUTHORIZE_URL', 'APPLE_ISSUERS', 'APPLE_JWKS_URL', 'BILLING_CHECKOUT_MINUTES',
    'BILLING_PAY_LINK_DAYS', 'CLAMAV_PORT', 'CLAMAV_TIMEOUT', 'ELEVENLABS_STT_MODEL', 'ELEVENLABS_TIMEOUT', 'GAME_DESIGNER', 'GAME_DESIGNER_EFFORT',
    'GAME_DESIGNER_MAX_TOKENS', 'GAME_DESIGNER_TIMEOUT', 'GOOGLE_AUTHORIZE_URL', 'GOOGLE_ISSUERS', 'GOOGLE_JWKS_URL',
    'GOOGLE_TOKEN_URL', 'MOYASAR_API_BASE', 'OPENAI_BASE_URL', 'GEMINI_BASE_URL', 'PAYMOB_API_BASE', 'STRIPE_API_BASE', 'STRIPE_CONNECT_BASE',
    'STRIPE_CONNECT_REDIRECT', 'TAP_API_BASE', 'WHATSAPP_META_API_VERSION', 'QURAN_TEXT_URL', 'QURAN_DATA_URL',
    // A laptop's own academy; and the push key's path, which compose.yml sets inside the worker.
    'PLATFORM_DEV_ACADEMY', 'FCM_CREDENTIALS',
];

/** @return array<string, string> the settings deploy.sh writes, KEY => value as written (secrets as their shell) */
function serverSettings(): array
{
    $script = (string) file_get_contents(dirname(__DIR__, 5).'/infra/deploy/deploy.sh');
    preg_match('/^settings\(\) \{\n(.*?)\n\}$/ms', $script, $block);
    preg_match_all('/^([A-Z][A-Z0-9_]*)=(.*)$/m', $block[1] ?? '', $lines, PREG_SET_ORDER);

    return array_column($lines, 2, 1);
}

it('writes every setting the modules read, unless its default suits a server', function () {
    $read = [];
    foreach (glob(dirname(__DIR__, 3).'/modules/*/config/*.php') as $file) {
        preg_match_all("/env\\('([A-Z0-9_]+)'/", (string) file_get_contents($file), $m);
        $read = [...$read, ...$m[1]];
    }
    $settings = serverSettings();

    expect(count($settings))->toBeGreaterThan(100)
        ->and(array_values(array_diff(array_unique($read), array_keys($settings), SERVER_DEFAULTS)))->toBe([]);
});

it('keeps what a server has not set up off, and never takes a laptop default', function () {
    $s = serverSettings();

    expect([$s['APP_ENV'], $s['APP_DEBUG'], $s['CLASSROOM_PROVIDER']])->toBe(['production', 'false', 'external'])
        // Blank, not the development key and secret the code falls back to (anyone could sign LiveKit's webhooks with those).
        ->and([$s['LIVEKIT_API_KEY'], $s['LIVEKIT_API_SECRET']])->toBe(['', ''])
        ->and([$s['WHATSAPP_DRIVER'], $s['SMS_DRIVER'], $s['PUSH_DRIVER'], $s['MAIL_MAILER']])->toBe(['log', 'log', 'log', 'log'])
        ->and($s['FCM_CREDENTIALS_FILE'])->toBe('./secrets/fcm.json')
        ->and($s['CORS_ALLOWED_ORIGINS'])->toBe('')
        ->and($s['UPLOAD_SCANNER'])->toBe('none')
        // The helpers need the platform's keys; each academy still turns each one on itself.
        ->and([$s['ANTHROPIC_API_KEY'], $s['ELEVENLABS_API_KEY'], $s['ELEVENLABS_ZERO_RETENTION']])->toBe(['', '', 'false'])
        ->and([$s['OPENAI_API_KEY'], $s['GEMINI_API_KEY'], $s['OLLAMA_URL'], $s['OLLAMA_API_KEY']])->toBe(['', '', '', '']);
});
