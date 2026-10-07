<?php

namespace Modules\Learning\Contracts\Events;

/** A guardian cheered a student on; the student is told. */
final readonly class CheerSent
{
    public function __construct(
        public string $academyId,
        public string $cheerId,
        public string $studentId,
        public string $fromId,
        public string $kind,
    ) {}
}
