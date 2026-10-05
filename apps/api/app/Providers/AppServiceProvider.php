<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Times are immutable everywhere; the database and PHP both run in UTC.
        Date::use(CarbonImmutable::class);

        // Catch lazy loading, silently discarded attributes and missing attributes in dev and tests.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
