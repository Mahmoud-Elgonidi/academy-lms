<?php

namespace Modules\Platform\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Directory\EloquentTenantDirectory;
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
