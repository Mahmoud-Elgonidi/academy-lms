<?php

namespace Modules\Billing\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Billing\Models\Plan;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** A plan on offer with its price in a price region: what the first invoice is made of. */
final class PricesStep implements SetupStep
{
    public function key(): string
    {
        return 'billing.prices';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Money;
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
        return __('billing::setup.prices.title');
    }

    public function hint(): string
    {
        return __('billing::setup.prices.hint');
    }

    public function done(): bool
    {
        return Plan::query()->where('is_active', true)->whereHas('prices')->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageBilling->value) ? route('filament.academy.resources.settings.plans.index') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageBilling;
    }
}
