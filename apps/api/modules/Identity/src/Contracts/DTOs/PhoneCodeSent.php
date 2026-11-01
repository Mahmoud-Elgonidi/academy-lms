<?php

namespace Modules\Identity\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A code on its way to a mobile number, as the visitor is told: the same
 * whether or not the number has an account here.
 *
 * @param  list<string>  $channels  every channel a new code could go by
 */
final readonly class PhoneCodeSent
{
    /** @param  list<string>  $channels */
    public function __construct(
        public string $challengeId,
        public string $channel,
        public string $sentTo,
        public CarbonImmutable $expiresAt,
        public int $resendAfter,
        public array $channels,
    ) {}
}
