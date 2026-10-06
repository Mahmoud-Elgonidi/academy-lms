<?php

namespace Modules\Credits\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Credits\Console\ExpireCreditsCommand;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\CreditsService;

final class CreditsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreditsApi::class, CreditsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'credits');

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireCreditsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('credits:expire')->hourly()->withoutOverlapping()->onOneServer();
        });
    }
}
