<?php

namespace Modules\SharedKernel\Time;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * How the person on the other end of this request reads a clock: their own
 * choice (users.time_format), else their academy's (TenantRef::timeFormat),
 * else 12 hours. Unlike the zone, no device decides it: it is a choice.
 */
final class ViewerClock
{
    private ?Authenticatable $user = null;

    /** Set by a page that read the person itself (a signed link): their choice, known without a user model. */
    private ?TimeFormat $chosen = null;

    private bool $known = false;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuthFactory $auth,
    ) {}

    /** Forgets the previous request's user (long-lived workers reuse this object). */
    public function reset(): void
    {
        $this->user = null;
        $this->chosen = null;
        $this->known = false;
    }

    /** For pages that know the person without the auth guard (the login response, signed links). */
    public function setUser(?Authenticatable $user): void
    {
        $this->user = $user;
    }

    /** For a page that reads the person's row itself (a signed link): their users.time_format, null for the academy's. */
    public function choose(mixed $timeFormat): void
    {
        $this->chosen = TimeFormat::of($timeFormat);
        $this->known = true;
    }

    /** The person's own choice, or null to follow the academy. */
    public function own(): ?TimeFormat
    {
        if ($this->known) {
            return $this->chosen;
        }

        return TimeFormat::of(data_get($this->user ?? $this->auth->guard()->user(), 'time_format'));
    }

    /** The academy's choice for everyone (12 hours where there is no academy). */
    public function academy(): TimeFormat
    {
        return TimeFormat::of($this->tenants->current()?->timeFormat) ?? TimeFormat::DEFAULT;
    }

    public function effective(): TimeFormat
    {
        return $this->own() ?? $this->academy();
    }
}
