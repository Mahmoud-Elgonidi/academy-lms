<?php

namespace Modules\Identity\Contracts\Events;

/**
 * A way in (the email or the mobile number) of an account changed, by the
 * person or by the academy. Raised after commit; carries which one, never the
 * addresses.
 */
final readonly class ContactChanged
{
    public function __construct(
        public string $userId,
        public string $kind,      // email | phone
        public bool $bySelf,
    ) {}
}
