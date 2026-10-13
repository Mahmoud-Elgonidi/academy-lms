<?php

namespace Modules\Identity\Actions\Staff;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Identity\Staff\TeamGuard;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Turns a team member's account off: they cannot sign in anywhere, every
 * app token and web session of theirs ends, and a "remember me" cookie
 * stops working. Only someone ranked at least as high, never oneself, never
 * the last active owner; someone who also teaches or is a family member is
 * turned off from People's screens instead. Nothing they did is lost. In
 * the audit log, with their roles.
 */
final class DeactivateStaff
{
    public function __construct(
        private readonly TeamGuard $team,
        private readonly IdentityApi $identity,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $userId, string $actorId): User
    {
        return DB::transaction(function () use ($userId, $actorId) {
            $actor = $this->team->actor($actorId);
            $user = $this->team->member($userId);
            TeamGuard::refuse(StaffRules::whyNotDeactivate($actor->roles(), $actor->id === $user->id, $user->roles(), $this->team->otherActiveOwners($user->id)), 'status');
            if (! $user->isActive()) {
                return $user;
            }

            $user->forceFill(['status' => UserStatus::Disabled, 'remember_token' => Str::random(60)])->save();
            $this->identity->endSessions($user->id);
            $this->audit->record('identity.staff_deactivated', 'user', $user->id, ['name' => $user->name, 'roles' => TeamGuard::names(StaffRules::staffOf($user->roles()))]);

            return $user;
        });
    }
}
