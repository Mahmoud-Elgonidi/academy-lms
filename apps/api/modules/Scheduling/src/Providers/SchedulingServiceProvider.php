<?php

namespace Modules\Scheduling\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Identity\Contracts\Preferences\PreferenceDefinition;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Scheduling\Console\MaterializeSessionsCommand;
use Modules\Scheduling\Console\SweepSessionsCommand;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Listeners\FundSessionsFromNewCredit;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-academy policy sets replace this when the policy engine lands.
        $this->app->singleton(AttendancePolicy::class, fn () => AttendancePolicy::defaults());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'scheduling');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'scheduling');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        $this->app->make(UserPreferences::class)->define(new PreferenceDefinition(
            key: LiveBoard::LAYOUT_PREFERENCE,
            label: 'scheduling::board.layout.label',
            options: array_combine(LiveBoard::LAYOUTS, array_map(fn (string $l) => "scheduling::board.layout.{$l}", LiveBoard::LAYOUTS)),
            default: 'table',
            hint: 'scheduling::board.layout.hint',
        ));

        Event::listen(CreditsGranted::class, FundSessionsFromNewCredit::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SweepSessionsCommand::class, MaterializeSessionsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('scheduling:sweep')->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command('scheduling:materialize')->dailyAt('01:10')->withoutOverlapping()->onOneServer();
        });
    }
}
