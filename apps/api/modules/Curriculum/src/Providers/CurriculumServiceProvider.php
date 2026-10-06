<?php

namespace Modules\Curriculum\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\CurriculumService;

final class CurriculumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurriculumApi::class, CurriculumService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'curriculum');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'curriculum');
    }
}
