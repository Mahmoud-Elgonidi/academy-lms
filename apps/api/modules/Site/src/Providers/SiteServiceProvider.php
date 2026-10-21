<?php

namespace Modules\Site\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\Site\Commands\SweepJoinRequests;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Listeners\SendSiteNotifications;
use Modules\Site\Privacy\JoinRequestsData;
use Modules\Site\Setup\SiteStep;
use Modules\Site\SiteService;
use Modules\Site\Support\RequestAccess;

/**
 * Each academy's public page (docs/architecture/02, section 24): what it
 * says, the requests it brings, and the screens to run it. Sits on top: it
 * reads the subjects (Catalog), programs (Curriculum), teachers' cards
 * (People), plans and regions (Billing) and the academy's address (Platform)
 * through their contracts, and only the CRM uses its own (SiteApi,
 * JoinRequested), to make leads of its trial requests.
 */
final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SiteApi::class, SiteService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'site');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'site');
        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        // What a person asked from the page, for their copy and erasure (Privacy).
        $this->app->make(PersonalData::class)->add(JoinRequestsData::class);
        // An optional step of "Start here": the page switched on.
        $this->app->make(SetupChecklist::class)->add(SiteStep::class);

        SendSiteNotifications::templates($this->app->make(NotificationsApi::class));
        // In the back office it opens the requests for whoever answers that kind; nowhere else.
        $this->app->make(NotificationLinks::class)->register('site_request', fn (array $a, string $panel) => $panel === 'academy' && app(RequestAccess::class)->kinds() !== []
            ? route('filament.academy.pages.site.requests')
            : null);
        Event::listen(JoinRequested::class, [SendSiteNotifications::class, 'requested']);

        if ($this->app->runningInConsole()) {
            $this->commands([SweepJoinRequests::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // A visitor's name and number go a year after they asked.
            $schedule->command('site:sweep')->dailyAt('03:35')->withoutOverlapping()->onOneServer();
        });
    }
}
