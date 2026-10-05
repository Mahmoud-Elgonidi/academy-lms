<?php

namespace Modules\Identity\Contracts\DTOs;

use Modules\Identity\Enums\Role;

final readonly class NewUser
{
    /** @param  list<Role>  $roles */
    public function __construct(
        public string $name,
        public array $roles,
        public ?string $email = null,
        public ?string $phoneE164 = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $locale = null,
        public ?string $timezone = null,
        public bool $isManagedMinor = false,
    ) {}
}
