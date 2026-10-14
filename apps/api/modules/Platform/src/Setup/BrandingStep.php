<?php

namespace Modules\Platform\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Platform\Models\AcademyBranding;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;
use Modules\SharedKernel\Tenancy\TenantContext;

/** The academy's name in both languages and its logo, so families know whose page they are on. */
final class BrandingStep implements SetupStep
{
    public function key(): string
    {
        return 'platform.branding';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 10;
    }

    public function required(): bool
    {
        return true;
    }

    public function title(): string
    {
        return __('platform::setup.branding.title');
    }

    public function hint(): string
    {
        return __('platform::setup.branding.hint');
    }

    public function done(): bool
    {
        $academy = app(TenantContext::class)->require();

        return trim($academy->nameAr) !== '' && trim($academy->nameEn) !== ''
            && AcademyBranding::query()->whereNotNull('logo_path')->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageBranding->value) ? route('filament.academy.pages.settings.branding') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageBranding;
    }
}
