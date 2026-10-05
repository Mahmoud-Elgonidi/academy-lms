<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Contracts\DTOs\NewUser;

/** What other modules and the app layer may ask of Identity. */
interface IdentityApi
{
    /** Creates a user in the current academy and returns its id. */
    public function createUser(NewUser $user): string;

    /**
     * The academy a bearer token was issued for, or null if the token is
     * unknown, expired, or does not match.
     */
    public function academyIdForToken(string $plainTextToken): ?string;
}
