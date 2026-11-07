<?php

namespace Modules\Platform\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Platform\Models\AcademyModules;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** Choosing the academy's optional modules (Settings › Academy modules): done once they were saved, whatever was chosen. */
final class ModulesStep implements SetupStep
{
    public function key(): string
    {
        return 'platform.modules';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 15;
    }

    public function required(): bool
    {
        return false;
    }

    public function title(): string
    {
        return __('platform::modules.setup.title');
    }

    public function hint(): string
    {
        return __('platform::modules.setup.hint');
    }

    public function done(): bool
    {
        return AcademyModules::query()->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageModules->value) ? route('filament.academy.pages.settings.modules') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageModules;
    }
}
