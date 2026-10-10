<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\SharedKernel\Authorization\Ability;

/** What other modules and the app layer may ask of Identity. */
interface IdentityApi
{
    /** Creates a user in the current academy and returns its id. */
    public function createUser(NewUser $user): string;

    /** Staff change a person's name, ways in (audited), language or time zone. */
    public function updateUser(string $userId, UserDetails $details): void;

    /** A person's name as they or their guardian corrected it (the name alone; nothing else of the account changes). */
    public function rename(string $userId, string $name): void;

    /** Turns a person's account off (signed out everywhere) or on again; never the owner's or one's own. Audited. */
    public function setActive(string $userId, bool $active): void;

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

    /**
     * Signs a person out everywhere: every app's token and every web session
     * (a child whose guardian took their consent back).
     */
    public function endSessions(string $userId): void;
}
