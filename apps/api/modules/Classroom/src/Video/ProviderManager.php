<?php

namespace Modules\Classroom\Video;

use InvalidArgumentException;
use Modules\Classroom\Video\LiveKit\LiveKitProvider;

/** Picks the academy's provider (docs/architecture/04, "Adapter + Manager"). */
final class ProviderManager
{
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
}
