<?php

namespace Modules\People\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\Events\UserInvited;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Contracts\SignInGate;
use Modules\People\Actions\SendWelcome;
use Modules\People\Checks\Events\TeacherCheckDecided;
use Modules\People\Checks\Events\TeacherCheckExpiring;
use Modules\People\Checks\Events\TeacherCheckSubmitted;
use Modules\People\Consent\ConsentSignInGate;
use Modules\People\Console\RemindTeacherChecksCommand;
use Modules\People\Contracts\Events\ConsentRequested;
use Modules\People\Contracts\Events\ConsentWithdrawn;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Family\SessionFamilyView;
use Modules\People\Filament\Family\LearnerSwitcher;
use Modules\People\Http\Controllers\ConsentLinkController;
use Modules\People\Listeners\SendCheckNotifications;
use Modules\People\Listeners\SendConsentNotifications;
use Modules\People\Live\ConsentsChanged;
use Modules\People\Live\MyChecksChanged;
use Modules\People\Live\TeacherChecksChanged;
use Modules\People\PeopleService;
use Modules\People\Privacy\ProfileData;
use Modules\People\Setup\TeacherStep;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Tenancy\TenantContext;

final class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PeopleApi::class, PeopleService::class);
        $this->app->scoped(FamilyView::class, SessionFamilyView::class);
        // A child waits for a guardian's consent before signing in (Identity asks; People knows who).
        $this->app->scoped(SignInGate::class, ConsentSignInGate::class);
    }

    public function boot(): void
    {
        // A person's profile and guardian links, for their copy and erasure (Privacy).
        $this->app->make(PersonalData::class)->add(ProfileData::class);
        // The new academy's checklist: a first teacher.
        $this->app->make(SetupChecklist::class)->add(TeacherStep::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'people');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'people');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        Livewire::component('people.learner-switcher', LearnerSwitcher::class);

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendConsentNotifications::templates($this->app->make(NotificationsApi::class));
        SendWelcome::templates($this->app->make(NotificationsApi::class));
        SendCheckNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        // Each person's own link to choose their password (Identity's welcome page), on the academy's host.
        $links->registerPersonal('welcome', fn (array $action, string $userId) => $this->app->make(IdentityApi::class)->welcomeLink($userId));
        // The request carries each guardian's own link to read and agree, without signing in.
        $links->registerPersonal('guardian_consent', function (array $action, string $userId) {
            $path = URL::temporarySignedRoute('people.consent', now()->addDays(ConsentLinkController::LINK_DAYS), ['student' => $action['id'] ?? '', 'g' => $userId], absolute: false);

            return $this->app->make(TenantDirectory::class)->baseUrl($this->app->make(TenantContext::class)->require()).$path;
        });
        $links->register('guardian_consent', fn (array $a, string $panel) => $panel === 'family' ? route('filament.family.pages.privacy') : null);
        $links->register('guardian_consents', fn (array $a, string $panel) => $panel === 'academy' ? route('filament.academy.pages.guardian-consents') : null);
        // Teachers' vetting documents: the academy's checks page, and the teacher's own.
        $links->register('teacher_checks', fn (array $a, string $panel) => $panel === 'academy' ? route('filament.academy.pages.teacher-checks') : null);
        $links->register('my_checks', fn (array $a, string $panel) => $panel === 'teach' ? route('filament.teach.pages.my-documents') : null);

        Event::listen(ConsentRequested::class, [SendConsentNotifications::class, 'requested']);
        Event::listen(ConsentWithdrawn::class, [SendConsentNotifications::class, 'withdrawn']);
        Event::listen(TeacherCheckSubmitted::class, [SendCheckNotifications::class, 'submitted']);
        Event::listen(TeacherCheckDecided::class, [SendCheckNotifications::class, 'decided']);
        Event::listen(TeacherCheckExpiring::class, [SendCheckNotifications::class, 'expiring']);
        // A new member of the academy's team (Identity's Team screen) gets the same welcome, in the staff's words.
        Event::listen(UserInvited::class, fn (UserInvited $e) => $this->app->make(SendWelcome::class)->handle([$e->userId], $e->again));

        $live = $this->app->make(LiveSignals::class);
        $live->on('consents', fn (string $userId) => new ConsentsChanged($userId));
        $live->on('checks', fn (string $academyId) => new TeacherChecksChanged($academyId));
        $live->on('my-checks', fn (string $userId) => new MyChecksChanged($userId));
        Broadcast::channel(TeacherChecksChanged::CHANNEL, fn (Authenticatable $user, string $academyId) => $academyId === $this->app->make(TenantContext::class)->id()
            && Gate::forUser($user)->allows(Ability::HandleSafeguarding->value));

        // The scans count in the academy's storage.
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, fn () => (int) DB::table('teacher_checks')->sum('size'));

        if ($this->app->runningInConsole()) {
            $this->commands([RemindTeacherChecksCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('people:checks')->hourly()->withoutOverlapping()->onOneServer());
    }
}
