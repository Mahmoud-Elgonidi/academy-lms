<?php

namespace Modules\Enrollment\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\EnrollmentService;
use Modules\Enrollment\Queries\ActiveStudents;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;

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
        // The plan's active students limit counts the students with a running enrollment.
        $this->app->make(UsageMeters::class)->add(Limit::ActiveStudents, fn () => $this->app->make(ActiveStudents::class)->count());
    }
}
