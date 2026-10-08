<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Contracts\DTOs\SignInRefusal;

/**
 * Whether a person may sign in now, beyond their password or code: a module
 * above Identity may hold someone back (People: a child whose guardian has
 * not agreed yet). Asked on every way in (the panels, every token the apps
 * get) and on every request to the family panel; the default lets everyone in.
 */
interface SignInGate
{
    /** Why this person may not sign in now, or null when they may. */
    public function refusal(string $userId): ?SignInRefusal;
}
