<?php

namespace Modules\Platform\Providers;

use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Commands\CreatePlatformAdmin;
use Modules\Platform\Commands\HealthCommand;
use Modules\Platform\Commands\MailTestCommand;
use Modules\Platform\Commands\MeasureUsageCommand;
use Modules\Platform\Commands\ShowPlatformCode;
use Modules\Platform\Console\ConsoleService;
use Modules\Platform\Contracts\AccountDirectory;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Directory\EloquentTenantDirectory;
use Modules\Platform\Directory\PlatformAccountDirectory;
use Modules\Platform\Health\WorkerBeat;
use Modules\Platform\Http\Controllers\HealthController;
use Modules\Platform\Listeners\RecordConsoleSignIn;
use Modules\Platform\Models\AcademyBranding;
use Modules\Platform\Plans\PlanUsage;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\PlatformService;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Tenancy\TenantResolver;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/platform.php', 'platform');

        $this->app->singleton(TenantDirectory::class, EloquentTenantDirectory::class);
        $this->app->alias(TenantDirectory::class, TenantResolver::class);
        $this->app->singleton(PlatformApi::class, PlatformService::class);
        $this->app->singleton(ConsoleApi::class, ConsoleService::class);
        $this->app->singleton(AccountDirectory::class, PlatformAccountDirectory::class);

        // Scoped: built once per request for the request's academy.
        $this->app->scoped(BrandingService::class);
        $this->app->alias(BrandingService::class, BrandingApi::class);

        // The academies' SaaS plans, for every module through SharedKernel's AcademyPlan.
        $this->app->scoped(PlatformAcademyPlan::class);
        $this->app->alias(PlatformAcademyPlan::class, AcademyPlan::class);
        $this->app->scoped(PlanUsage::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'platform');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'platform');
        Event::listen(Login::class, RecordConsoleSignIn::class);
        if ($this->app->runningInConsole()) {
            $this->commands([CreatePlatformAdmin::class, ShowPlatformCode::class, MeasureUsageCommand::class, HealthCommand::class, MailTestCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('platform:usage')->dailyAt('02:10')->withoutOverlapping()->onOneServer();
            // The stack's heartbeats (the scheduler's own, and one through the queue to the worker) and the alerts.
            $schedule->call(fn () => Heartbeats::beat('scheduler'))->everyMinute()->name('health:scheduler');
            $schedule->call(fn () => WorkerBeat::dispatch(now()->getTimestamp())->onQueue('default'))->everyMinute()->name('health:worker');
            $schedule->command('platform:health --alert')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });

        // For an uptime monitor (HEALTH_TOKEN): statuses only, no session, no academy.
        Route::middleware('throttle:30,1')->get('health', HealthController::class)->name('platform.health');

        // The plan's storage counts the academy's logo and icon too.
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, function (): int {
            $branding = AcademyBranding::query()->first();
            $disk = Storage::disk('public');

            return array_sum(array_map(fn (?string $path) => $path !== null && $disk->exists($path) ? $disk->size($path) : 0, [$branding?->logo_path, $branding?->favicon_path]));
        });

        // On any panel screen, something the plan stops says why instead of failing the page.
        \Livewire\on('exception', function ($component, $e, $stopPropagation): void {
            if ($e instanceof PlanLimitReached || $e instanceof FeatureNotInPlan) {
                Notification::make()->danger()->title(__('platform::plans.stopped'))->body($e->getMessage())->persistent()->send();
                $stopPropagation();
            }
        });

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale'])
            ->group(__DIR__.'/../../routes/api.php');
    }
}
