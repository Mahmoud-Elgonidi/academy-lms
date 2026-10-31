<?php

namespace Modules\Quran\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Contracts\JourneyQuran;
use Modules\Learning\Contracts\LearningQuran;
use Modules\Quran\Bridges\QuranJourney;
use Modules\Quran\Bridges\QuranLearning;
use Modules\Quran\Bridges\QuranLessons;
use Modules\Quran\Console\QuranCommand;
use Modules\Quran\Console\TryRecitationCommand;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Hifz\HifzFromLessons;
use Modules\Quran\Listeners\QueueRecitationCheck;
use Modules\Quran\Live\HifzChanged;
use Modules\Quran\Live\LessonMushafChanged;
use Modules\Quran\Live\MushafChanged;
use Modules\Quran\Privacy\QuranData;
use Modules\Quran\QuranService;
use Modules\Quran\Texts\QuranFiles;
use Modules\Quran\Texts\QuranText;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Quran\LessonQuran;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Text\QuranSource;

/**
 * The Quran (docs/architecture/02, section 26): the platform's copy of its
 * text, the hifz map and its daily review, the shared mushaf and the mistakes
 * notebook, and the check of recited homework. It sits on top: nothing below
 * depends on it, and it reaches Learning, Classroom and the journey only
 * through the contracts they own (LearningQuran, JourneyQuran, LessonQuran),
 * whose defaults do nothing. Switched on per academy by its SaaS plan
 * (Feature::Quran, QuranArea): without it, its screens and routes close and
 * nothing new is written; what it holds stays.
 */
final class QuranServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/quran.php', 'quran');
        // The platform's copy of the Quran (Tanzil's simple text and metadata, resources/quran/README.md): fetched to
        // the server's storage (QuranFiles), else shipped with the code.
        $this->app->singleton(QuranText::class, fn () => new QuranText(self::quranDir(), dirname(__DIR__, 2).'/resources/quran'));
        $this->app->singleton(QuranSource::class, fn () => new QuranFiles(self::quranDir()));
        // Made each time over the copy in place now (a fetch, or a test, may put another one there).
        $this->app->bind(QuranApi::class, QuranService::class);

        // What the Quran adds to the modules below, through their own contracts.
        $this->app->bind(LearningQuran::class, QuranLearning::class);
        $this->app->bind(JourneyQuran::class, QuranJourney::class);
        $this->app->bind(LessonQuran::class, QuranLessons::class);
    }

    /** Where the server keeps the Quran's files it fetched (the storage volume, in the backups). */
    public static function quranDir(): string
    {
        return storage_path('app/platform/quran');
    }

    public function boot(): void
    {
        // The hifz map, the mistakes notebook and the checks of recited homework, for a learner's copy and erasure.
        $this->app->make(PersonalData::class)->add(QuranData::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'quran');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'quran');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        // A report on a lesson of the Quran reviews its pages on the learner's hifz map; recited homework gets its check.
        Event::listen(SessionReportSubmitted::class, [HifzFromLessons::class, 'reportSubmitted']);
        Event::listen(HomeworkSubmitted::class, QueueRecitationCheck::class);

        $signals = $this->app->make(LiveSignals::class);
        // The hifz map refreshes when a page is reviewed, marked or recited (the hifz triggers raise "hifz:{person}").
        $signals->on('hifz', fn (string $userId) => new HifzChanged($userId));
        $signals->on('mushaf', fn (string $userId) => new MushafChanged($userId));
        // A lesson's shared mushaf turned or marked: the class page follows on the lesson's own channel (Classroom's).
        $signals->on('lesson-mushaf', fn (string $sessionId) => new LessonMushafChanged($sessionId));

        if ($this->app->runningInConsole()) {
            $this->commands([QuranCommand::class, TryRecitationCommand::class]);
        }
    }
}
