<?php

namespace Modules\Curriculum\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\CurriculumService;
use Modules\Curriculum\Http\ResourcePreviewController;
use Modules\Curriculum\Setup\ProgramStep;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Setup\SetupChecklist;

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
        // The new academy's checklist (optional): a published curriculum.
        $this->app->make(SetupChecklist::class)->add(ProgramStep::class);
        // Its AI capabilities (the gateway routes, meters and switches them): practice suggestions and the outline.
        $this->app->make(Capabilities::class)->add(Capability::of(AiFeature::PracticeItems, tier: 'best', credits: 1, module: 'curriculum'));
        $this->app->make(Capabilities::class)->add(Capability::of(AiFeature::CurriculumOutline, tier: 'best', credits: 5, module: 'curriculum'));

        // The plan's storage counts the files on lessons, each once (a copied program's lessons share the file).
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, fn () => (int) DB::query()
            ->fromSub(DB::table('node_resources')->whereNotNull('file_path')->select('file_path', 'size')->distinct(), 'files')->sum('size'));

        // Staff who build the curriculum open a lesson's file from the builder; everyone else gets a signed link from Learning.
        Route::middleware(['web', 'tenant', 'auth'])->get('curriculum/resources/{resource}', ResourcePreviewController::class)->name('curriculum.resource');
    }
}
