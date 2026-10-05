<?php

namespace Modules\Identity;

use Modules\Identity\Actions\CreateUser;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Models\PersonalAccessToken;

final class IdentityService implements IdentityApi
{
    public function __construct(private readonly CreateUser $createUser) {}

    public function createUser(NewUser $user): string
    {
        return $this->createUser->handle($user)->id;
    }

    public function academyIdForToken(string $plainTextToken): ?string
    {
        $token = PersonalAccessToken::findToken($plainTextToken);

        if ($token === null || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return null;
        }

        return $token->academy_id;
    }
}
