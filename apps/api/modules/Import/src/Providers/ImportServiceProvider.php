<?php

namespace Modules\Import\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Import\Contracts\Events\PeopleImported;
use Modules\Import\Listeners\SendWelcomes;

/**
 * Import (docs/architecture/01: "استيراد من CSV/Excel"): teachers, families
 * and running schedules from an academy's spreadsheets, through each
 * module's contracts. It sits on top; nothing depends on it.
 */
final class ImportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'import');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'import');

        Event::listen(PeopleImported::class, SendWelcomes::class);
    }
}
