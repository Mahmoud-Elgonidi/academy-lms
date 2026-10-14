<?php

namespace Modules\Privacy\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\Events\ContactChanged;
use Modules\Privacy\Console\SweepDataRequestsCommand;
use Modules\Privacy\Contracts\Events\AccountErased;
use Modules\Privacy\Contracts\Events\CorrectionAnswered;
use Modules\Privacy\Contracts\Events\CorrectionRequested;
use Modules\Privacy\Contracts\Events\DataExportReady;
use Modules\Privacy\Contracts\Events\ErasureApproved;
use Modules\Privacy\Contracts\Events\ErasureRefused;
use Modules\Privacy\Contracts\Events\ErasureRequested;
use Modules\Privacy\Http\Controllers\CopyDownloadController;
use Modules\Privacy\Http\Controllers\LegalController;
use Modules\Privacy\Listeners\SendPrivacyNotifications;
use Modules\Privacy\Live\MyDataChanged;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Setup\PrivacyDetailsStep;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Setup\SetupChecklist;

/**
 * Privacy (docs/architecture/03, section 11): people's rights over their
 * data and the pages that tell them about it. A copy of everything the
 * academy holds about a person, erasure on request (answered by the
 * academy, then the account anonymised), and the public privacy notice,
 * terms and account-deletion page the stores ask for. Every module says
 * what it holds through SharedKernel's PersonalData; this sits on top and
 * nothing depends on it.
 */
final class PrivacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/privacy.php', 'privacy');
        $this->app->scoped(PrivacyRules::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'privacy');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'privacy');
        // The new academy's checklist: who it is on its privacy notice and terms.
        $this->app->make(SetupChecklist::class)->add(PrivacyDetailsStep::class);

        // The public pages: on an academy's host its own, on the platform's the platform's (no sign-in).
        Route::middleware(['web', 'throttle:links'])->group(function () {
            Route::get('privacy', [LegalController::class, 'privacy'])->name('privacy.notice');
            Route::get('terms', [LegalController::class, 'terms'])->name('privacy.terms');
            Route::get('account-deletion', [LegalController::class, 'deletion'])->name('privacy.deletion');
        });
        // A person's copy of their data, through its signed address (the panels, the apps and the portal hand it out).
        Route::middleware(['web', 'tenant', 'signed:relative', 'throttle:links'])->get('privacy/copies/{request}', CopyDownloadController::class)->name('privacy.copy');
        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendPrivacyNotifications::templates($this->app->make(NotificationsApi::class));
        Event::listen(DataExportReady::class, [SendPrivacyNotifications::class, 'exportReady']);
        Event::listen(ErasureRequested::class, [SendPrivacyNotifications::class, 'erasureRequested']);
        Event::listen(ErasureApproved::class, [SendPrivacyNotifications::class, 'erasureApproved']);
        Event::listen(ErasureRefused::class, [SendPrivacyNotifications::class, 'erasureRefused']);
        Event::listen(AccountErased::class, [SendPrivacyNotifications::class, 'accountErased']);
        Event::listen(CorrectionRequested::class, [SendPrivacyNotifications::class, 'correctionRequested']);
        Event::listen(CorrectionAnswered::class, [SendPrivacyNotifications::class, 'correctionAnswered']);
        Event::listen(ContactChanged::class, [SendPrivacyNotifications::class, 'contactChanged']);

        $links = $this->app->make(NotificationLinks::class);
        $links->register('my_data', fn (array $a, string $panel) => match ($panel) {
            'family' => route('filament.family.pages.my-data'),
            'teach' => route('filament.teach.pages.my-data'),
            default => null,
        });
        // A changed email or number opens the person's own settings, where both are.
        $links->register('account', fn (array $a, string $panel) => in_array($panel, ['academy', 'teach', 'family'], true) ? route("filament.{$panel}.auth.profile") : null);
        $links->register('data_request', fn (array $a, string $panel) => $panel === 'academy' ? route('filament.academy.pages.data-requests', ['request' => $a['id'] ?? null]) : null);

        // "My data" follows its requests on the person's own channel (a copy ready, the academy's answer).
        $this->app->make(LiveSignals::class)->on('privacy', fn (string $userId) => new MyDataChanged($userId));

        if ($this->app->runningInConsole()) {
            $this->commands([SweepDataRequestsCommand::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('privacy:sweep')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
