<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\IdentityService;
use Modules\Identity\Models\PersonalAccessToken;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdentityApi::class, IdentityService::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'identity');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale'])
            ->group(__DIR__.'/../../routes/api.php');
    }
}
