<?php

namespace Modules\Classroom\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Modules\Classroom\Console\ReconcileClassroomsCommand;
use Modules\Classroom\Http\Controllers\LiveKitWebhookController;
use Modules\Classroom\Queries\VideoMinutes;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;

/** The live classroom (docs/architecture/01-features.md, section 9; 03, section 6). */
final class ClassroomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/classroom.php', 'classroom');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'classroom');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'classroom');
        // The plan's video minutes count the lessons held on our own video this month.
        $this->app->make(UsageMeters::class)->add(Limit::VideoMinutes, fn (UsagePeriod $month) => $this->app->make(VideoMinutes::class)->in($month));

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        Route::prefix('api/v1')->middleware(['api', 'tenant', 'locale', 'timezone'])->group(__DIR__.'/../../routes/api.php');
        // Signed by LiveKit, not by a user; the academy comes from the room name.
        Route::post('webhooks/livekit', LiveKitWebhookController::class)->middleware('api')->name('classroom.webhooks.livekit');

        // "Join now" and reminders carry each person's own signed link to the class page.
        $this->app->make(NotificationLinks::class)->registerPersonal('session', function (array $action, string $userId) {
            $lesson = $this->app->make(AttendanceApi::class)->lesson($action['id'] ?? '');
            if ($lesson === null) {
                return null;
            }
            $path = URL::temporarySignedRoute('classroom.page', $lesson->endsAt->addMinutes(30), ['session' => $lesson->id, 'u' => $userId], absolute: false);

            return $this->app->make(TenantDirectory::class)->baseUrl($this->app->make(TenantContext::class)->require()).$path;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileClassroomsCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('classroom:reconcile')->everyMinute()->withoutOverlapping()->onOneServer();
        });
    }
}
