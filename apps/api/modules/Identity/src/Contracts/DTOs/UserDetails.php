<?php

namespace Modules\Identity\Contracts\DTOs;

/** Who a person is and how to reach them, as the academy's staff keep it. */
final readonly class UserDetails
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phoneE164 = null,
        public ?string $locale = null,
        public ?string $timezone = null,
    ) {}
}
