<?php

namespace Modules\Learning\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Enrollment\Contracts\Events\CurriculumAttached;
use Modules\Learning\Console\SendWeeklyDigestsCommand;
use Modules\Learning\Console\SweepLearningCommand;
use Modules\Learning\Console\TryRecitationCommand;
use Modules\Learning\Contracts\Events\CheerSent;
use Modules\Learning\Contracts\Events\HomeworkAssigned;
use Modules\Learning\Contracts\Events\HomeworkReviewed;
use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Contracts\Events\NodeMastered;
use Modules\Learning\Contracts\Events\SessionReportOverdue;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Contracts\JourneyGames;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Filament\Family\FamilyJourney;
use Modules\Learning\Filament\LearningPlugin;
use Modules\Learning\Filament\Pages\ReportsOversight;
use Modules\Learning\Filament\Teacher\HomeworkToMark;
use Modules\Learning\Filament\Teacher\WriteReport;
use Modules\Learning\Hifz\HifzFromLessons;
use Modules\Learning\Http\Web\MaterialController;
use Modules\Learning\Http\Web\SubmissionFileController;
use Modules\Learning\Http\Web\WeeklyVoiceController;
use Modules\Learning\LearningService;
use Modules\Learning\Listeners\OpenReportWhenSessionCloses;
use Modules\Learning\Listeners\QueueRecitationCheck;
use Modules\Learning\Listeners\RebuildProgressOnProgramChange;
use Modules\Learning\Listeners\SendLearningNotifications;
use Modules\Learning\Live\HifzChanged;
use Modules\Learning\Live\HomeworkChanged;
use Modules\Learning\Live\MushafChanged;
use Modules\Learning\Live\PracticeChanged;
use Modules\Learning\Privacy\LearningData;
use Modules\Learning\Queries\Journey\NoJourneyGames;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Reports, homework and progress on the roadmap (docs/architecture/01, "التقارير والواجبات"; 02, section 6). */
final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LearningApi::class, LearningService::class);
        // The journey without games until the Games module binds its own.
        $this->app->bindIf(JourneyGames::class, NoJourneyGames::class);
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
        // The recording of the week a guardian hears from their weekly note: their own signed link, no sign-in.
        Route::middleware(['web', 'tenant', 'signed:relative', 'throttle:links', 'throttle:20,1'])->group(function (): void {
            Route::get('voice/{submission}', [WeeklyVoiceController::class, 'show'])->name('learning.voice');
            Route::get('voice/{submission}/file', [WeeklyVoiceController::class, 'file'])->name('learning.voice.file');
        });

        LearningPlugin::registerAccountSection();
        LearningPlugin::registerJourneyStyle();

        Event::listen(SessionFinalized::class, OpenReportWhenSessionCloses::class);

        SendLearningNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('write_report', fn (array $a, string $panel) => $panel === 'teach' ? WriteReport::getUrl(['report' => $a['id']], panel: 'teach') : ReportsOversight::getUrl());
        $links->register('homework_to_mark', fn (array $a, string $panel) => $panel === 'teach' ? HomeworkToMark::getUrl(panel: 'teach') : null);
        // The journey, in the family area (a student's home, the journey page of guardians); the games and the old challenges open it too.
        foreach (['journey', 'games', 'challenges'] as $screen) {
            $links->register($screen, fn (array $a, string $panel) => $panel === 'family' ? FamilyJourney::getUrl(panel: 'family') : null);
        }
        // The weekly note's link to hear the recording of the week: each guardian's own, for two weeks.
        $links->registerPersonal('journey', function (array $action, string $userId): ?string {
            if (! is_string($action['voice'] ?? null)) {
                return null;
            }
            $path = URL::temporarySignedRoute('learning.voice', now()->addDays(WeeklyVoiceController::LINK_DAYS), ['submission' => $action['voice'], 'u' => $userId], absolute: false);

            return $this->app->make(TenantDirectory::class)->baseUrl($this->app->make(TenantContext::class)->require()).$path;
        });
        Event::listen(SessionReportSubmitted::class, [SendLearningNotifications::class, 'reportSubmitted']);
        Event::listen(HomeworkAssigned::class, [SendLearningNotifications::class, 'homeworkAssigned']);
        Event::listen(HomeworkReviewed::class, [SendLearningNotifications::class, 'homeworkReviewed']);
        Event::listen(NodeMastered::class, [SendLearningNotifications::class, 'nodeMastered']);
        Event::listen(HomeworkSubmitted::class, [SendLearningNotifications::class, 'homeworkSubmitted']);
        Event::listen(HomeworkSubmitted::class, QueueRecitationCheck::class);
        // A report on a lesson of the Quran reviews its pages on the learner's hifz map.
        Event::listen(SessionReportSubmitted::class, [HifzFromLessons::class, 'reportSubmitted']);
        Event::listen(SessionReportOverdue::class, [SendLearningNotifications::class, 'reportOverdue']);
        Event::listen(CheerSent::class, [SendLearningNotifications::class, 'cheerSent']);
        Event::listen(SessionFinalized::class, [SendLearningNotifications::class, 'lessonStars']);
        Event::listen(CurriculumAttached::class, RebuildProgressOnProgramChange::class);

        // Homework pages refresh when homework changes (the homework triggers raise "homework:{person}").
        $this->app->make(LiveSignals::class)->on('homework', fn (string $userId) => new HomeworkChanged($userId));
        $this->app->make(LiveSignals::class)->on('practice', fn (string $userId) => new PracticeChanged($userId));
        // The hifz map refreshes when a page is reviewed, marked or recited (the hifz triggers raise "hifz:{person}").
        $this->app->make(LiveSignals::class)->on('hifz', fn (string $userId) => new HifzChanged($userId));
        $this->app->make(LiveSignals::class)->on('mushaf', fn (string $userId) => new MushafChanged($userId));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepLearningCommand::class, TryRecitationCommand::class, SendWeeklyDigestsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('learning:sweep')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('learning:digests')->hourlyAt(11)->withoutOverlapping()->onOneServer();
        });
    }
}
