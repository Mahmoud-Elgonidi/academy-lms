<?php

namespace Modules\Platform\Plans;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The current academy's plan beside what it uses now, limit by limit, as
 * each owning module counts it (UsageMeters); monthly limits over this
 * calendar month on the academy's clock, with the month's extra packs. The
 * AI uses show only while the academy has the AI area.
 */
final class PlanUsage
{
    public function __construct(
        private readonly PlatformAcademyPlan $plan,
        private readonly UsageMeters $meters,
        private readonly TenantContext $tenants,
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
        $extras = $terms === null ? [] : Extras::of($academy->id, $period->key($academy->defaultTimezone));

        return array_values(array_map(fn (Limit $limit) => new UsageLine(
            $limit,
            $this->meters->measure($limit, $period),
            $terms?->limit($limit),
            $terms !== null && in_array($limit->value, $terms->overridden, true),
            $extras[$limit->value] ?? 0,
        ), self::shown(fn (Feature $f) => $this->plan->has($f))));
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
