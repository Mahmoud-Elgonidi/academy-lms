<?php

namespace Modules\Identity\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Turns a person's account off (they can no longer sign in anywhere, and
 * their open sessions end) or on again. The academy's owner stays on, and
 * nobody turns themselves off. In the audit log.
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
        if (! $active && ($user->hasRole(Role::Owner) || $user->id === auth()->id())) {
            throw ValidationException::withMessages(['status' => __('identity::users.cannot_disable')]);
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
