<?php

namespace Modules\People\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Contracts\SignInGate;
use Modules\People\Actions\SendWelcome;
use Modules\People\Consent\ConsentSignInGate;
use Modules\People\Contracts\Events\ConsentRequested;
use Modules\People\Contracts\Events\ConsentWithdrawn;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Family\SessionFamilyView;
use Modules\People\Filament\Family\LearnerSwitcher;
use Modules\People\Http\Controllers\ConsentLinkController;
use Modules\People\Listeners\SendConsentNotifications;
use Modules\People\Live\ConsentsChanged;
use Modules\People\PeopleService;
use Modules\People\Privacy\ProfileData;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
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

        Event::listen(ConsentRequested::class, [SendConsentNotifications::class, 'requested']);
        Event::listen(ConsentWithdrawn::class, [SendConsentNotifications::class, 'withdrawn']);
        $this->app->make(LiveSignals::class)->on('consents', fn (string $userId) => new ConsentsChanged($userId));
    }
}
