<?php

namespace Modules\Crm\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Contracts\Events\LeadAssigned;
use Modules\Crm\Contracts\Events\TrialAssessed;
use Modules\Crm\Contracts\Events\TrialCompleted;
use Modules\Crm\Contracts\Events\TrialMissed;
use Modules\Crm\Filament\Pages\LeadPage;
use Modules\Crm\Filament\Teacher\MyTrials;
use Modules\Crm\Listeners\FollowLeads;
use Modules\Crm\Listeners\SendCrmNotifications;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Privacy\LeadsData;
use Modules\Crm\Queries\TrialConversion;
use Modules\Enrollment\Contracts\Events\EnrollmentCreated;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Contracts\TrialConversionRates;
use Modules\SharedKernel\Privacy\PersonalData;

/**
 * The CRM (docs/architecture/01, section 14; 04, M14): leads, their trial
 * lessons and the teacher's assessment, up to the subscription. It sits on
 * top: it follows Scheduling's and Enrollment's events, and nothing depends
 * on it (Scheduling asks it for trial conversion through its own contract).
 */
final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrialConversionRates::class, TrialConversion::class);
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
        Event::listen(LeadAssigned::class, [SendCrmNotifications::class, 'assigned']);
        Event::listen(TrialCompleted::class, [SendCrmNotifications::class, 'trialCompleted']);
        Event::listen(TrialMissed::class, [SendCrmNotifications::class, 'trialMissed']);
        Event::listen(TrialAssessed::class, [SendCrmNotifications::class, 'trialAssessed']);
    }
}
