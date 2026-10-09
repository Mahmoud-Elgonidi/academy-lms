<?php

namespace Modules\Support\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Evaluation\Contracts\TeacherComplaints;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Console\FollowUpTicketsCommand;
use Modules\Support\Contracts\Events\ComplaintValidated;
use Modules\Support\Contracts\Events\TicketAssigned;
use Modules\Support\Contracts\Events\TicketOpened;
use Modules\Support\Contracts\Events\TicketOverdue;
use Modules\Support\Contracts\Events\TicketReplied;
use Modules\Support\Contracts\Events\TicketResolved;
use Modules\Support\Filament\Pages\TicketPage;
use Modules\Support\Http\Controllers\TicketFileController;
use Modules\Support\Listeners\SendSupportNotifications;
use Modules\Support\Live\MyTicketsChanged;
use Modules\Support\Live\TicketsChanged;
use Modules\Support\Privacy\SupportData;
use Modules\Support\Queries\ComplaintRecordingHolds;
use Modules\Support\Queries\ConfirmedComplaints;
use Modules\Support\SupportRules;

/**
 * Support (docs/architecture/01, section 16; 04, M16): tickets and
 * complaints from families and teachers, answered by the academy within its
 * targets. It sits on top: it reaches lessons, people and messages through
 * their contracts, and nothing depends on it; Evaluation learns confirmed
 * complaints through its own TeacherComplaints contract, which this binds.
 */
final class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SupportRules::class);
        $this->app->bind(TeacherComplaints::class, ConfirmedComplaints::class);
        // And the classroom keeps a lesson's recordings while a complaint about it is open.
        $this->app->bind(RecordingHolds::class, ComplaintRecordingHolds::class);
    }

    public function boot(): void
    {
        // The tickets a person opened, for their copy (Privacy).
        $this->app->make(PersonalData::class)->add(SupportData::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'support');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'support');
        // A picture or PDF on a message, in the panels (the apps download it through the API).
        Route::middleware(['web', 'tenant', 'auth'])->get('support/files/{message}', TicketFileController::class)->name('support.file');
        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendSupportNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('support_ticket', fn (array $a, string $panel) => $panel === 'academy' && isset($a['id']) ? TicketPage::getUrl(['ticket' => $a['id']], panel: 'academy') : null);
        $links->register('ticket', fn (array $a, string $panel) => match ($panel) {
            'family' => route('filament.family.pages.help', ['ticket' => $a['id'] ?? null]),
            'teach' => route('filament.teach.pages.support', ['ticket' => $a['id'] ?? null]),
            default => null,
        });
        // A teacher hears a complaint confirmed: their numbers are where it counts.
        $links->register('my_performance', fn (array $a, string $panel) => $panel === 'teach' ? route('filament.teach.pages.performance') : null);

        Event::listen(TicketOpened::class, [SendSupportNotifications::class, 'opened']);
        Event::listen(TicketReplied::class, [SendSupportNotifications::class, 'replied']);
        Event::listen(TicketAssigned::class, [SendSupportNotifications::class, 'assigned']);
        Event::listen(TicketResolved::class, [SendSupportNotifications::class, 'resolved']);
        Event::listen(TicketOverdue::class, [SendSupportNotifications::class, 'overdue']);
        Event::listen(ComplaintValidated::class, [SendSupportNotifications::class, 'complaintValidated']);

        // Live screens: the academy's queue on its own channel, a requester's tickets on their personal one.
        $live = $this->app->make(LiveSignals::class);
        $live->on('support', fn (string $academyId) => new TicketsChanged($academyId));
        $live->on('tickets', fn (string $userId) => new MyTicketsChanged($userId));
        Broadcast::channel(TicketsChanged::CHANNEL, fn (Authenticatable $user, string $academyId) => $academyId === $this->app->make(TenantContext::class)->id()
            && Gate::forUser($user)->allows(Ability::HandleTickets->value));

        // The pictures and PDFs on messages count in the academy's storage.
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, fn () => (int) DB::table('ticket_messages')->sum('size'));

        if ($this->app->runningInConsole()) {
            $this->commands([FollowUpTicketsCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('support:follow-up')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
