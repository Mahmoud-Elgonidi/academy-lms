<?php

namespace Modules\Billing\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Billing\Filament\Pages\PaymentSettings;
use Modules\Billing\Filament\Pages\Revenue;
use Modules\Billing\Filament\Resources\Coupons\CouponResource;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Resources\Plans\PlanResource;

/** Billing's screens in the academy back office. */
final class BillingPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'billing';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([InvoiceResource::class, PlanResource::class, CouponResource::class])->pages([Revenue::class, PaymentSettings::class]);
    }

    public function boot(Panel $panel): void {}
}
