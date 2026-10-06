<?php

namespace Modules\Billing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Billing\BillingService;
use Modules\Billing\Console\SweepBillingCommand;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\Events\InvoiceIssued;
use Modules\Billing\Contracts\Events\InvoicePaid;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Http\Controllers\GatewayWebhookController;
use Modules\Billing\Listeners\IssueRenewalInvoice;
use Modules\Billing\Listeners\SendBillingNotifications;
use Modules\Billing\Live\PaymentsChanged;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\PayLinks;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Credits\Contracts\Events\CreditBalanceLow;
use Modules\SharedKernel\Realtime\LiveSignals;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/billing.php', 'billing');
        $this->app->singleton(BillingApi::class, BillingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'billing');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'billing');

        // Paying online: the invoice's pay page and the way back from a gateway (signed, on the academy's host),
        // and each gateway account's webhook (the academy and the account in the path, signed by the gateway).
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        Route::post('webhooks/payments/{academy}/{account}', GatewayWebhookController::class)
            ->middleware(['api', 'throttle:120,1'])->name('billing.webhooks');
        $this->app->make(LiveSignals::class)->on('payments', fn (string $userId) => new PaymentsChanged($userId));
        if ($this->app->runningInConsole()) {
            $this->commands([SweepBillingCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('billing:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });

        Event::listen(CreditBalanceLow::class, IssueRenewalInvoice::class);

        SendBillingNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('invoice', fn (array $a, string $panel) => match ($panel) {
            'academy' => InvoiceResource::getUrl('view', ['record' => $a['id']], panel: 'academy'),
            'family' => FamilyBilling::getUrl(panel: 'family'),
            default => null,
        });
        // The invoice message carries each payer's own link to the invoice and its ways to pay (no sign-in).
        $links->registerPersonal('invoice', function (array $action, string $userId) {
            $invoice = Invoice::query()->find($action['id'] ?? null);

            return $invoice === null ? null : $this->app->make(PayLinks::class)->invoice($invoice, $userId);
        });
        Event::listen(InvoiceIssued::class, [SendBillingNotifications::class, 'issued']);
        Event::listen(InvoicePaid::class, [SendBillingNotifications::class, 'paid']);
    }
}
