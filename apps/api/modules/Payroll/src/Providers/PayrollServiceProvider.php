<?php

namespace Modules\Payroll\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Payroll\Contracts\Events\PayrollMonthClosed;
use Modules\Payroll\Filament\Pages\Payroll;
use Modules\Payroll\Filament\Teacher\MyEarnings;
use Modules\Payroll\Listeners\AccruePay;
use Modules\Payroll\Listeners\SendPayrollNotifications;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Contracts\Events\SessionFlaggedForReview;

/**
 * Payroll (docs/architecture/04, M13): rate cards, the teachers' pay ledger,
 * payroll months and their export. It listens to Scheduling; nothing
 * depends on it.
 */
final class PayrollServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'payroll');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'payroll');
        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendPayrollNotifications::templates($this->app->make(NotificationsApi::class));
        $this->app->make(NotificationLinks::class)->register('payroll_month', fn (array $a, string $panel) => match ($panel) {
            'teach' => MyEarnings::getUrl(['month' => $a['id'] ?? null], panel: 'teach'),
            'academy' => Payroll::getUrl(['month' => $a['id'] ?? null], panel: 'academy'),
            default => null,
        });

        Event::listen([SessionFinalized::class, SessionCancelled::class, SessionFlaggedForReview::class], AccruePay::class);
        Event::listen(PayrollMonthClosed::class, [SendPayrollNotifications::class, 'monthClosed']);
    }
}
