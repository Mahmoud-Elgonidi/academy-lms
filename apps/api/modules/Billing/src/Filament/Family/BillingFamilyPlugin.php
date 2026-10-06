<?php

namespace Modules\Billing\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Billing in the family area: lessons left, and the invoices of the person who pays. */
final class BillingFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'billing-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyBilling::class])->widgets([BalanceWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
