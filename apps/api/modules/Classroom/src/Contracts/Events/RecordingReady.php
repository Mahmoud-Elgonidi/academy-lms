<?php

namespace Modules\Classroom\Contracts\Events;

/** A lesson's recording was made and can be watched (after commit). */
final readonly class RecordingReady
{
    public function __construct(
        public string $academyId,
        public string $recordingId,
        public string $sessionId,
        public string $media,
        public int $durationSec,
    ) {}
}
