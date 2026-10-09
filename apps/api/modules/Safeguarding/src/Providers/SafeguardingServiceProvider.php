<?php

namespace Modules\Safeguarding\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Console\FollowUpConcernsCommand;
use Modules\Safeguarding\Events\ConcernClosed;
use Modules\Safeguarding\Events\ConcernEscalated;
use Modules\Safeguarding\Events\ConcernReported;
use Modules\Safeguarding\Listeners\SendConcernNotifications;
use Modules\Safeguarding\Live\ConcernsChanged;
use Modules\Safeguarding\Live\MyConcernsChanged;
use Modules\Safeguarding\Privacy\ConcernsData;
use Modules\Safeguarding\Queries\ConcernRecordingHolds;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Keeping children safe (docs/architecture/02, section 15): concerns anyone
 * can raise and only the academy's safeguarding leads read. Sits on top:
 * nothing depends on it; it reaches lessons, people and recordings through
 * their contracts.
 */
final class SafeguardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ConcernRules::class);
        // A lesson named in an open concern keeps its recordings.
        $this->app->tag([ConcernRecordingHolds::class], RecordingHolds::SOURCES);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'safeguarding');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'safeguarding');
        // The concerns a person raised, for their copy (Privacy); a safeguarding record itself is kept.
        $this->app->make(PersonalData::class)->add(ConcernsData::class);

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendConcernNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('safeguarding_concern', fn (array $a, string $panel) => $panel === 'academy' ? route('filament.academy.pages.safeguarding', ['concern' => $a['id'] ?? null]) : null);
        $links->register('my_concerns', fn (array $a, string $panel) => match ($panel) {
            'family' => route('filament.family.pages.tell-us'),
            'teach' => route('filament.teach.pages.raise-concern'),
            default => null,
        });

        Event::listen(ConcernReported::class, [SendConcernNotifications::class, 'reported']);
        Event::listen(ConcernEscalated::class, [SendConcernNotifications::class, 'escalated']);
        Event::listen(ConcernClosed::class, [SendConcernNotifications::class, 'closed']);

        // Live screens: the leads' page on the academy's safeguarding channel, a reporter's list on their personal one.
        $live = $this->app->make(LiveSignals::class);
        $live->on('safeguarding', fn (string $academyId) => new ConcernsChanged($academyId));
        $live->on('concerns', fn (string $userId) => new MyConcernsChanged($userId));
        Broadcast::channel(ConcernsChanged::CHANNEL, fn (Authenticatable $user, string $academyId) => $academyId === $this->app->make(TenantContext::class)->id()
            && Gate::forUser($user)->allows(Ability::HandleSafeguarding->value));

        if ($this->app->runningInConsole()) {
            $this->commands([FollowUpConcernsCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('safeguarding:follow-up')->everyFiveMinutes()->withoutOverlapping()->onOneServer());
    }
}
