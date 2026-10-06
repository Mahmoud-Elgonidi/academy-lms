<?php

namespace Modules\Evaluation\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Evaluation\Contracts\Events\LessonRated;
use Modules\Evaluation\Contracts\Events\LessonRatingDue;
use Modules\Evaluation\Filament\Family\RateLessonChip;
use Modules\Evaluation\Filament\Pages\TeacherPerformance;
use Modules\Evaluation\Filament\Teacher\MyPerformance;
use Modules\Evaluation\Http\Controllers\RateLessonController;
use Modules\Evaluation\Listeners\AskForRatings;
use Modules\Evaluation\Listeners\SendEvaluationNotifications;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Evaluation (docs/architecture/04, M12): the families' ratings of their
 * lessons and the teachers' KPIs. It listens to Scheduling and reads the
 * lessons, attendance and reports; nothing below it depends on it.
 */
final class EvaluationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'evaluation');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'evaluation');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        SendEvaluationNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        // "How was the lesson?" carries each person's own link to rate it, without signing in.
        $links->registerPersonal('lesson_rating', function (array $action, string $userId) {
            $path = URL::temporarySignedRoute('evaluation.rate', now()->addDays(RateLessonController::LINK_DAYS), ['session' => $action['id'] ?? '', 'u' => $userId], absolute: false);

            return $this->app->make(TenantDirectory::class)->baseUrl($this->app->make(TenantContext::class)->require()).$path;
        });
        $links->register('lesson_rating', fn (array $a, string $panel) => match ($panel) {
            'family' => route('filament.family.pages.lessons'),
            'teach' => MyPerformance::getUrl(panel: 'teach'),
            'academy' => ($teacher = DB::table('sessions')->where('id', $a['id'] ?? null)->value('teacher_id')) ? TeacherPerformance::getUrl(['teacher' => $teacher], panel: 'academy') : null,
            default => null,
        });
        $links->register('teacher_performance', fn (array $a, string $panel) => $panel === 'academy' && isset($a['id']) ? TeacherPerformance::getUrl(['teacher' => $a['id']], panel: 'academy') : null);

        RateLessonChip::register();

        Event::listen(SessionFinalized::class, AskForRatings::class);
        Event::listen(LessonRatingDue::class, [SendEvaluationNotifications::class, 'ratingDue']);
        Event::listen(LessonRated::class, [SendEvaluationNotifications::class, 'rated']);
    }
}
