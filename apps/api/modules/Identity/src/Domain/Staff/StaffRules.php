<?php

namespace Modules\Identity\Domain\Staff;

use Modules\Identity\Enums\Role;

/**
 * Who may change whose place on the academy's team (docs/architecture/02,
 * section 19): roles in, a reason out (null: allowed). The back office's
 * roles rank owner > admin > the rest; nobody gives or takes a role above
 * their own, nor touches someone who holds one (an admin and an owner),
 * nobody changes their own place, and the academy always keeps an active
 * owner. Teacher, student and guardian are People's to manage: never given
 * or taken here, and kept as they are. Reasons are keys of
 * identity::team.errors.
 */
final class StaffRules
{
    /** @var list<Role> the back office's roles, the only ones managed here */
    public const ROLES = [Role::Owner, Role::Admin, Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales];

    public static function isStaff(Role $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    /** Owner 3, admin 2, the rest of the back office 1, anyone else 0. */
    public static function rank(Role $role): int
    {
        return match ($role) {
            Role::Owner => 3,
            Role::Admin => 2,
            Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales => 1,
            default => 0,
        };
    }

    /** @param  list<Role>  $roles */
    public static function rankOf(array $roles): int
    {
        return array_reduce($roles, fn (int $top, Role $role) => max($top, self::rank($role)), 0);
    }

    /**
     * @param  list<Role>  $roles
     * @return list<Role> the back-office ones, in the team's order
     */
    public static function staffOf(array $roles): array
    {
        return array_values(array_filter(self::ROLES, fn (Role $role) => in_array($role, $roles, true)));
    }

    /**
     * @param  list<Role>  $roles
     * @return list<Role> the others (teacher, student, guardian), which stay as they are
     */
    public static function othersOf(array $roles): array
    {
        return array_values(array_filter($roles, fn (Role $role) => ! self::isStaff($role)));
    }

    /**
     * @param  list<Role>  $actor
     * @return list<Role> the roles someone may give and take: up to their own rank
     */
    public static function grantable(array $actor): array
    {
        $rank = self::rankOf($actor);

        return array_values(array_filter(self::ROLES, fn (Role $role) => self::rank($role) <= $rank));
    }

    /**
     * Inviting someone new with these roles.
     *
     * @param  list<Role>  $actor
     * @param  list<Role>  $roles
     */
    public static function whyNotInvite(array $actor, array $roles): ?string
    {
        return match (true) {
            $roles === [] => 'roles_required',
            self::othersOf($roles) !== [] => 'not_staff_role',
            self::rankOf($roles) > self::rankOf($actor) => 'above_you',
            default => null,
        };
    }

    /**
     * Acting on someone's place at all (their welcome link, say).
     *
     * @param  list<Role>  $actor
     * @param  list<Role>  $target  everything they hold
     */
    public static function whyNotTouch(array $actor, bool $self, array $target): ?string
    {
        return match (true) {
            $self => 'self',
            self::staffOf($target) === [] => 'not_staff',
            self::rankOf($target) > self::rankOf($actor) => 'touch_above',
            default => null,
        };
    }

    /**
     * Giving someone the back-office roles $wanted in place of the ones they
     * hold; their other roles stay. Someone not on the team yet (a teacher)
     * may join it this way.
     *
     * @param  list<Role>  $actor
     * @param  list<Role>  $target  everything they hold now
     * @param  list<Role>  $wanted  the back-office roles they should hold
     * @param  int  $otherActiveOwners  active owners besides them
     */
    public static function whyNotChange(array $actor, bool $self, array $target, array $wanted, bool $targetActive, int $otherActiveOwners): ?string
    {
        $before = self::staffOf($target);
        $added = array_values(array_filter($wanted, fn (Role $role) => ! in_array($role, $before, true)));
        $removed = array_values(array_filter($before, fn (Role $role) => ! in_array($role, $wanted, true)));

        return match (true) {
            $self => 'self',
            self::othersOf($wanted) !== [] => 'not_staff_role',
            self::rankOf($target) > self::rankOf($actor) => 'touch_above',
            self::rankOf([...$added, ...$removed]) > self::rankOf($actor) => 'above_you',
            [...$wanted, ...self::othersOf($target)] === [] => 'roles_required',
            in_array(Role::Owner, $removed, true) && $targetActive && $otherActiveOwners === 0 => 'last_owner',
            default => null,
        };
    }

    /**
     * Turning someone's account off from the team. Someone who also teaches
     * or is a family member is turned off from People's screens (which
     * check their lessons first), or loses their back-office roles here.
     *
     * @param  list<Role>  $actor
     * @param  list<Role>  $target
     * @param  int  $otherActiveOwners  active owners besides them
     */
    public static function whyNotDeactivate(array $actor, bool $self, array $target, int $otherActiveOwners): ?string
    {
        return self::whyNotTouch($actor, $self, $target) ?? match (true) {
            self::othersOf($target) !== [] => 'also_other',
            in_array(Role::Owner, $target, true) && $otherActiveOwners === 0 => 'last_owner',
            default => null,
        };
    }

    /**
     * Turning someone's account back on from the team.
     *
     * @param  list<Role>  $actor
     * @param  list<Role>  $target
     */
    public static function whyNotReactivate(array $actor, bool $self, array $target): ?string
    {
        return self::whyNotTouch($actor, $self, $target) ?? (self::othersOf($target) !== [] ? 'also_other' : null);
    }
}
