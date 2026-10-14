<?php

namespace Modules\Privacy\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Privacy\Models\PrivacySettings;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/**
 * Who the academy is on its privacy notice and terms (LegalController): its
 * legal name and the address people write to about their data. Without
 * them the public pages fall back to the academy's name and the apps.
 */
final class PrivacyDetailsStep implements SetupStep
{
    public function key(): string
    {
        return 'privacy.details';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 30;
    }

    public function required(): bool
    {
        return true;
    }

    public function title(): string
    {
        return __('privacy::setup.details.title');
    }

    public function hint(): string
    {
        return __('privacy::setup.details.hint');
    }

    public function done(): bool
    {
        return PrivacySettings::query()->whereNotNull('legal_name')->whereNotNull('contact_email')->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::HandleDataRequests->value) ? route('filament.academy.pages.data-requests') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::HandleDataRequests;
    }
}
