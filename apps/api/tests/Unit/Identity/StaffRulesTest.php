<?php

use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\Role;

/*
 * Who may change whose place on the academy's team (docs/architecture/02,
 * section 19): owner > admin > the rest, nobody above their own rank,
 * never oneself, always an active owner, and the roles People manages
 * (teacher, student, guardian) never given, taken or turned off here.
 */

arch('the team rules are pure: roles in, a reason out')
    ->expect('Modules\Identity\Domain')
    ->toOnlyUse(['Modules\Identity\Domain', 'Modules\Identity\Enums\Role']);

it('ranks the owner over admins over the rest of the back office, and lets each give only up to their own', function () {
    expect(array_map(fn (Role $r) => StaffRules::rank($r), Role::cases()))->toBe([3, 2, 1, 1, 1, 1, 0, 0, 0])
        ->and(StaffRules::rankOf([Role::Teacher, Role::Accountant]))->toBe(1)
        ->and(StaffRules::rankOf([]))->toBe(0)
        ->and(StaffRules::grantable([Role::Owner]))->toBe(StaffRules::ROLES)
        ->and(StaffRules::grantable([Role::Admin, Role::Teacher]))->toBe([Role::Admin, Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales])
        ->and(StaffRules::grantable([Role::Accountant]))->toBe([Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales])
        ->and(StaffRules::grantable([Role::Teacher, Role::Guardian]))->toBe([])
        ->and(StaffRules::staffOf([Role::Teacher, Role::Sales, Role::Owner]))->toBe([Role::Owner, Role::Sales])
        ->and(StaffRules::othersOf([Role::Teacher, Role::Sales, Role::Guardian]))->toBe([Role::Teacher, Role::Guardian]);
});

it('invites only with back-office roles, up to the inviter\'s own', function () {
    expect(StaffRules::whyNotInvite([Role::Admin], []))->toBe('roles_required')
        ->and(StaffRules::whyNotInvite([Role::Owner], [Role::Teacher]))->toBe('not_staff_role')
        ->and(StaffRules::whyNotInvite([Role::Owner], [Role::Sales, Role::Guardian]))->toBe('not_staff_role')
        ->and(StaffRules::whyNotInvite([Role::Admin], [Role::Owner]))->toBe('above_you')
        ->and(StaffRules::whyNotInvite([Role::Admin], [Role::Admin, Role::Accountant]))->toBeNull()
        ->and(StaffRules::whyNotInvite([Role::Owner], [Role::Owner]))->toBeNull();
});

it('changes roles up to one\'s own rank, never one\'s own, never an owner\'s for an admin, and keeps an active owner', function () {
    $change = fn (array $actor, array $target, array $wanted, bool $self = false, int $owners = 1, bool $active = true) => StaffRules::whyNotChange($actor, $self, $target, $wanted, $active, $owners);

    expect($change([Role::Owner, Role::Admin], [Role::Owner, Role::Admin], [Role::Admin], self: true))->toBe('self')
        // An admin neither makes an owner nor touches one, even one who is an admin too.
        ->and($change([Role::Admin], [Role::Scheduler], [Role::Scheduler, Role::Owner]))->toBe('above_you')
        ->and($change([Role::Admin], [Role::Owner], [Role::Owner, Role::Accountant]))->toBe('touch_above')
        ->and($change([Role::Admin], [Role::Owner, Role::Admin], [Role::Admin]))->toBe('touch_above')
        ->and($change([Role::Supervisor], [Role::Admin], [Role::Sales]))->toBe('touch_above')
        // Up to their own: an admin makes and unmakes admins and the rest.
        ->and($change([Role::Admin], [Role::Sales], [Role::Admin]))->toBeNull()
        ->and($change([Role::Admin], [Role::Admin], [Role::Supervisor]))->toBeNull()
        ->and($change([Role::Owner], [Role::Admin], [Role::Owner]))->toBeNull()
        // People's roles are not given here, and someone keeps at least one role.
        ->and($change([Role::Owner], [Role::Sales], [Role::Teacher]))->toBe('not_staff_role')
        ->and($change([Role::Owner], [Role::Sales], []))->toBe('roles_required')
        // A teacher joins the team, and leaves it still teaching.
        ->and($change([Role::Admin], [Role::Teacher], [Role::Supervisor]))->toBeNull()
        ->and($change([Role::Owner], [Role::Teacher, Role::Sales], []))->toBeNull()
        // Never the last active owner.
        ->and($change([Role::Owner], [Role::Owner], [Role::Admin], owners: 0))->toBe('last_owner')
        ->and($change([Role::Owner], [Role::Owner], [Role::Admin], owners: 1))->toBeNull()
        ->and($change([Role::Owner], [Role::Owner], [Role::Admin], owners: 0, active: false))->toBeNull();
});

it('turns off and on only back-office accounts at or below one\'s own rank, never one\'s own or the last owner\'s', function () {
    expect(StaffRules::whyNotDeactivate([Role::Admin], false, [Role::Sales], 1))->toBeNull()
        ->and(StaffRules::whyNotDeactivate([Role::Admin], true, [Role::Admin], 1))->toBe('self')
        ->and(StaffRules::whyNotDeactivate([Role::Admin], false, [Role::Owner], 1))->toBe('touch_above')
        ->and(StaffRules::whyNotDeactivate([Role::Owner], false, [Role::Owner], 1))->toBeNull()
        ->and(StaffRules::whyNotDeactivate([Role::Owner], false, [Role::Owner], 0))->toBe('last_owner')
        // Someone who also teaches or is family is turned off from People's screens.
        ->and(StaffRules::whyNotDeactivate([Role::Owner], false, [Role::Supervisor, Role::Teacher], 1))->toBe('also_other')
        ->and(StaffRules::whyNotDeactivate([Role::Owner], false, [Role::Teacher], 1))->toBe('not_staff')
        ->and(StaffRules::whyNotReactivate([Role::Admin], false, [Role::Accountant]))->toBeNull()
        ->and(StaffRules::whyNotReactivate([Role::Admin], false, [Role::Owner]))->toBe('touch_above')
        ->and(StaffRules::whyNotReactivate([Role::Admin], false, [Role::Guardian, Role::Sales]))->toBe('also_other')
        ->and(StaffRules::whyNotTouch([Role::Admin], false, [Role::Teacher]))->toBe('not_staff');
});
