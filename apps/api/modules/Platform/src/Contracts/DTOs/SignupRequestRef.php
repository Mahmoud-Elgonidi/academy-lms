<?php

namespace Modules\Platform\Contracts\DTOs;

/** An academy's request to join from the platform's page, as the console's New academy page fills in from it. */
final readonly class SignupRequestRef
{
    public function __construct(
        public string $id,
        public string $academyName,
        public string $contactName,
        public string $email,
        public ?string $phone,
        public ?string $country,
        public ?string $planCode,
        public string $locale,
        public string $status,
    ) {}
}
