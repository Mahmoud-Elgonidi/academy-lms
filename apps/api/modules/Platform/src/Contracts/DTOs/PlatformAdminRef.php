<?php

namespace Modules\Platform\Contracts\DTOs;

/** Someone of the platform team, as other modules see them. */
final readonly class PlatformAdminRef
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
}
