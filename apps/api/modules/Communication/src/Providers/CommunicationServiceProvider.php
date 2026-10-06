<?php

namespace Modules\Communication\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Communication\CommunicationService;
use Modules\Communication\Console\SweepCommunicationCommand;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Filament\NotificationBell;
use Modules\Communication\Live\InboxChanged;
use Modules\Communication\NotificationPreferences;
use Modules\Communication\Templates\LinkRegistry;
use Modules\Communication\Templates\TemplateRegistry;
use Modules\SharedKernel\Realtime\LiveSignals;

final class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/communication.php', 'communication');
        $this->app->singleton(TemplateRegistry::class);
        $this->app->singleton(NotificationsApi::class, CommunicationService::class);
        $this->app->singleton(NotificationLinks::class, LinkRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'communication');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'communication');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        $this->app->make(NotificationPreferences::class)->define();

        // The bell updates when the inbox changes (live:relay), not on a timer.
        $this->app->make(LiveSignals::class)->on('inbox', fn (string $userId) => new InboxChanged($userId));

        // The bell, in every panel's top bar (staff and teachers).
        Livewire::component('communication.bell', NotificationBell::class);
        FilamentView::registerRenderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => auth()->check() ? Blade::render('@livewire(\'communication.bell\')') : '');

        if ($this->app->runningInConsole()) {
            $this->commands([SweepCommunicationCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('communication:sweep')->everyMinute()->withoutOverlapping()->onOneServer();
        });
    }
}
