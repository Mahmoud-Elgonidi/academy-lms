<?php

namespace Modules\Identity\Contracts\DTOs;

/** Why someone may not sign in: a problem type for the API and the words to show them. */
final readonly class SignInRefusal
{
    public function __construct(
        public string $type,
        public string $message,
    ) {}
}
