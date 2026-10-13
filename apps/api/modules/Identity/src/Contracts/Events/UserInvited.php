<?php

namespace Modules\Identity\Contracts\Events;

/**
 * The academy made someone an account (a new member of its team) and they
 * should get the welcome with their own link to choose a password; raised
 * after commit, and again ($again) when the inviter sends it once more.
 * Identity sends no messages: People's welcome does (SendWelcome).
 */
final readonly class UserInvited
{
    public function __construct(
        public string $userId,
        public bool $again = false,
    ) {}
}
