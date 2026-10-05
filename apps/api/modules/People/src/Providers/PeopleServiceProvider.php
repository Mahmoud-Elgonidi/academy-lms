<?php

namespace Modules\People\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\People\Contracts\PeopleApi;
use Modules\People\PeopleService;

final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PeopleApi::class, PeopleService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
