<?php

namespace Modules\Identity\Staff;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * What every change to the team checks before StaffRules: who acts (an
 * active member who may manage the team; never the platform team signed in
 * as someone, as with passwords), one change at a time per academy, and the
 * person changed (not an erased account, not a child under a guardian).
 */
final class TeamGuard
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * The person acting, read again under the academy's team lock (call
     * inside the transaction), so two changes at once never leave it
     * without an active owner.
     */
    public function actor(string $actorId): User
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['roles' => __('identity::team.errors.impersonating')]);
        }
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['team:'.$this->tenants->require()->id]);

        $actor = User::query()->with('roleRecords')->find($actorId);
        if ($actor === null || ! Gate::forUser($actor)->allows(Ability::ManageStaff->value)) {
            throw new AuthorizationException(__('identity::team.errors.not_allowed'));
        }

        return $actor;
    }

    public function member(string $userId): User
    {
        $user = User::query()->with('roleRecords')->whereKey($userId)->lockForUpdate()->firstOrFail();
        if ($user->status === UserStatus::Erased) {
            throw ValidationException::withMessages(['roles' => __('identity::users.erased')]);
        }
        if ($user->is_managed_minor) {
            throw ValidationException::withMessages(['roles' => __('identity::team.errors.minor')]);
        }

        return $user;
    }

    /** Active owners other than this person. */
    public function otherActiveOwners(string $userId): int
    {
        return User::query()->where('status', UserStatus::Active->value)->whereKeyNot($userId)
            ->whereHas('roleRecords', fn ($q) => $q->where('role', Role::Owner->value))->count();
    }

    /** Says why StaffRules refused, if it did. */
    public static function refuse(?string $reason, string $field = 'roles'): void
    {
        if ($reason !== null) {
            throw ValidationException::withMessages([$field => __('identity::team.errors.'.$reason)]);
        }
    }

    /** @return list<string> */
    public static function names(array $roles): array
    {
        return array_map(fn (Role $role) => $role->value, $roles);
    }
}
