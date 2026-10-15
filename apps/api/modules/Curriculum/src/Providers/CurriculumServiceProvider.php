<?php

namespace Modules\Curriculum\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\CurriculumService;
use Modules\Curriculum\Http\ResourcePreviewController;
use Modules\Curriculum\Setup\ProgramStep;
use Modules\Curriculum\Texts\QuranText;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Setup\SetupChecklist;

final class CurriculumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurriculumApi::class, CurriculumService::class);
        // The platform's copy of the Quran (Tanzil's simple text and metadata, resources/quran/README.md).
        $this->app->singleton(QuranText::class, fn () => new QuranText(dirname(__DIR__, 2).'/resources/quran'));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'curriculum');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'curriculum');
        // The new academy's checklist (optional): a published curriculum.
        $this->app->make(SetupChecklist::class)->add(ProgramStep::class);

        // The plan's storage counts the files on lessons.
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, fn () => (int) DB::table('node_resources')->sum('size'));

        // Staff who build the curriculum open a lesson's file from the builder; everyone else gets a signed link from Learning.
        Route::middleware(['web', 'tenant', 'auth'])->get('curriculum/resources/{resource}', ResourcePreviewController::class)->name('curriculum.resource');
    }
}
