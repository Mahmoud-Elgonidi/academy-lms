<?php

namespace Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\CatalogService;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Catalog\Setup\SubjectsStep;
use Modules\SharedKernel\Setup\SetupChecklist;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogApi::class, CatalogService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'catalog');
        // The new academy's checklist: a first subject.
        $this->app->make(SetupChecklist::class)->add(SubjectsStep::class);
    }
}
