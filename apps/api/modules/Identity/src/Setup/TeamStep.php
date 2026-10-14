<?php

namespace Modules\Identity\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** Optional: someone besides the owner in the back office (an admin, a scheduler, an accountant, ...), through Settings › Team. */
final class TeamStep implements SetupStep
{
    public function key(): string
    {
        return 'identity.team';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 40;
    }

    public function required(): bool
    {
        return false;
    }

    public function title(): string
    {
        return __('identity::setup.team.title');
    }

    public function hint(): string
    {
        return __('identity::setup.team.hint');
    }

    public function done(): bool
    {
        $others = array_values(array_map(fn (Role $role) => $role->value, array_filter(StaffRules::ROLES, fn (Role $role) => $role !== Role::Owner)));

        return User::query()->where('status', UserStatus::Active->value)
            ->whereHas('roleRecords', fn ($q) => $q->whereIn('role', $others))
            ->whereDoesntHave('roleRecords', fn ($q) => $q->where('role', Role::Owner->value))
            ->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageStaff->value) ? route('filament.academy.pages.settings.team') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageStaff;
    }
}
