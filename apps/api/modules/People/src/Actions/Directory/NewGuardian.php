<?php

namespace Modules\People\Actions\Directory;

use Modules\Identity\Contracts\DTOs\UserDetails;

/** Who looks after a student: someone already here, or someone new (with how to reach them). */
final readonly class NewGuardian
{
    public function __construct(
        public ?string $existingId = null,
        public ?UserDetails $details = null,
        public string $relationship = 'parent',
    ) {}
}
