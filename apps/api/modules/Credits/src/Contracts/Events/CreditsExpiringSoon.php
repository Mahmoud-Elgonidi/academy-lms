<?php

namespace Modules\Credits\Contracts\Events;

use Carbon\CarbonImmutable;

/**
 * A purchased lot's minutes left lapse within CreditsApi::EXPIRY_NOTICE_DAYS
 * (raised once per lot and date by ExpireCredits::notice); Billing tells the
 * course's payer.
 */
final readonly class CreditsExpiringSoon
{
    public function __construct(
        public string $academyId,
        public string $accountId,
        public string $lotId,
        public int $minutes,
        public CarbonImmutable $expiresAt,
    ) {}
}
