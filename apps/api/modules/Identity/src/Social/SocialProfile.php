<?php

namespace Modules\Identity\Social;

/** What Google or Apple confirmed about the person signing in. */
final readonly class SocialProfile
{
    public function __construct(
        public Provider $provider,
        /** The provider's stable id for the person: what a link matches. */
        public string $subject,
        public ?string $email,
        /** The provider checked the email belongs to the person (Apple's relay addresses count). */
        public bool $emailVerified,
    ) {}

    /** The email to match an account by: only a verified one, lower case. */
    public function matchableEmail(): ?string
    {
        return $this->emailVerified && filled($this->email) ? mb_strtolower(trim((string) $this->email)) : null;
    }
}
