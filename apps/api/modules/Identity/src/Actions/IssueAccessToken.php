<?php

namespace Modules\Identity\Actions;

use Carbon\CarbonImmutable;
use Modules\Identity\Models\User;

/** A Sanctum token for an app on one device (password, code, or a discovered account). */
final class IssueAccessToken
{
    /** @return array{user: User, token: string, expires_at: ?CarbonImmutable} */
    public function handle(User $user, string $deviceName): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->toImmutable()->addMinutes((int) $minutes) : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return ['user' => $user->load('roleRecords'), 'token' => $token->plainTextToken, 'expires_at' => $expiresAt];
    }
}
