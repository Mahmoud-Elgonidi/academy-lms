<?php

namespace Modules\Scheduling\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/**
 * The cancellation, makeup and absence policy, looked at and saved by
 * someone at the academy (a version with its author), not only the defaults
 * the first lesson made version 1 of. Keeping the defaults counts: saving
 * them as they are writes the academy's own version (SavePolicySettings).
 */
final class PolicyStep implements SetupStep
{
    public function key(): string
    {
        return 'scheduling.policy';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 20;
    }

    public function required(): bool
    {
        return true;
    }

    public function title(): string
    {
        return __('scheduling::setup.policy.title');
    }

    public function hint(): string
    {
        return __('scheduling::setup.policy.hint');
    }

    public function done(): bool
    {
        return PolicyVersion::query()->whereNotNull('created_by')->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManagePolicies->value) ? route('filament.academy.pages.settings.policy') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManagePolicies;
    }
}
