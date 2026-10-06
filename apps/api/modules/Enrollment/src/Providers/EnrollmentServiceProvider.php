<?php

namespace Modules\Enrollment\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\EnrollmentService;

final class EnrollmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnrollmentApi::class, EnrollmentService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'enrollment');
    }
}
