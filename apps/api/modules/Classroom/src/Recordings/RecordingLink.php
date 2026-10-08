<?php

namespace Modules\Classroom\Recordings;

use Carbon\CarbonImmutable;

/** An address to watch one recording, signed for whoever opened it, until it expires. */
final readonly class RecordingLink
{
    public function __construct(
        public string $url,
        public CarbonImmutable $expiresAt,
    ) {}
}
