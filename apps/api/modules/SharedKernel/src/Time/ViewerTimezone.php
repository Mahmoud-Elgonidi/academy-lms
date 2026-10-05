<?php

namespace Modules\SharedKernel\Time;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The timezone the person on the other end of this request reads times in.
 *
 * Order: the device's zone (X-Timezone header, sent by the apps and the web
 * portal), then the user's profile zone, then the academy's default, then UTC.
 * When the device and the profile disagree (a traveller), mismatch() is true
 * and the app asks whether to update the profile.
 */
final class ViewerTimezone
{
    public const HEADER = 'X-Timezone';

    private ?string $device = null;

    private ?Authenticatable $user = null;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuthFactory $auth,
    ) {}

    /** Forgets the previous request's device and user (long-lived workers reuse this object). */
    public function reset(): void
    {
        $this->device = null;
        $this->user = null;
    }

    /** Invalid values (offsets, typos) are ignored rather than trusted. */
    public function setDevice(?string $timezone): void
    {
        $this->device = Timezones::isValid($timezone) ? $timezone : null;
    }

    /** For responses built before the auth guard knows the user (the login response). */
    public function setUser(?Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function device(): ?string
    {
        return $this->device;
    }

    public function profile(): ?string
    {
        $timezone = data_get($this->user ?? $this->auth->guard()->user(), 'timezone');

        return Timezones::isValid($timezone) ? $timezone : null;
    }

    public function academy(): ?string
    {
        $timezone = $this->tenants->current()?->defaultTimezone;

        return Timezones::isValid($timezone) ? $timezone : null;
    }

    public function effective(): string
    {
        return $this->device ?? $this->profile() ?? $this->academy() ?? 'UTC';
    }

    /** True when the device and the profile keep different clocks. */
    public function mismatch(): bool
    {
        $profile = $this->profile();

        return $this->device !== null && $profile !== null && ! Timezones::sameClock($this->device, $profile);
    }
}
