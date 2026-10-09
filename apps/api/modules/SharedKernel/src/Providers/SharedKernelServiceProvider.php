<?php

namespace Modules\SharedKernel\Providers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\SharedKernel\Auth\TimingSafeUserProvider;
use Modules\SharedKernel\Files\ClamAvScanner;
use Modules\SharedKernel\Files\NullUploadScanner;
use Modules\SharedKernel\Files\UploadScanner;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\OpenPlan;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\Console\LiveRelayCommand;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\PgTenantSession;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantResolver;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\LocalTimeResolver;
use Modules\SharedKernel\Time\ViewerTimezone;

final class SharedKernelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: reset between requests under Octane and between queued jobs.
        $this->app->scoped(PgTenantSession::class);
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(ViewerTimezone::class);
        $this->app->scoped(LocalTimePresenter::class);
        $this->app->singleton(LocalTimeResolver::class);
        $this->app->singleton(LiveSignals::class);
        // Uploads are checked for malware by clamd when the server runs one (UPLOAD_SCANNER=clamav), else not at all.
        $this->mergeConfigFrom(__DIR__.'/../../config/uploads.php', 'uploads');
        $this->app->singleton(UploadScanner::class, fn () => config('uploads.scanner') === 'clamav'
            ? new ClamAvScanner((string) config('uploads.clamav.host'), (int) config('uploads.clamav.port'), (int) config('uploads.clamav.timeout'))
            : new NullUploadScanner);
        // The academy's plan: everything until Platform binds the plans; the meters, from each module that counts.
        $this->app->scopedIf(AcademyPlan::class, OpenPlan::class);
        $this->app->singleton(UsageMeters::class);
        // What each module holds about a person, for the Privacy module's copies and erasures.
        $this->app->singleton(PersonalData::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'shared');
        // Signing in takes as long whether or not the account exists (config/auth.php's providers).
        Auth::provider('timing-safe', fn ($app, array $config) => new TimingSafeUserProvider($app['hash'], $config['model']));
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'shared');
        if ($this->app->runningInConsole()) {
            $this->commands([LiveRelayCommand::class]);
        }

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $this->app->make(PgTenantSession::class)->onConnectionEstablished($event);
        });

        $this->restoreTenantInQueuedJobs();
        $this->registerBlueprintMacros();
        $this->registerRateLimits();
    }

    /**
     * Ceilings for every open door (docs/architecture/03, section 11), on top
     * of the tight ones a door keeps for itself (sign-in, codes, proofs):
     * the apps' API per person (or address, before signing in; the portal
     * passes its visitor's address on), and the pages behind signed links
     * and the class page. Named, so they never share a count with another door.
     * Given to the limiter when something first asks for it: making it opens
     * the cache store, which booting (an image build, a console command) must
     * not do.
     */
    private function registerRateLimits(): void
    {
        $this->callAfterResolving(RateLimiter::class, function (RateLimiter $limiter): void {
            $limiter->for('api', fn (Request $request) => $request->user()
                ? Limit::perMinute(600)->by('user:'.$request->user()->getAuthIdentifier())
                : Limit::perMinute(240)->by('ip:'.$request->ip()));
            $limiter->for('links', fn (Request $request) => $request->user()
                ? Limit::perMinute(120)->by('user:'.$request->user()->getAuthIdentifier())
                : Limit::perMinute(120)->by('ip:'.$request->ip()));
        });
    }

    /**
     * TenantContext::set() puts tenant_id in Laravel's Context, which Laravel
     * serializes into every job payload. When a worker hydrates that context,
     * set the tenant again; once the job is done, clear it (on a worker).
     */
    private function restoreTenantInQueuedJobs(): void
    {
        Context::hydrated(function (ContextRepository $context): void {
            $tenantId = $context->get(TenantContext::CONTEXT_KEY);
            if (! is_string($tenantId)) {
                return;
            }

            $tenant = $this->app->make(TenantResolver::class)->byId($tenantId);
            if ($tenant !== null) {
                $this->app->make(TenantContext::class)->set($tenant);
            }
        });

        // A sync job runs inside the request (or command) that dispatched it,
        // which still needs its academy afterwards: only workers clear it.
        $clear = function (JobProcessed|JobFailed|JobExceptionOccurred $event): void {
            if ($event->connectionName !== 'sync') {
                $this->app->make(TenantContext::class)->clear();
            }
        };
        Event::listen(JobProcessed::class, $clear);
        Event::listen(JobFailed::class, $clear);
        Event::listen(JobExceptionOccurred::class, $clear);

        // Mail that left, for the health page (a failure is noted by whoever sent it).
        Event::listen(MessageSent::class, fn () => MailHealth::sent());
    }

    private function registerBlueprintMacros(): void
    {
        // Case-insensitive text (emails, slugs, hosts) from the citext extension.
        PostgresGrammar::macro('typeCitext', fn () => 'citext');
        Blueprint::macro('citext', function (string $column) {
            /** @var Blueprint $this */
            return $this->addColumn('citext', $column);
        });

        // academy_id uuid not null, FK to academies, indexed.
        Blueprint::macro('tenant', function () {
            /** @var Blueprint $this */
            return $this->foreignUuid('academy_id')->constrained('academies')->restrictOnDelete();
        });

        // unique (academy_id, id): the target of composite tenant foreign keys.
        Blueprint::macro('tenantKey', function () {
            /** @var Blueprint $this */
            return $this->unique(['academy_id', 'id']);
        });

        // (academy_id, $column) -> $table (academy_id, id). RLS does not stop
        // an insert from pointing at another academy's row (FK checks bypass
        // RLS), so tenant-to-tenant references always carry academy_id.
        Blueprint::macro('tenantForeign', function (string $column, string $table) {
            /** @var Blueprint $this */
            return $this->foreign(['academy_id', $column])->references(['academy_id', 'id'])->on($table);
        });
    }
}
