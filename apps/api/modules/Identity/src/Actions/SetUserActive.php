<?php

namespace Modules\Identity\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Turns a person's account off (they can no longer sign in anywhere, and
 * their open sessions end) or on again. The academy's owner stays on,
 * nobody turns themselves off, and nobody signed in touches someone who
 * holds a back-office role above theirs (a teacher who is also an admin,
 * from People's screens; StaffRules' ranks). In the audit log.
 */
final class SetUserActive
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly IdentityApi $identity,
    ) {}

    public function handle(string $userId, bool $active): User
    {
        $user = User::query()->findOrFail($userId);
        if ($user->status === UserStatus::Erased) {
            throw ValidationException::withMessages(['status' => __('identity::users.erased')]);
        }
        if (! $active && ($user->hasRole(Role::Owner) || $user->id === auth()->id())) {
            throw ValidationException::withMessages(['status' => __('identity::users.cannot_disable')]);
        }
        $actor = auth()->user();
        if ($actor instanceof User && StaffRules::rankOf($user->roles()) > StaffRules::rankOf($actor->roles())) {
            throw ValidationException::withMessages(['status' => __('identity::users.above_you')]);
        }
        $status = $active ? UserStatus::Active : UserStatus::Disabled;
        if ($user->status === $status) {
            return $user;
        }

        $user->forceFill(['status' => $status])->save();
        if (! $active) {
            $this->identity->endSessions($user->id);
        }
        $this->audit->record($active ? 'identity.account_enabled' : 'identity.account_disabled', 'user', $user->id, ['name' => $user->name]);

        return $user;
    }
}
