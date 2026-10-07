<?php

namespace Modules\Scheduling\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Identity\Contracts\Preferences\PreferenceDefinition;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Scheduling\Actions\BringExistingSchedule;
use Modules\Scheduling\AttendanceService;
use Modules\Scheduling\Console\MaterializeSessionsCommand;
use Modules\Scheduling\Console\PlanDstCommand;
use Modules\Scheduling\Console\SweepSessionsCommand;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\DstShiftDecided;
use Modules\Scheduling\Contracts\Events\DstShiftUpcoming;
use Modules\Scheduling\Contracts\Events\HolidayDeclared;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\SessionTeacherSubstituted;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftProposalChangesRequested;
use Modules\Scheduling\Contracts\Events\ShiftProposalCountered;
use Modules\Scheduling\Contracts\Events\ShiftProposalRejected;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Contracts\Events\SlotTimesShifted;
use Modules\Scheduling\Contracts\Events\TeacherLessonsShifted;
use Modules\Scheduling\Contracts\Events\TimeOffDecided;
use Modules\Scheduling\Contracts\Events\TimeOffRequested;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Contracts\ScheduleImport;
use Modules\Scheduling\Contracts\TrialConversionRates;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Pages\Holidays;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Pages\ReviewShiftProposal;
use Modules\Scheduling\Filament\Pages\TimeOffReview;
use Modules\Scheduling\Filament\Teacher\MyTimeOff;
use Modules\Scheduling\Filament\Teacher\MyWeek;
use Modules\Scheduling\Listeners\FundSessionsFromNewCredit;
use Modules\Scheduling\Listeners\SendSchedulingNotifications;
use Modules\Scheduling\Live\BoardChanged;
use Modules\Scheduling\Live\TeacherLessonsChanged;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\TenantContext;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The current academy's numbers, read each time (one process serves many academies).
        $this->app->bind(AttendancePolicy::class, fn () => PolicyVersion::settingsInForce()->attendance());
        $this->app->singleton(AttendanceApi::class, AttendanceService::class);
        $this->app->bind(ScheduleImport::class, BringExistingSchedule::class);
        // Trial conversion is the CRM's to know; without it no teacher gets ahead in a trial search.
        $this->app->bindIf(TrialConversionRates::class, fn () => new class implements TrialConversionRates
        {
            public function forTeachers(array $teacherIds): array
            {
                return [];
            }
        });
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

        // Live screens (live:relay): what a change to lessons means, and who may hear it.
        $live = $this->app->make(LiveSignals::class);
        $live->on('board', fn (string $academyId) => new BoardChanged($academyId));
        $live->on('lessons', fn (string $teacherId) => new TeacherLessonsChanged($teacherId));
        // The live board's signals: only for someone of that academy who may see the board.
        Broadcast::channel(BoardChanged::CHANNEL, fn (Authenticatable $user, string $academyId) => $academyId === $this->app->make(TenantContext::class)->id()
            && Gate::forUser($user)->allows(Ability::ViewLiveBoard->value));

        SendSchedulingNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('shift_proposal', fn (array $a, string $panel) => $panel === 'academy' ? ReviewShiftProposal::getUrl(['proposal' => $a['id']], panel: 'academy') : null);
        $links->register('live_board', fn (array $a, string $panel) => $panel === 'academy' ? LiveBoard::getUrl(panel: 'academy') : null);
        $links->register('session', fn (array $a, string $panel) => match ($panel) {
            'academy' => LiveBoard::getUrl(panel: 'academy'),
            'teach' => MyWeek::getUrl(panel: 'teach'),
            'family' => FamilyLessons::getUrl(panel: 'family'),
            default => null,
        });
        $links->register('time_off', fn (array $a, string $panel) => match ($panel) {
            'academy' => TimeOffReview::getUrl(['timeOff' => $a['id']], panel: 'academy'),
            'teach' => MyTimeOff::getUrl(panel: 'teach'),
            default => null,
        });
        $links->register('holiday', fn (array $a, string $panel) => match ($panel) {
            'academy' => Holidays::getUrl(panel: 'academy'),
            'teach' => MyWeek::getUrl(panel: 'teach'),
            'family' => FamilyLessons::getUrl(panel: 'family'),
            default => null,
        });
        Event::listen(SessionCancelled::class, [SendSchedulingNotifications::class, 'cancelled']);
        Event::listen(SessionRescheduled::class, [SendSchedulingNotifications::class, 'rescheduled']);
        Event::listen(ShiftProposalSubmitted::class, [SendSchedulingNotifications::class, 'proposalSubmitted']);
        Event::listen(TimeOffRequested::class, [SendSchedulingNotifications::class, 'timeOffRequested']);
        Event::listen(TimeOffDecided::class, [SendSchedulingNotifications::class, 'timeOffDecided']);
        Event::listen(SessionTeacherSubstituted::class, [SendSchedulingNotifications::class, 'substituted']);
        Event::listen(HolidayDeclared::class, [SendSchedulingNotifications::class, 'holidayDeclared']);
        Event::listen(DstShiftUpcoming::class, [SendSchedulingNotifications::class, 'dstUpcoming']);
        Event::listen(DstShiftDecided::class, [SendSchedulingNotifications::class, 'dstDecided']);
        Event::listen(SlotTimesShifted::class, [SendSchedulingNotifications::class, 'slotShifted']);
        Event::listen(TeacherLessonsShifted::class, [SendSchedulingNotifications::class, 'teacherShifted']);
        Event::listen(TrialLessonBooked::class, [SendSchedulingNotifications::class, 'trialBooked']);
        foreach ([ShiftProposalApproved::class, ShiftProposalRejected::class, ShiftProposalChangesRequested::class, ShiftProposalCountered::class] as $answer) {
            Event::listen($answer, [SendSchedulingNotifications::class, 'proposalAnswered']);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SweepSessionsCommand::class, MaterializeSessionsCommand::class, PlanDstCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('scheduling:sweep')->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command('scheduling:materialize')->dailyAt('01:10')->withoutOverlapping()->onOneServer();
            $schedule->command('scheduling:dst')->dailyAt('01:40')->withoutOverlapping()->onOneServer();
        });
    }
}
