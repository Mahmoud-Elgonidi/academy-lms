<?php

namespace Modules\People\Contracts\Events;

/** A guardian agreed to a child's account (or the office recorded it): the child may sign in. */
final readonly class ConsentGranted
{
    public function __construct(
        public string $academyId,
        public string $studentId,
        public string $consentId,
        public string $via,
    ) {}
}
