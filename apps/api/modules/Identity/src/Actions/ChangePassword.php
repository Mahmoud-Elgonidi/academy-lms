<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Str;
use Modules\Identity\Models\User;
use SensitiveParameter;

/**
 * The signed-in user sets a new password. Callers check the current one first.
 * The model's "hashed" cast hashes plain text and keeps an existing hash as is.
 */
final class ChangePassword
{
    public function handle(User $user, #[SensitiveParameter] string $password): User
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        return $user;
    }
}
