<?php

namespace Modules\Scheduling\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Identity\Contracts\Preferences\PreferenceDefinition;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Scheduling\AttendanceService;
use Modules\Scheduling\Console\MaterializeSessionsCommand;
use Modules\Scheduling\Console\SweepSessionsCommand;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftProposalChangesRequested;
use Modules\Scheduling\Contracts\Events\ShiftProposalCountered;
use Modules\Scheduling\Contracts\Events\ShiftProposalRejected;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Pages\ReviewShiftProposal;
use Modules\Scheduling\Listeners\FundSessionsFromNewCredit;
use Modules\Scheduling\Listeners\SendSchedulingNotifications;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The current academy's numbers, read each time (one process serves many academies).
        $this->app->bind(AttendancePolicy::class, fn () => PolicyVersion::settingsInForce()->attendance());
        $this->app->singleton(AttendanceApi::class, AttendanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'scheduling');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'scheduling');

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        $this->app->make(UserPreferences::class)->define(new PreferenceDefinition(
            key: LiveBoard::LAYOUT_PREFERENCE,
            label: 'scheduling::board.layout.label',
            options: array_combine(LiveBoard::LAYOUTS, array_map(fn (string $l) => "scheduling::board.layout.{$l}", LiveBoard::LAYOUTS)),
            default: 'table',
            hint: 'scheduling::board.layout.hint',
        ));

        Event::listen(CreditsGranted::class, FundSessionsFromNewCredit::class);

        SendSchedulingNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('shift_proposal', fn (array $a, string $panel) => $panel === 'academy' ? ReviewShiftProposal::getUrl(['proposal' => $a['id']], panel: 'academy') : null);
        foreach (['session', 'live_board'] as $screen) {
            $links->register($screen, fn (array $a, string $panel) => $panel === 'academy' ? LiveBoard::getUrl(panel: 'academy') : null);
        }
        Event::listen(SessionCancelled::class, [SendSchedulingNotifications::class, 'cancelled']);
        Event::listen(SessionRescheduled::class, [SendSchedulingNotifications::class, 'rescheduled']);
        Event::listen(ShiftProposalSubmitted::class, [SendSchedulingNotifications::class, 'proposalSubmitted']);
        foreach ([ShiftProposalApproved::class, ShiftProposalRejected::class, ShiftProposalChangesRequested::class, ShiftProposalCountered::class] as $answer) {
            Event::listen($answer, [SendSchedulingNotifications::class, 'proposalAnswered']);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SweepSessionsCommand::class, MaterializeSessionsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('scheduling:sweep')->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command('scheduling:materialize')->dailyAt('01:10')->withoutOverlapping()->onOneServer();
        });
    }
}
