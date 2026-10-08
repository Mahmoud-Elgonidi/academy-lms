<?php

namespace Modules\Identity\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Authorization\AbilityMatrix;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Identity\Contracts\SignInGate;
use Modules\Identity\IdentityService;
use Modules\Identity\Models\OtpChallenge;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\SocialSignIn;
use Modules\Identity\Models\User;
use Modules\Identity\Otp\LogOtpSender;
use Modules\Identity\Preferences\PreferenceStore;
use Modules\Identity\Queries\ActiveTeachers;
use Modules\Identity\SignIn\OpenSignInGate;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/identity.php', 'identity');
        $this->app->singleton(IdentityApi::class, IdentityService::class);
        // Codes go to the log until Communication binds the real sender.
        $this->app->singletonIf(OtpSender::class, LogOtpSender::class);
        // Per request: a module above may bind its own (People: a child waits for a guardian's consent).
        $this->app->scopedIf(SignInGate::class, OpenSignInGate::class);

        // One registry per process: modules declare their preferences in boot().
        $this->app->singleton(PreferenceStore::class);
        $this->app->alias(PreferenceStore::class, UserPreferences::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        AbilityMatrix::register();
        // The plan's teacher limit counts the academy's active teachers.
        $this->app->make(UsageMeters::class)->add(Limit::Teachers, fn () => $this->app->make(ActiveTeachers::class)->count());

        // Each person's own channel: only they listen (their inbox, their lessons, the panels' pop-ups).
        Broadcast::channel(PersonalChannel::PATTERN, fn (User $user, string $id) => (string) $user->getKey() === $id);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'identity');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'identity');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');
        Route::prefix('api/v1')
            ->middleware(['api', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api_accounts.php');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('model:prune', ['--model' => [OtpChallenge::class, SocialSignIn::class]])->daily()->onOneServer();
        });
    }
}
