<?php

namespace Modules\Identity\Actions\Staff;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Identity\Staff\TeamGuard;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Lets a team member whose account was turned off sign in again, with the
 * roles they had. Only someone ranked at least as high, never oneself. In
 * the audit log, with their roles.
 */
final class ReactivateStaff
{
    public function __construct(
        private readonly TeamGuard $team,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $userId, string $actorId): User
    {
        return DB::transaction(function () use ($userId, $actorId) {
            $actor = $this->team->actor($actorId);
            $user = $this->team->member($userId);
            TeamGuard::refuse(StaffRules::whyNotReactivate($actor->roles(), $actor->id === $user->id, $user->roles()), 'status');
            if ($user->isActive()) {
                return $user;
            }

            $user->forceFill(['status' => UserStatus::Active])->save();
            $this->audit->record('identity.staff_reactivated', 'user', $user->id, ['name' => $user->name, 'roles' => TeamGuard::names(StaffRules::staffOf($user->roles()))]);

            return $user;
        });
    }
}
