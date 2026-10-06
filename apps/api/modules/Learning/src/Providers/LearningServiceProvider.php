<?php

namespace Modules\Learning\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Enrollment\Contracts\Events\CurriculumAttached;
use Modules\Learning\Console\SweepLearningCommand;
use Modules\Learning\Contracts\Events\HomeworkAssigned;
use Modules\Learning\Contracts\Events\HomeworkReviewed;
use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Contracts\Events\NodeMastered;
use Modules\Learning\Contracts\Events\SessionReportOverdue;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Filament\LearningPlugin;
use Modules\Learning\Filament\Pages\ReportsOversight;
use Modules\Learning\Filament\Teacher\HomeworkToMark;
use Modules\Learning\Filament\Teacher\WriteReport;
use Modules\Learning\Http\Web\SubmissionFileController;
use Modules\Learning\LearningService;
use Modules\Learning\Listeners\OpenReportWhenSessionCloses;
use Modules\Learning\Listeners\RebuildProgressOnProgramChange;
use Modules\Learning\Listeners\SendLearningNotifications;
use Modules\Scheduling\Contracts\Events\SessionFinalized;

/** Reports, homework and progress on the roadmap (docs/architecture/01, "التقارير والواجبات"; 02, section 6). */
final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LearningApi::class, LearningService::class);
    }

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

        SendLearningNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('write_report', fn (array $a, string $panel) => $panel === 'teach' ? WriteReport::getUrl(['report' => $a['id']], panel: 'teach') : ReportsOversight::getUrl());
        $links->register('homework_to_mark', fn (array $a, string $panel) => $panel === 'teach' ? HomeworkToMark::getUrl(panel: 'teach') : null);
        Event::listen(SessionReportSubmitted::class, [SendLearningNotifications::class, 'reportSubmitted']);
        Event::listen(HomeworkAssigned::class, [SendLearningNotifications::class, 'homeworkAssigned']);
        Event::listen(HomeworkReviewed::class, [SendLearningNotifications::class, 'homeworkReviewed']);
        Event::listen(NodeMastered::class, [SendLearningNotifications::class, 'nodeMastered']);
        Event::listen(HomeworkSubmitted::class, [SendLearningNotifications::class, 'homeworkSubmitted']);
        Event::listen(SessionReportOverdue::class, [SendLearningNotifications::class, 'reportOverdue']);
        Event::listen(CurriculumAttached::class, RebuildProgressOnProgramChange::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SweepLearningCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('learning:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
