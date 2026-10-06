<?php

namespace Modules\SharedKernel\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'shared');

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $this->app->make(PgTenantSession::class)->onConnectionEstablished($event);
        });

        $this->restoreTenantInQueuedJobs();
        $this->registerBlueprintMacros();
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
