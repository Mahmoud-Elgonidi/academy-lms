<?php

namespace Modules\Platform\Health;

use Modules\Platform\Ai\PlatformAcademyAi;

/**
 * The outside services this server is set to use (their keys in Platform
 * settings or the server's .env), read from the config in force each time:
 * the health page shows one check for each, the daily `platform:services`
 * tries them, and the settings page shows their trouble. A service's name is
 * the one its gateway writes to ServiceHealth.
 */
final class OutsideServices
{
    /** Each service and the Platform settings section that holds its keys (Anthropic's also serves the games designer: sections()). */
    public const GROUPS = [
        'anthropic' => 'ai',
        'openai' => 'ai',
        'gemini' => 'ai',
        'ollama' => 'ai',
        'elevenlabs' => 'speech',
        'whatsapp' => 'whatsapp',
        'twilio' => 'sms',
        'fcm' => 'push',
        'livekit' => 'classroom',
        'stripe' => 'stripe',
    ];

    /** @return list<string> in GROUPS' order */
    public static function configured(): array
    {
        return array_values(array_filter(array_keys(self::GROUPS), fn (string $service) => self::uses($service)));
    }

    public static function uses(string $service): bool
    {
        $driver = (string) config('platform.ai.driver');
        $ai = $driver !== 'off' && ! PlatformAcademyAi::demo();

        return match ($service) {
            'anthropic' => $ai && $driver === 'claude' && self::set('platform.ai.key')
                || config('platform.games.designer.driver') === 'claude' && self::set('platform.games.designer.key') && self::set('platform.games.designer.model'),
            'openai' => $ai && $driver === 'openai' && self::set('platform.ai.openai.key'),
            'gemini' => $ai && $driver === 'gemini' && self::set('platform.ai.gemini.key'),
            'ollama' => $ai && $driver === 'ollama' && self::set('platform.ai.ollama.url'),
            'elevenlabs' => $ai && self::set('platform.ai.speech.key'),
            'whatsapp' => config('communication.whatsapp.driver') === 'meta'
                && self::set('communication.whatsapp.meta.token') && self::set('communication.whatsapp.meta.phone_number_id'),
            'twilio' => config('communication.sms.driver') === 'twilio' && self::set('communication.sms.twilio.sid') && self::set('communication.sms.twilio.token'),
            'fcm' => config('communication.push.driver') === 'fcm'
                && (self::set('communication.push.fcm.credentials_json') || self::set('communication.push.fcm.credentials')),
            'livekit' => self::set('classroom.livekit.key') && self::set('classroom.livekit.secret') && self::set('classroom.livekit.api_url'),
            'stripe' => self::set('billing.payments.stripe.platform_secret'),
            default => false,
        };
    }

    public static function group(string $service): string
    {
        return self::GROUPS[$service] ?? 'ai';
    }

    /** @return list<string> the Platform settings sections whose try asks this service */
    public static function sections(string $service): array
    {
        return $service === 'anthropic' ? ['ai', 'designer'] : [self::group($service)];
    }

    private static function set(string $path): bool
    {
        return trim((string) config($path)) !== '';
    }
}
