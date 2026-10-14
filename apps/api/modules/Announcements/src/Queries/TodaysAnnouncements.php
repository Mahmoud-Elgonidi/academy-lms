<?php

namespace Modules\Announcements\Queries;

use Carbon\CarbonImmutable;
use Modules\Announcements\Domain\AnnouncementRules;
use Modules\Announcements\Models\Announcement;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * How many announcements the academy sent today, and how many it may still
 * send: today is the day on the academy's own clock, from its midnight
 * (through LocalTimeResolver, so a clock change that day counts right).
 */
final class TodaysAnnouncements
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly LocalTimeResolver $resolver,
    ) {}

    /** The instant today began on the academy's clock. */
    public function since(?CarbonImmutable $now = null): CarbonImmutable
    {
        $timezone = $this->tenants->require()->defaultTimezone;
        $today = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->toDateString();

        return $this->resolver->resolve($today, '00:00', $timezone)->utc;
    }

    public function sent(?CarbonImmutable $now = null): int
    {
        return Announcement::query()->where('sent_at', '>=', $this->since($now))->count();
    }

    public function left(?CarbonImmutable $now = null): int
    {
        return AnnouncementRules::left($this->sent($now));
    }
}
