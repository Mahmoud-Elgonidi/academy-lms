<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Authorization\AbilityMatrix;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Identity\IdentityService;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;
use Modules\Identity\Preferences\PreferenceStore;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdentityApi::class, IdentityService::class);

        // One registry per process: modules declare their preferences in boot().
        $this->app->singleton(PreferenceStore::class);
        $this->app->alias(PreferenceStore::class, UserPreferences::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        AbilityMatrix::register();

        // Each person's own channel: only they listen (their inbox, their lessons, the panels' pop-ups).
        Broadcast::channel(PersonalChannel::PATTERN, fn (User $user, string $id) => (string) $user->getKey() === $id);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'identity');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'identity');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');
    }
}
