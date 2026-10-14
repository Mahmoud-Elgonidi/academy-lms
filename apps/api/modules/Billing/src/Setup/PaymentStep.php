<?php

namespace Modules\Billing\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/**
 * A way for families to pay: transfer details written (the academy's own
 * text, or a price region's: Queries\TransferDetails, saved only through
 * SaveTransferDetails, so a saved text is never blank), or an online
 * gateway account switched on. One query for the three.
 */
final class PaymentStep implements SetupStep
{
    public function key(): string
    {
        return 'billing.payment';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Money;
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
        return __('billing::setup.payment.title');
    }

    public function hint(): string
    {
        return __('billing::setup.payment.hint');
    }

    public function done(): bool
    {
        return (bool) DB::selectOne(<<<'SQL'
            select exists (select 1 from billing_settings where coalesce(btrim(manual_payment_note), '') <> '')
                or exists (select 1 from price_regions where coalesce(btrim(transfer_details), '') <> '')
                or exists (select 1 from gateway_accounts where is_active) as ready
            SQL)->ready;
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageBilling->value) ? route('filament.academy.pages.settings.payments') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageBilling;
    }
}
