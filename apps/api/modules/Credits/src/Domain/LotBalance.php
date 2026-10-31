<?php

namespace Modules\Credits\Domain;

use Carbon\CarbonImmutable;

/** What the allocator needs to know about a lot. */
final readonly class LotBalance
{
    public function __construct(
        public string $id,
        public string $source,
        public int $remainingMin,
        public ?CarbonImmutable $expiresAt,
        public CarbonImmutable $createdAt,
        public ?string $useFor = null,          // a makeup lot kept for private or group makeup lessons; null: either
    ) {}
}
