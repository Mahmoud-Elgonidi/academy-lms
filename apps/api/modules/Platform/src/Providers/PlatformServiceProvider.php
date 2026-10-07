<?php

namespace Modules\Platform\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Commands\CreatePlatformAdmin;
use Modules\Platform\Commands\ShowPlatformCode;
use Modules\Platform\Console\ConsoleService;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Directory\EloquentTenantDirectory;
use Modules\Platform\Listeners\RecordConsoleSignIn;
use Modules\Platform\PlatformService;
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

        // Scoped: built once per request for the request's academy.
        $this->app->scoped(BrandingService::class);
        $this->app->alias(BrandingService::class, BrandingApi::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'platform');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'platform');
        Event::listen(Login::class, RecordConsoleSignIn::class);
        if ($this->app->runningInConsole()) {
            $this->commands([CreatePlatformAdmin::class, ShowPlatformCode::class]);
        }

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale'])
            ->group(__DIR__.'/../../routes/api.php');
    }
}
