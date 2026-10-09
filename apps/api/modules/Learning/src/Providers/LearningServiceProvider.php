<?php

namespace Modules\Learning\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Enrollment\Contracts\Events\CurriculumAttached;
use Modules\Learning\Console\SweepLearningCommand;
use Modules\Learning\Contracts\Events\CheerSent;
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
use Modules\Learning\Http\Web\MaterialController;
use Modules\Learning\Http\Web\SubmissionFileController;
use Modules\Learning\LearningService;
use Modules\Learning\Listeners\OpenReportWhenSessionCloses;
use Modules\Learning\Listeners\RebuildProgressOnProgramChange;
use Modules\Learning\Listeners\SendLearningNotifications;
use Modules\Learning\Live\HomeworkChanged;
use Modules\Learning\Privacy\LearningData;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;

/** Reports, homework and progress on the roadmap (docs/architecture/01, "التقارير والواجبات"; 02, section 6). */
final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LearningApi::class, LearningService::class);
    }

    public function boot(): void
    {
        // A learner's homework, reports, challenges and cheers, for their copy and erasure (Privacy).
        $this->app->make(PersonalData::class)->add(LearningData::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'learning');
        // The plan's storage counts the handed-in homework (recordings and files) kept for the academy.
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, fn () => (int) DB::table('homework_submissions')->sum('size'));
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'learning');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');
        // Files handed in, for the panels (session sign-in); the apps use the API route.
        Route::middleware(['web', 'tenant'])->get('learning/files/{submission}', SubmissionFileController::class)->name('learning.files');
        // A lesson's file behind a signed address (MaterialLinks), for whoever was shown the lesson: no sign-in needed.
        Route::middleware(['web', 'tenant', 'signed:relative', 'throttle:links'])->get('materials/{resource}', MaterialController::class)->name('learning.material');

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
        Event::listen(CheerSent::class, [SendLearningNotifications::class, 'cheerSent']);
        Event::listen(CurriculumAttached::class, RebuildProgressOnProgramChange::class);

        // Homework pages refresh when homework changes (the homework triggers raise "homework:{person}").
        $this->app->make(LiveSignals::class)->on('homework', fn (string $userId) => new HomeworkChanged($userId));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepLearningCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('learning:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
