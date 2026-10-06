<?php

namespace Modules\Billing\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Billing\BillingService;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\Events\InvoiceIssued;
use Modules\Billing\Contracts\Events\InvoicePaid;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Listeners\IssueRenewalInvoice;
use Modules\Billing\Listeners\SendBillingNotifications;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
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

        SendBillingNotifications::templates($this->app->make(NotificationsApi::class));
        $this->app->make(NotificationLinks::class)->register('invoice', fn (array $a, string $panel) => $panel === 'academy'
            ? InvoiceResource::getUrl('view', ['record' => $a['id']], panel: 'academy') : null);
        Event::listen(InvoiceIssued::class, [SendBillingNotifications::class, 'issued']);
        Event::listen(InvoicePaid::class, [SendBillingNotifications::class, 'paid']);
    }
}
