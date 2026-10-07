<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use SensitiveParameter;

/**
 * The signed-in user sets a new password. Callers check the current one first.
 * The model's "hashed" cast hashes plain text and keeps an existing hash as is.
 * Never while the platform team is signed in as them.
 */
final class ChangePassword
{
    public function handle(User $user, #[SensitiveParameter] string $password): User
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['password' => __('identity::impersonation.errors.password')]);
        }
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
        app(AuditTrail::class)->record('identity.password_changed', 'user', $user->id, ['name' => $user->name]);

        return $user;
    }
}
