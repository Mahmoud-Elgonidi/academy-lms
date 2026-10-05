<?php

namespace Modules\SharedKernel\Tenancy;

use Closure;
use Illuminate\Support\Facades\Context;
use Modules\SharedKernel\Tenancy\Exceptions\TenantNotResolved;

/**
 * The tenant the current request or job runs for.
 *
 * Setting it also sets the Postgres session variable used by RLS and adds
 * tenant_id to Laravel's Context, so logs carry it and queued jobs restore it.
 */
final class TenantContext
{
    public const CONTEXT_KEY = 'tenant_id';

    private ?TenantRef $tenant = null;

    public function __construct(private readonly PgTenantSession $session) {}

    public function set(TenantRef $tenant): void
    {
        $this->tenant = $tenant;
        $this->session->apply($tenant->id);
        Context::add(self::CONTEXT_KEY, $tenant->id);
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->session->apply(null);
        Context::forget(self::CONTEXT_KEY);
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function current(): ?TenantRef
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    /** @throws TenantNotResolved */
    public function require(): TenantRef
    {
        return $this->tenant ?? throw new TenantNotResolved;
    }

    /**
     * Runs $callback as $tenant, then restores whatever was set before.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(TenantRef $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $previous ? $this->set($previous) : $this->clear();
        }
    }
}
