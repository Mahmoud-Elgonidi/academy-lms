<?php

namespace Modules\Credits\Contracts\Events;

/** New minutes on an account; Scheduling funds unpaid sessions from them, oldest first. */
final readonly class CreditsGranted
{
    public function __construct(
        public string $academyId,
        public string $accountId,
        public string $lotId,
        public string $source,
        public int $minutes,
    ) {}
}
