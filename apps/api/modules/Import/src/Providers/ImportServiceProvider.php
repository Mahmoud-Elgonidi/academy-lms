<?php

namespace Modules\Import\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Import\Contracts\Events\PeopleImported;
use Modules\Import\Listeners\SendWelcomes;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Import (docs/architecture/01: "استيراد من CSV/Excel"): teachers, families
 * and running schedules from an academy's spreadsheets, through each
 * module's contracts. It sits on top; nothing depends on it.
 */
final class ImportServiceProvider extends ServiceProvider
{
    /** How long a welcome link works. */
    public const WELCOME_DAYS = 14;

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'import');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'import');

        SendWelcomes::templates($this->app->make(NotificationsApi::class));
        // Each person's own link to choose their password (Identity's welcome page), on the academy's host.
        $this->app->make(NotificationLinks::class)->registerPersonal('welcome', function (array $action, string $userId) {
            $path = URL::temporarySignedRoute('identity.welcome', now()->addDays(self::WELCOME_DAYS), ['user' => $userId], absolute: false);

            return $this->app->make(TenantDirectory::class)->baseUrl($this->app->make(TenantContext::class)->require()).$path;
        });

        Event::listen(PeopleImported::class, SendWelcomes::class);
    }
}
