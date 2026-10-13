<?php

namespace Modules\Identity\Actions\Staff;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\CreateUser;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\Events\UserInvited;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Staff\TeamGuard;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Someone new joins the academy's team with back-office roles (up to the
 * inviter's own rank), through Identity's one door for accounts (the plan's
 * teacher limit is not theirs to hold). No password: if asked, they get the
 * welcome with their own link to choose one (People sends it, after
 * commit). In the audit log, with the roles.
 */
final class InviteStaff
{
    public function __construct(
        private readonly TeamGuard $team,
        private readonly CreateUser $create,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  list<Role>  $roles */
    public function handle(UserDetails $who, array $roles, bool $welcome, string $actorId): User
    {
        return DB::transaction(function () use ($who, $roles, $welcome, $actorId) {
            $actor = $this->team->actor($actorId);
            $roles = array_values(array_filter(Role::cases(), fn (Role $role) => in_array($role, $roles, true)));
            TeamGuard::refuse(StaffRules::whyNotInvite($actor->roles(), $roles));

            $email = trim((string) $who->email);
            $user = $this->create->handle(new NewUser(
                name: trim($who->name),
                roles: $roles,
                email: $email === '' ? null : $email,
                phoneE164: $who->phoneE164,
                locale: $who->locale,
                timezone: $who->timezone,
            ));
            $this->audit->record('identity.staff_invited', 'user', $user->id, ['name' => $user->name, 'roles' => TeamGuard::names($user->roles())]);

            if ($welcome) {
                $id = $user->id;
                DB::afterCommit(fn () => event(new UserInvited($id)));
            }

            return $user;
        });
    }
}
