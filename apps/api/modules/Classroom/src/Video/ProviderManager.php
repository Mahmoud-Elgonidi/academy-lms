<?php

namespace Modules\Classroom\Video;

use InvalidArgumentException;
use Modules\Classroom\Video\LiveKit\LiveKitProvider;

/**
 * Picks the academy's provider (docs/architecture/04, "Adapter + Manager").
 * LiveKit only where this server has it: a key and a secret of its own. The
 * development secret ships with the code (docker compose, .env.example), so
 * anyone could sign tokens and webhooks with it: on a real server it counts
 * as no LiveKit, and lessons go by each teacher's outside link.
 */
final class ProviderManager
{
    /** What config/classroom.php falls back to; fine on a laptop, never on a real server. */
    public const LIVEKIT_DEV_SECRET = 'local-dev-secret-not-for-production-0001';

    public function get(string $provider): VideoProvider
    {
        return match ($provider) {
            'livekit' => new LiveKitProvider(config('classroom.livekit')),
            'external' => new ExternalLinkProvider,
            default => throw new InvalidArgumentException("Unknown video provider [{$provider}]."),
        };
    }

    /** The provider's recorder, when it can record (an outside link cannot). */
    public function recorder(string $provider): ?Recorder
    {
        $video = $this->get($provider);

        return $video instanceof Recorder ? $video : null;
    }

    /** Whether this server has LiveKit set up (its webhooks are taken, academies may choose it). */
    public function liveKitReady(): bool
    {
        $key = (string) config('classroom.livekit.key');
        $secret = (string) config('classroom.livekit.secret');

        return $key !== '' && $secret !== '' && ($secret !== self::LIVEKIT_DEV_SECRET || app()->environment('local', 'testing'));
    }

    /** @return list<string> the providers an academy may choose on this server */
    public function available(): array
    {
        return $this->liveKitReady() ? ['livekit', 'external'] : ['external'];
    }

    /** The provider lessons use: the academy's choice when this server has it, else the teacher's outside link. */
    public function effective(?string $chosen): string
    {
        return in_array($chosen, $this->available(), true) ? $chosen : 'external';
    }
}
