<?php

namespace Modules\SharedKernel\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Eloquent's user provider, but signing in takes as long whether or not the
 * account exists: without one, a password is still checked against a hash of
 * nothing, so how long the answer takes says nothing about who has an account
 * (the message is already the same). The same for an account with no
 * password yet (made by the academy or an import, waiting for its welcome
 * link; a child under a guardian; one that signs in by code only), which
 * Eloquent would refuse without checking anything.
 */
final class TimingSafeUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $user = parent::retrieveByCredentials($credentials);
        if ($user === null && isset($credentials['password'])) {
            DummyPassword::check((string) $credentials['password']);
        }

        return $user;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        if (isset($credentials['password']) && (string) $user->getAuthPassword() === '') {
            return DummyPassword::check((string) $credentials['password']);
        }

        return parent::validateCredentials($user, $credentials);
    }
}
