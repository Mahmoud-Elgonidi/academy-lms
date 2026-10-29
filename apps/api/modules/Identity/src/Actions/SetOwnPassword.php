<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use SensitiveParameter;

/**
 * The signed-in person chooses the first password of their own account
 * (Filament\Pages\ChoosePassword): someone who came in by a one-time code
 * or a sign-in link before ever choosing one. Two-step sign-in is set up
 * against a password, so whoever must set it up and has none comes here
 * first. The same rules as SetFirstPassword: only an account with no
 * password, never while the platform team is signed in as them. Audited.
 */
final class SetOwnPassword
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(User $user, #[SensitiveParameter] string $password): User
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['password' => __('identity::impersonation.errors.password')]);
        }
        $user->refresh();
        if (! $user->isActive() || $user->password !== null) {
            throw ValidationException::withMessages(['password' => __('identity::security.password.errors.has_one')]);
        }
        if (mb_strlen($password) < SetFirstPassword::MIN) {
            throw ValidationException::withMessages(['password' => __('identity::welcome.errors.short', ['min' => SetFirstPassword::MIN])]);
        }

        $user->forceFill(['password' => Hash::make($password)])->save();
        $this->audit->record('identity.password_chosen', 'user', $user->id, ['name' => $user->name]);

        return $user;
    }
}
