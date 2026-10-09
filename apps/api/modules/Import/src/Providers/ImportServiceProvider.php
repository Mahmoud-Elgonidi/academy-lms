<?php

namespace Modules\Import\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Import\Contracts\Events\PeopleImported;
use Modules\Import\Listeners\SendWelcomes;
use Modules\Import\Privacy\ImportsData;
use Modules\SharedKernel\Privacy\PersonalData;

/**
 * Import (docs/architecture/01: "استيراد من CSV/Excel"): teachers, families
 * and running schedules from an academy's spreadsheets, through each
 * module's contracts. It sits on top; nothing depends on it.
 */
final class ImportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The spreadsheet rows a person came from, emptied when they are erased (Privacy).
        $this->app->make(PersonalData::class)->add(ImportsData::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'import');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'import');

        Event::listen(PeopleImported::class, SendWelcomes::class);
    }
}
