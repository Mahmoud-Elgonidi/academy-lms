<?php

namespace Modules\People\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Family\SessionFamilyView;
use Modules\People\Filament\Family\LearnerSwitcher;
use Modules\People\PeopleService;

final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PeopleApi::class, PeopleService::class);
        $this->app->scoped(FamilyView::class, SessionFamilyView::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'people');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'people');
        Livewire::component('people.learner-switcher', LearnerSwitcher::class);

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');
    }
}
