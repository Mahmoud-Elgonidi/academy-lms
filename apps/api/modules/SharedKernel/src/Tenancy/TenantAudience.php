<?php

namespace Modules\SharedKernel\Tenancy;

use Illuminate\Contracts\Container\Container;

/**
 * AcademyAudience from the tenant: the current academy's TenantRef (filled by
 * Platform from academies.audience), another academy's through the
 * TenantResolver Platform binds, kept for the rest of the request or job
 * (scoped: workers and Octane start each one afresh).
 */
final class TenantAudience implements AcademyAudience
{
    /** @var array<string, Audience> academy id => its audience, this request or job */
    private array $known = [];

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Container $app,
    ) {}

    public function current(): Audience
    {
        return $this->tenants->current()?->audience ?? Audience::DEFAULT;
    }

    public function adultsOnly(): bool
    {
        return $this->current() === Audience::Adults;
    }

    public function forAcademy(string $academyId): Audience
    {
        if ($this->tenants->id() === $academyId) {
            return $this->current();
        }

        return $this->known[$academyId] ??= ($this->app->bound(TenantResolver::class)
            ? $this->app->make(TenantResolver::class)->byId($academyId)?->audience
            : null) ?? Audience::DEFAULT;
    }

    /** Drops what it read of other academies (after one of them changed). */
    public function forget(): void
    {
        $this->known = [];
    }
}
