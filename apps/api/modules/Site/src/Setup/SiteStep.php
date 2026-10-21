<?php

namespace Modules\Site\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;
use Modules\Site\Models\SiteSettings;

/** The academy's own page switched on, where families find it and ask for a free trial lesson. Optional: many academies start from WhatsApp. */
final class SiteStep implements SetupStep
{
    public function key(): string
    {
        return 'site.page';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 50;
    }

    public function required(): bool
    {
        return false;
    }

    public function title(): string
    {
        return __('site::site.setup.title');
    }

    public function hint(): string
    {
        return __('site::site.setup.hint');
    }

    public function done(): bool
    {
        return SiteSettings::isOn();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageSite->value) ? route('filament.academy.pages.settings.site') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageSite;
    }
}
