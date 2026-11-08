<?php

namespace Modules\Platform\Plans;

use Carbon\CarbonImmutable;
use Modules\Platform\Metering\Meters;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The current academy's plan beside what it uses now, limit by limit: as
 * each owning module counts it (UsageMeters), and the monthly ones (video
 * minutes, AI credits) from the usage ledger, over this calendar month on the
 * academy's clock, with the bought balance and, for video, the rooms open
 * now. The AI credits show only while the academy has the AI area.
 */
final class PlanUsage
{
    public function __construct(
        private readonly PlatformAcademyPlan $plan,
        private readonly UsageMeters $meters,
        private readonly TenantContext $tenants,
        private readonly Meters $ledger,
    ) {}

    public function terms(): ?PlanTerms
    {
        return $this->plan->terms();
    }

    public function period(?CarbonImmutable $at = null): UsagePeriod
    {
        return UsagePeriod::monthOf($at ?? CarbonImmutable::now(), $this->tenants->require()->defaultTimezone);
    }

    /** @return list<UsageLine> */
    public function lines(?CarbonImmutable $at = null): array
    {
        $terms = $this->plan->terms();
        $period = $this->period($at);
        $academy = $this->tenants->require();

        return array_values(array_map(function (Limit $limit) use ($terms, $period, $academy): UsageLine {
            $overridden = $terms !== null && in_array($limit->value, $terms->overridden, true);
            if ($limit->monthly()) {
                return self::monthly($this->ledger, $academy->id, $limit, $terms?->limit($limit), $overridden, $this->meters->runningNow($limit));
            }

            return new UsageLine($limit, $this->meters->measure($limit, $period), $terms?->limit($limit), $overridden);
        }, self::shown(fn (Feature $f) => $this->plan->has($f))));
    }

    /**
     * A monthly limit's line from the usage ledger: this month's use (and
     * what runs now, not counted yet), the plan's allowance, and what more
     * may be used over it (allowed() = used + what may still be used).
     */
    public static function monthly(Meters $ledger, string $academyId, Limit $limit, ?int $max, bool $overridden, int $running = 0): UsageLine
    {
        $meter = Meter::fromLimit($limit);
        if ($max === null) {
            return new UsageLine($limit, $ledger->usedIn($academyId, $meter, $ledger->month($academyId)) + $running, null, $overridden);
        }
        $state = $ledger->state($academyId, $meter);
        $used = $state->used + $running;
        $available = $state->available() - $running;

        return new UsageLine($limit, $used, $max, $overridden, $used + $available - $max, $state->bought);
    }

    /**
     * The limits an academy's plan shows: the AI uses only with the AI area.
     *
     * @param  callable(Feature): bool  $has
     * @return list<Limit>
     */
    public static function shown(callable $has): array
    {
        return array_values(array_filter(Limit::cases(), fn (Limit $l) => $l !== Limit::AiUses || $has(Feature::Ai)));
    }
}
