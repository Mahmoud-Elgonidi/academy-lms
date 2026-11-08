<?php

namespace Modules\Communication\Providers;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Communication\CommunicationService;
use Modules\Communication\Console\SweepCommunicationCommand;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Filament\NotificationBell;
use Modules\Communication\Listeners\TellStaffAboutUsage;
use Modules\Communication\Live\InboxChanged;
use Modules\Communication\NotificationPreferences;
use Modules\Communication\Otp\CommunicationOtpSender;
use Modules\Communication\Privacy\MessagesData;
use Modules\Communication\Templates\LinkRegistry;
use Modules\Communication\Templates\TemplateRegistry;
use Modules\Identity\Contracts\OtpSender;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Plans\Events\BalanceRunningOut;
use Modules\SharedKernel\Plans\Events\LotExpiringSoon;
use Modules\SharedKernel\Plans\Events\UsageThresholdReached;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\TenantContext;

final class CommunicationServiceProvider extends ServiceProvider
{
    /** Settings › Usage and balance (Platform's page, by route name). */
    private const USAGE_PAGE = 'filament.academy.pages.settings.usage';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/communication.php', 'communication');
        $this->app->singleton(TemplateRegistry::class);
        $this->app->singleton(NotificationsApi::class, CommunicationService::class);
        $this->app->singleton(NotificationLinks::class, LinkRegistry::class);
        // Identity's sign-in codes go out through the platform's channels (Identity sits below and cannot call us).
        $this->app->singleton(OtpSender::class, CommunicationOtpSender::class);
    }

    public function boot(): void
    {
        // The messages a person received and their push devices, for their copy and erasure (Privacy).
        $this->app->make(PersonalData::class)->add(MessagesData::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'communication');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'communication');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        $this->app->make(NotificationPreferences::class)->define();

        // The academy's usage of its plan (Platform raises the events in SharedKernel; the staff who see the plan hear them).
        TellStaffAboutUsage::define($this->app->make(NotificationsApi::class));
        Event::listen(UsageThresholdReached::class, [TellStaffAboutUsage::class, 'threshold']);
        Event::listen(LotExpiringSoon::class, [TellStaffAboutUsage::class, 'expiring']);
        Event::listen(BalanceRunningOut::class, [TellStaffAboutUsage::class, 'runningOut']);
        $links = $this->app->make(NotificationLinks::class);
        $links->register('usage', fn (array $action, string $panel) => $panel === 'academy' && Route::has(self::USAGE_PAGE) ? route(self::USAGE_PAGE) : null);
        $links->registerPersonal('usage', fn (array $action, string $userId) => Route::has(self::USAGE_PAGE) && $this->app->make(TenantContext::class)->check()
            ? $this->app->make(TenantDirectory::class)->baseUrl($this->app->make(TenantContext::class)->require()).route(self::USAGE_PAGE, absolute: false) : null);

        // The bell updates when the inbox changes (live:relay), not on a timer.
        $this->app->make(LiveSignals::class)->on('inbox', fn (string $userId) => new InboxChanged($userId));

        // The bell, in every panel's top bar (staff and teachers).
        Livewire::component('communication.bell', NotificationBell::class);
        // The academy's panels only: the platform console has no inbox.
        FilamentView::registerRenderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => auth()->check() && in_array(Filament::getCurrentPanel()?->getId(), ['academy', 'teach', 'family'], true) ? Blade::render('@livewire(\'communication.bell\')') : '');

        if ($this->app->runningInConsole()) {
            $this->commands([SweepCommunicationCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('communication:sweep')->everyMinute()->withoutOverlapping()->onOneServer();
        });
    }
}
