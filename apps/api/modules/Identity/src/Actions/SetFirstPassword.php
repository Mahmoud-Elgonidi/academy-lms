<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The first password of an account made for someone (an import, a lead):
 * set once, from their own welcome link. After that, only they change it,
 * from their settings. Never while the platform team is signed in as someone.
 */
final class SetFirstPassword
{
    public const MIN = 8;

    public function handle(User $user, string $password): User
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['password' => __('identity::impersonation.errors.password')]);
        }
        if (! $user->isActive() || $user->password !== null) {
            throw ValidationException::withMessages(['password' => __('identity::welcome.errors.already')]);
        }
        if (mb_strlen($password) < self::MIN) {
            throw ValidationException::withMessages(['password' => __('identity::welcome.errors.short', ['min' => self::MIN])]);
        }

        $user->forceFill(['password' => Hash::make($password)])->save();
        app(AuditTrail::class)->record('identity.password_set', 'user', $user->id, ['name' => $user->name]);

        return $user;
    }
}
