<?php

namespace Modules\Crm\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Console\FollowUpLeadsCommand;
use Modules\Crm\Contracts\Events\LeadAssigned;
use Modules\Crm\Contracts\Events\LeadStageChanged;
use Modules\Crm\Contracts\Events\TrialAssessed;
use Modules\Crm\Contracts\Events\TrialCancelledByAcademy;
use Modules\Crm\Contracts\Events\TrialCancelledByFamily;
use Modules\Crm\Contracts\Events\TrialCompleted;
use Modules\Crm\Contracts\Events\TrialMissed;
use Modules\Crm\Filament\Pages\LeadPage;
use Modules\Crm\Filament\Teacher\MyTrials;
use Modules\Crm\Listeners\FollowLeads;
use Modules\Crm\Listeners\FollowSiteRequests;
use Modules\Crm\Listeners\SendCrmNotifications;
use Modules\Crm\Listeners\TellFamilyAfterTrial;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Privacy\LeadsData;
use Modules\Crm\Queries\TrialConversion;
use Modules\Crm\Support\StartOffersFromTrials;
use Modules\Enrollment\Contracts\Events\EnrollmentCreated;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Contracts\StartOffers;
use Modules\Scheduling\Contracts\TrialConversionRates;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\Site\Contracts\Events\JoinRequested;

/**
 * The CRM (docs/architecture/01, section 14; 04, M14): leads, their trial
 * lessons and the teacher's assessment, up to the subscription. It sits on
 * top: it follows Scheduling's, Enrollment's and the academy's page's (Site)
 * events, and nothing depends on it (Scheduling asks it for trial conversion
 * through its own contract).
 */
final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrialConversionRates::class, TrialConversion::class);
        // The trial's offer goes on the course the family starts on its own (J3).
        $this->app->bind(StartOffers::class, StartOffersFromTrials::class);
    }

    public function boot(): void
    {
        // The enquiry a person came through, for their copy and erasure (Privacy).
        $this->app->make(PersonalData::class)->add(LeadsData::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'crm');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'crm');
        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendCrmNotifications::templates($this->app->make(NotificationsApi::class));
        TellFamilyAfterTrial::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('lead', fn (array $a, string $panel) => $panel === 'academy' && isset($a['id']) ? LeadPage::getUrl(['lead' => $a['id']], panel: 'academy') : null);
        $links->register('trial', fn (array $a, string $panel) => match ($panel) {
            'teach' => MyTrials::getUrl(panel: 'teach'),
            'academy' => ($lead = LeadTrial::query()->whereKey($a['id'] ?? null)->value('lead_id')) ? LeadPage::getUrl(['lead' => $lead], panel: 'academy') : null,
            default => null,
        });

        Event::listen(TrialLessonBooked::class, [FollowLeads::class, 'trialBooked']);
        Event::listen(SessionRescheduled::class, [FollowLeads::class, 'rescheduled']);
        Event::listen(SessionCancelled::class, [FollowLeads::class, 'cancelled']);
        Event::listen(SessionFinalized::class, [FollowLeads::class, 'finalized']);
        Event::listen(EnrollmentCreated::class, [FollowLeads::class, 'enrolled']);
        // A free trial asked for on the academy's page (Site) becomes a lead.
        Event::listen(JoinRequested::class, [FollowSiteRequests::class, 'joinRequested']);
        Event::listen(LeadAssigned::class, [SendCrmNotifications::class, 'assigned']);
        Event::listen(TrialCompleted::class, [SendCrmNotifications::class, 'trialCompleted']);
        Event::listen(TrialMissed::class, [SendCrmNotifications::class, 'trialMissed']);
        Event::listen(TrialAssessed::class, [SendCrmNotifications::class, 'trialAssessed']);
        Event::listen(TrialCancelledByFamily::class, [SendCrmNotifications::class, 'trialCancelled']);
        // After the trial (J2): the family's report (and the offer made with it), a missed trial, an apology with a new time.
        Event::listen(TrialAssessed::class, [TellFamilyAfterTrial::class, 'assessed']);
        Event::listen(TrialMissed::class, [TellFamilyAfterTrial::class, 'missed']);
        Event::listen(TrialCancelledByAcademy::class, [TellFamilyAfterTrial::class, 'cancelled']);
        // The page's request follows its lead (one status).
        Event::listen(LeadStageChanged::class, [FollowSiteRequests::class, 'stageChanged']);

        if ($this->app->runningInConsole()) {
            $this->commands([FollowUpLeadsCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Leads nobody answered within the academy's hours, trials still waiting for their assessment, offers ending within a day.
            $schedule->command('crm:follow-up')->hourlyAt(17)->withoutOverlapping()->onOneServer();
        });
    }
}
