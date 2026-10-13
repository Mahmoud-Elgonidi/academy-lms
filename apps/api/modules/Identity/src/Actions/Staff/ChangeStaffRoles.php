<?php

namespace Modules\Identity\Actions\Staff;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Models\UserRole;
use Modules\Identity\Staff\TeamGuard;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Gives someone the back-office roles they should hold, in place of the
 * ones they hold now (StaffRules: up to the changer's own rank, never one's
 * own, never the last active owner's). Their teacher, student or guardian
 * role stays; a teacher may join the team this way. What they may do
 * follows on their next step (abilities are read each request); a new
 * owner, admin or accountant is asked to set up two-step sign-in on their
 * next visit to the back office. In the audit log, with the roles.
 */
final class ChangeStaffRoles
{
    public function __construct(
        private readonly TeamGuard $team,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  list<Role>  $roles  the back-office roles they should hold */
    public function handle(string $userId, array $roles, string $actorId): User
    {
        return DB::transaction(function () use ($userId, $roles, $actorId) {
            $actor = $this->team->actor($actorId);
            $user = $this->team->member($userId);
            $wanted = array_values(array_filter(Role::cases(), fn (Role $role) => in_array($role, $roles, true)));
            $held = $user->roles();
            TeamGuard::refuse(StaffRules::whyNotChange($actor->roles(), $actor->id === $user->id, $held, $wanted, $user->isActive(), $this->team->otherActiveOwners($user->id)));

            $before = StaffRules::staffOf($held);
            $added = array_values(array_filter($wanted, fn (Role $role) => ! in_array($role, $before, true)));
            $removed = array_values(array_filter($before, fn (Role $role) => ! in_array($role, $wanted, true)));
            if ($added === [] && $removed === []) {
                return $user;
            }

            if ($removed !== []) {
                UserRole::query()->where('user_id', $user->id)->whereIn('role', TeamGuard::names($removed))->delete();
            }
            foreach ($added as $role) {
                $user->roleRecords()->create(['role' => $role]);
            }
            $user->load('roleRecords');

            $this->audit->record('identity.staff_roles_changed', 'user', $user->id, [
                'name' => $user->name,
                'roles' => TeamGuard::names(StaffRules::staffOf($user->roles())),
                'added' => TeamGuard::names($added),
                'removed' => TeamGuard::names($removed),
            ]);

            return $user;
        });
    }
}
