<?php

namespace Modules\Learning\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Enrollment\Contracts\Events\CurriculumAttached;
use Modules\Learning\Console\SweepLearningCommand;
use Modules\Learning\Filament\LearningPlugin;
use Modules\Learning\Http\Web\SubmissionFileController;
use Modules\Learning\Listeners\OpenReportWhenSessionCloses;
use Modules\Learning\Listeners\RebuildProgressOnProgramChange;
use Modules\Scheduling\Contracts\Events\SessionFinalized;

/** Reports, homework and progress on the roadmap (docs/architecture/01, "التقارير والواجبات"; 02, section 6). */
final class LearningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'learning');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'learning');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');
        // Files handed in, for the panels (session sign-in); the apps use the API route.
        Route::middleware(['web', 'tenant'])->get('learning/files/{submission}', SubmissionFileController::class)->name('learning.files');

        LearningPlugin::registerAccountSection();

        Event::listen(SessionFinalized::class, OpenReportWhenSessionCloses::class);
        Event::listen(CurriculumAttached::class, RebuildProgressOnProgramChange::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SweepLearningCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('learning:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
