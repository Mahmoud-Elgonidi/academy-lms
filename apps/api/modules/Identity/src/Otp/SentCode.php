<?php

namespace Modules\Identity\Otp;

use Carbon\CarbonImmutable;

/**
 * What the person is told after asking for a code: the same whether or not
 * the contact has an account (nothing about membership leaks before the code).
 */
final readonly class SentCode
{
    /** @param  list<string>  $channels  every channel a new code could go by */
    public function __construct(
        public string $challengeId,
        public string $channel,
        public string $sentTo,
        public CarbonImmutable $expiresAt,
        public int $resendAfter,
        public array $channels,
    ) {}
}
