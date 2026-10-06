<?php

namespace Modules\Identity\Authorization;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Authorization\Ability;

/** Which fixed roles hold which ability (docs/architecture/01-features.md, permission matrix). */
final class AbilityMatrix
{
    /** @return list<Role> */
    public static function rolesFor(Ability $ability): array
    {
        return match ($ability) {
            Ability::ManageBranding => [Role::Owner, Role::Admin],
            Ability::ViewLiveBoard, Ability::ManageSessions, Ability::ReviewShifts => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler],
            Ability::FindTeachers => [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler, Role::Sales],
            Ability::ProposeOwnShifts => [Role::Teacher],
        };
    }

    public static function register(): void
    {
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user): bool => $user->isActive() && $user->hasRole(...self::rolesFor($ability)));
        }
    }
}
