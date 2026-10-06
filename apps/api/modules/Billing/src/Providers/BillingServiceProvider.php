<?php

namespace Modules\Billing\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Billing\BillingService;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Listeners\IssueRenewalInvoice;
use Modules\Credits\Contracts\Events\CreditBalanceLow;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingApi::class, BillingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'billing');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'billing');

        Event::listen(CreditBalanceLow::class, IssueRenewalInvoice::class);
    }
}
