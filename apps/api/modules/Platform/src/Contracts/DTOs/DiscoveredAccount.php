<?php

namespace Modules\Platform\Contracts\DTOs;

/** One account a verified contact has: the academy as the app shows it, and who the person is there. */
final readonly class DiscoveredAccount
{
    /** @param  list<string>  $roles */
    public function __construct(
        public string $academyId,
        public string $academySlug,
        public string $academyNameAr,
        public string $academyNameEn,
        public ?string $logoUrl,
        public ?string $colour,
        public string $userId,
        public string $userName,
        public array $roles,
    ) {}
}
