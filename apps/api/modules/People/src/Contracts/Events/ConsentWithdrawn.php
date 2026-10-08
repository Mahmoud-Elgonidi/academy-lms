<?php

namespace Modules\People\Contracts\Events;

/** A guardian took their consent back: the child is signed out everywhere and waits again. */
final readonly class ConsentWithdrawn
{
    public function __construct(
        public string $academyId,
        public string $studentId,
        public string $consentId,
        public string $byUserId,
    ) {}
}
