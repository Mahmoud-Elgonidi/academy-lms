<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\SharedKernel\Authorization\Ability;

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

    /** @return list<string> active users of the current academy who hold the ability (who to tell about a proposal, say) */
    public function usersWithAbility(Ability $ability): array;

    /**
     * A person's own link, on the academy's host, to choose their first
     * password (an account made for them: an import, a new academy's owner).
     */
    public function welcomeLink(string $userId): string;
}
